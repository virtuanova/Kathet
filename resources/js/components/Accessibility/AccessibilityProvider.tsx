import React, { createContext, useContext, useState, useEffect, useRef, ReactNode } from 'react';
import { useI18n } from '@/hooks/useI18n';

interface AccessibilitySettings {
  fontSize: 'small' | 'medium' | 'large' | 'extra-large';
  contrast: 'normal' | 'high' | 'extra-high';
  reducedMotion: boolean;
  screenReaderOptimizations: boolean;
  keyboardNavigation: boolean;
  focusIndicators: 'standard' | 'enhanced';
  colorBlindSupport: 'none' | 'deuteranopia' | 'protanopia' | 'tritanopia';
  voiceOver: boolean;
}

interface AccessibilityState extends AccessibilitySettings {
  announcements: string[];
  skipLinks: { href: string; text: string }[];
  focusHistory: string[];
  landmarkRegions: string[];
}

interface AccessibilityContextType extends AccessibilityState {
  updateSettings: (settings: Partial<AccessibilitySettings>) => void;
  announce: (message: string, priority?: 'polite' | 'assertive' | 'off') => void;
  addSkipLink: (href: string, text: string) => void;
  removeSkipLink: (href: string) => void;
  addLandmark: (landmark: string) => void;
  focusElement: (selector: string) => boolean;
  restoreFocus: () => boolean;
  isHighContrast: () => boolean;
  isReducedMotion: () => boolean;
  getAriaDescribedBy: (elementId: string) => string;
  validateAriaLabel: (element: HTMLElement) => string[];
}

const defaultSettings: AccessibilitySettings = {
  fontSize: 'medium',
  contrast: 'normal',
  reducedMotion: false,
  screenReaderOptimizations: false,
  keyboardNavigation: true,
  focusIndicators: 'standard',
  colorBlindSupport: 'none',
  voiceOver: false,
};

const AccessibilityContext = createContext<AccessibilityContextType | undefined>(undefined);

interface AccessibilityProviderProps {
  children: ReactNode;
}

export const AccessibilityProvider: React.FC<AccessibilityProviderProps> = ({ children }) => {
  const { t } = useI18n();
  const [settings, setSettings] = useState<AccessibilitySettings>(defaultSettings);
  const [announcements, setAnnouncements] = useState<string[]>([]);
  const [skipLinks, setSkipLinks] = useState<{ href: string; text: string }[]>([]);
  const [focusHistory, setFocusHistory] = useState<string[]>([]);
  const [landmarkRegions, setLandmarkRegions] = useState<string[]>([]);
  
  const liveRegionRef = useRef<HTMLDivElement>(null);
  const assertiveRegionRef = useRef<HTMLDivElement>(null);

  // Load settings from localStorage on mount
  useEffect(() => {
    const savedSettings = localStorage.getItem('accessibility-settings');
    if (savedSettings) {
      try {
        const parsed = JSON.parse(savedSettings);
        setSettings({ ...defaultSettings, ...parsed });
      } catch (error) {
        console.warn('Failed to parse accessibility settings:', error);
      }
    }

    // Detect system preferences
    detectSystemPreferences();
  }, []);

  // Apply settings to document
  useEffect(() => {
    applyAccessibilitySettings();
  }, [settings]);

  // Track focus for restoration
  useEffect(() => {
    const handleFocus = (event: FocusEvent) => {
      const target = event.target as HTMLElement;
      if (target && target.id) {
        setFocusHistory(prev => [...prev.slice(-4), `#${target.id}`]);
      }
    };

    document.addEventListener('focusin', handleFocus);
    return () => document.removeEventListener('focusin', handleFocus);
  }, []);

  const detectSystemPreferences = () => {
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const prefersHighContrast = window.matchMedia('(prefers-contrast: high)').matches;
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

    if (prefersReducedMotion || prefersHighContrast) {
      setSettings(prev => ({
        ...prev,
        reducedMotion: prefersReducedMotion,
        contrast: prefersHighContrast ? 'high' : prev.contrast,
      }));
    }
  };

  const applyAccessibilitySettings = () => {
    const root = document.documentElement;
    const body = document.body;

    // Font size
    root.setAttribute('data-font-size', settings.fontSize);
    
    // Contrast
    root.setAttribute('data-contrast', settings.contrast);
    
    // Reduced motion
    if (settings.reducedMotion) {
      root.style.setProperty('--animation-duration', '0ms');
      root.style.setProperty('--transition-duration', '0ms');
    } else {
      root.style.removeProperty('--animation-duration');
      root.style.removeProperty('--transition-duration');
    }

    // Color blind support
    root.setAttribute('data-color-blind-support', settings.colorBlindSupport);

    // Focus indicators
    root.setAttribute('data-focus-indicators', settings.focusIndicators);

    // Screen reader optimizations
    if (settings.screenReaderOptimizations) {
      body.classList.add('screen-reader-optimized');
    } else {
      body.classList.remove('screen-reader-optimized');
    }

    // Save to localStorage
    localStorage.setItem('accessibility-settings', JSON.stringify(settings));
  };

  const updateSettings = (newSettings: Partial<AccessibilitySettings>) => {
    setSettings(prev => ({ ...prev, ...newSettings }));
    announce(t('accessibility.settings_updated'), 'polite');
  };

  const announce = (message: string, priority: 'polite' | 'assertive' | 'off' = 'polite') => {
    if (priority === 'off') return;

    const region = priority === 'assertive' ? assertiveRegionRef.current : liveRegionRef.current;
    if (region) {
      region.textContent = message;
      
      // Clear after announcement
      setTimeout(() => {
        if (region) region.textContent = '';
      }, 1000);
    }

    setAnnouncements(prev => [...prev, message].slice(-10)); // Keep last 10 announcements
  };

  const addSkipLink = (href: string, text: string) => {
    setSkipLinks(prev => {
      const existing = prev.find(link => link.href === href);
      if (existing) {
        return prev.map(link => link.href === href ? { href, text } : link);
      }
      return [...prev, { href, text }];
    });
  };

  const removeSkipLink = (href: string) => {
    setSkipLinks(prev => prev.filter(link => link.href !== href));
  };

  const addLandmark = (landmark: string) => {
    setLandmarkRegions(prev => {
      if (!prev.includes(landmark)) {
        return [...prev, landmark];
      }
      return prev;
    });
  };

  const focusElement = (selector: string): boolean => {
    try {
      const element = document.querySelector(selector) as HTMLElement;
      if (element && typeof element.focus === 'function') {
        element.focus();
        return true;
      }
    } catch (error) {
      console.warn('Failed to focus element:', selector, error);
    }
    return false;
  };

  const restoreFocus = (): boolean => {
    const lastFocused = focusHistory[focusHistory.length - 1];
    if (lastFocused) {
      return focusElement(lastFocused);
    }
    return false;
  };

  const isHighContrast = (): boolean => {
    return settings.contrast === 'high' || settings.contrast === 'extra-high';
  };

  const isReducedMotion = (): boolean => {
    return settings.reducedMotion || window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  };

  const getAriaDescribedBy = (elementId: string): string => {
    const descriptions = [];
    
    if (settings.screenReaderOptimizations) {
      descriptions.push(`${elementId}-help`);
    }
    
    if (settings.keyboardNavigation) {
      descriptions.push(`${elementId}-keyboard-help`);
    }
    
    return descriptions.join(' ');
  };

  const validateAriaLabel = (element: HTMLElement): string[] => {
    const issues: string[] = [];
    
    if (!element.getAttribute('aria-label') && 
        !element.getAttribute('aria-labelledby') && 
        !element.textContent?.trim()) {
      issues.push('Element has no accessible name');
    }
    
    const role = element.getAttribute('role');
    if (role === 'button' && !element.hasAttribute('aria-pressed') && 
        element.getAttribute('type') !== 'submit') {
      // Check if it should have aria-pressed for toggle buttons
      if (element.classList.contains('toggle') || 
          element.getAttribute('data-toggle')) {
        issues.push('Toggle button missing aria-pressed attribute');
      }
    }
    
    if (element.hasAttribute('aria-expanded') && 
        !['true', 'false'].includes(element.getAttribute('aria-expanded') || '')) {
      issues.push('aria-expanded must be true or false');
    }
    
    return issues;
  };

  const contextValue: AccessibilityContextType = {
    ...settings,
    announcements,
    skipLinks,
    focusHistory,
    landmarkRegions,
    updateSettings,
    announce,
    addSkipLink,
    removeSkipLink,
    addLandmark,
    focusElement,
    restoreFocus,
    isHighContrast,
    isReducedMotion,
    getAriaDescribedBy,
    validateAriaLabel,
  };

  return (
    <AccessibilityContext.Provider value={contextValue}>
      {children}
      
      {/* Live regions for screen reader announcements */}
      <div
        ref={liveRegionRef}
        aria-live="polite"
        aria-atomic="true"
        className="sr-only"
        id="live-region-polite"
      />
      <div
        ref={assertiveRegionRef}
        aria-live="assertive"
        aria-atomic="true"
        className="sr-only"
        id="live-region-assertive"
      />
      
      {/* Skip links */}
      {skipLinks.length > 0 && (
        <nav className="skip-links" aria-label={t('accessibility.skip_links')}>
          {skipLinks.map(({ href, text }) => (
            <a
              key={href}
              href={href}
              className="skip-link"
              onFocus={() => announce(t('accessibility.skip_link_focused', { text }), 'polite')}
            >
              {text}
            </a>
          ))}
        </nav>
      )}
    </AccessibilityContext.Provider>
  );
};

export const useAccessibility = (): AccessibilityContextType => {
  const context = useContext(AccessibilityContext);
  if (!context) {
    throw new Error('useAccessibility must be used within an AccessibilityProvider');
  }
  return context;
};

export default AccessibilityProvider;
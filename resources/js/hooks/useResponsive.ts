import { useState, useEffect, useCallback } from 'react';

export interface BreakpointConfig {
  xs: number;  // Extra small devices
  sm: number;  // Small devices
  md: number;  // Medium devices (tablets)
  lg: number;  // Large devices (desktops)
  xl: number;  // Extra large devices
  xxl: number; // Extra extra large devices
}

export interface ResponsiveState {
  width: number;
  height: number;
  breakpoint: keyof BreakpointConfig;
  isMobile: boolean;
  isTablet: boolean;
  isDesktop: boolean;
  isTouch: boolean;
  orientation: 'portrait' | 'landscape';
  devicePixelRatio: number;
  isOnline: boolean;
  connectionType?: string;
  reducedMotion: boolean;
}

export interface UseResponsiveReturn extends ResponsiveState {
  isBreakpoint: (breakpoint: keyof BreakpointConfig) => boolean;
  isBreakpointUp: (breakpoint: keyof BreakpointConfig) => boolean;
  isBreakpointDown: (breakpoint: keyof BreakpointConfig) => boolean;
  isBreakpointBetween: (min: keyof BreakpointConfig, max: keyof BreakpointConfig) => boolean;
  isMobileViewport: () => boolean;
  isTabletViewport: () => boolean;
  isDesktopViewport: () => boolean;
  getColumns: (mobile: number, tablet?: number, desktop?: number) => number;
  formatForDevice: (content: { mobile: string; tablet?: string; desktop?: string }) => string;
}

const DEFAULT_BREAKPOINTS: BreakpointConfig = {
  xs: 0,
  sm: 576,
  md: 768,
  lg: 992,
  xl: 1200,
  xxl: 1400,
};

/**
 * Enhanced responsive hook with mobile-first approach
 * Based on Moodle's responsive design principles
 */
export const useResponsive = (customBreakpoints?: Partial<BreakpointConfig>): UseResponsiveReturn => {
  const breakpoints = { ...DEFAULT_BREAKPOINTS, ...customBreakpoints };
  
  const [state, setState] = useState<ResponsiveState>(() => {
    // Server-side rendering safe defaults
    if (typeof window === 'undefined') {
      return {
        width: 1024,
        height: 768,
        breakpoint: 'lg' as keyof BreakpointConfig,
        isMobile: false,
        isTablet: false,
        isDesktop: true,
        isTouch: false,
        orientation: 'landscape',
        devicePixelRatio: 1,
        isOnline: true,
        reducedMotion: false,
      };
    }

    return getResponsiveState();
  });

  function getResponsiveState(): ResponsiveState {
    const width = window.innerWidth;
    const height = window.innerHeight;
    const devicePixelRatio = window.devicePixelRatio || 1;
    const orientation = width > height ? 'landscape' : 'portrait';
    
    // Determine breakpoint
    let breakpoint: keyof BreakpointConfig = 'xs';
    if (width >= breakpoints.xxl) breakpoint = 'xxl';
    else if (width >= breakpoints.xl) breakpoint = 'xl';
    else if (width >= breakpoints.lg) breakpoint = 'lg';
    else if (width >= breakpoints.md) breakpoint = 'md';
    else if (width >= breakpoints.sm) breakpoint = 'sm';

    // Device type detection (mobile-first)
    const isMobile = width < breakpoints.md;
    const isTablet = width >= breakpoints.md && width < breakpoints.lg;
    const isDesktop = width >= breakpoints.lg;
    
    // Touch device detection
    const isTouch = 'ontouchstart' in window || 
                   navigator.maxTouchPoints > 0 || 
                   (navigator as any).msMaxTouchPoints > 0;

    // Network status
    const isOnline = navigator.onLine;
    
    // Connection type (if available)
    const connection = (navigator as any).connection || 
                      (navigator as any).mozConnection || 
                      (navigator as any).webkitConnection;
    const connectionType = connection?.effectiveType;

    // Reduced motion preference
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    return {
      width,
      height,
      breakpoint,
      isMobile,
      isTablet,
      isDesktop,
      isTouch,
      orientation,
      devicePixelRatio,
      isOnline,
      connectionType,
      reducedMotion,
    };
  }

  const updateState = useCallback(() => {
    setState(getResponsiveState());
  }, []);

  // Debounced resize handler for performance
  const debouncedUpdateState = useCallback(
    debounce(updateState, 150),
    [updateState]
  );

  useEffect(() => {
    if (typeof window === 'undefined') return;

    // Update state on mount
    updateState();

    // Event listeners
    window.addEventListener('resize', debouncedUpdateState);
    window.addEventListener('orientationchange', debouncedUpdateState);
    window.addEventListener('online', updateState);
    window.addEventListener('offline', updateState);

    // Media query listeners
    const reducedMotionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    const handleReducedMotionChange = (e: MediaQueryListEvent) => {
      setState(prev => ({ ...prev, reducedMotion: e.matches }));
    };
    
    reducedMotionQuery.addEventListener('change', handleReducedMotionChange);

    return () => {
      window.removeEventListener('resize', debouncedUpdateState);
      window.removeEventListener('orientationchange', debouncedUpdateState);
      window.removeEventListener('online', updateState);
      window.removeEventListener('offline', updateState);
      reducedMotionQuery.removeEventListener('change', handleReducedMotionChange);
    };
  }, [debouncedUpdateState, updateState]);

  // Utility functions
  const isBreakpoint = useCallback((breakpoint: keyof BreakpointConfig): boolean => {
    return state.breakpoint === breakpoint;
  }, [state.breakpoint]);

  const isBreakpointUp = useCallback((breakpoint: keyof BreakpointConfig): boolean => {
    return state.width >= breakpoints[breakpoint];
  }, [state.width]);

  const isBreakpointDown = useCallback((breakpoint: keyof BreakpointConfig): boolean => {
    return state.width < breakpoints[breakpoint];
  }, [state.width]);

  const isBreakpointBetween = useCallback(
    (min: keyof BreakpointConfig, max: keyof BreakpointConfig): boolean => {
      return state.width >= breakpoints[min] && state.width < breakpoints[max];
    },
    [state.width]
  );

  const isMobileViewport = useCallback((): boolean => {
    return state.width < breakpoints.md;
  }, [state.width]);

  const isTabletViewport = useCallback((): boolean => {
    return state.width >= breakpoints.md && state.width < breakpoints.lg;
  }, [state.width]);

  const isDesktopViewport = useCallback((): boolean => {
    return state.width >= breakpoints.lg;
  }, [state.width]);

  const getColumns = useCallback(
    (mobile: number, tablet?: number, desktop?: number): number => {
      if (state.isDesktop && desktop !== undefined) return desktop;
      if (state.isTablet && tablet !== undefined) return tablet;
      return mobile;
    },
    [state.isDesktop, state.isTablet]
  );

  const formatForDevice = useCallback(
    (content: { mobile: string; tablet?: string; desktop?: string }): string => {
      if (state.isDesktop && content.desktop) return content.desktop;
      if (state.isTablet && content.tablet) return content.tablet;
      return content.mobile;
    },
    [state.isDesktop, state.isTablet]
  );

  return {
    ...state,
    isBreakpoint,
    isBreakpointUp,
    isBreakpointDown,
    isBreakpointBetween,
    isMobileViewport,
    isTabletViewport,
    isDesktopViewport,
    getColumns,
    formatForDevice,
  };
};

/**
 * Hook for managing responsive images
 */
export const useResponsiveImage = () => {
  const { width, devicePixelRatio, connectionType } = useResponsive();

  const getOptimalImageSize = useCallback(
    (baseWidth: number, sizes?: { sm?: number; md?: number; lg?: number }) => {
      // Consider device pixel ratio for high-DPI displays
      const adjustedWidth = baseWidth * Math.min(devicePixelRatio, 2);
      
      // Adjust for connection speed
      const connectionMultiplier = getConnectionMultiplier(connectionType);
      const targetWidth = adjustedWidth * connectionMultiplier;

      if (sizes) {
        if (width >= 992 && sizes.lg) return Math.min(targetWidth, sizes.lg);
        if (width >= 768 && sizes.md) return Math.min(targetWidth, sizes.md);
        if (width >= 576 && sizes.sm) return Math.min(targetWidth, sizes.sm);
      }

      return Math.min(targetWidth, width);
    },
    [width, devicePixelRatio, connectionType]
  );

  const getSrcSet = useCallback(
    (imagePath: string, sizes: number[]) => {
      return sizes
        .map(size => `${imagePath}?w=${size} ${size}w`)
        .join(', ');
    },
    []
  );

  const getSizes = useCallback(
    (breakpointSizes: { [key: string]: string }) => {
      const mediaQueries = Object.entries(breakpointSizes)
        .map(([breakpoint, size]) => {
          const minWidth = DEFAULT_BREAKPOINTS[breakpoint as keyof BreakpointConfig];
          return `(min-width: ${minWidth}px) ${size}`;
        });
      
      return mediaQueries.join(', ');
    },
    []
  );

  return {
    getOptimalImageSize,
    getSrcSet,
    getSizes,
  };
};

/**
 * Hook for responsive typography
 */
export const useResponsiveTypography = () => {
  const { breakpoint, isMobile, isTablet } = useResponsive();

  const getResponsiveFontSize = useCallback(
    (sizes: { mobile: number; tablet?: number; desktop?: number }) => {
      if (!isMobile && !isTablet && sizes.desktop) return `${sizes.desktop}rem`;
      if (!isMobile && sizes.tablet) return `${sizes.tablet}rem`;
      return `${sizes.mobile}rem`;
    },
    [isMobile, isTablet]
  );

  const getResponsiveSpacing = useCallback(
    (spacing: { mobile: number; tablet?: number; desktop?: number }) => {
      if (!isMobile && !isTablet && spacing.desktop) return `${spacing.desktop}rem`;
      if (!isMobile && spacing.tablet) return `${spacing.tablet}rem`;
      return `${spacing.mobile}rem`;
    },
    [isMobile, isTablet]
  );

  const getFluidFontSize = useCallback(
    (minSize: number, maxSize: number, minViewport = 320, maxViewport = 1200) => {
      return `clamp(${minSize}rem, ${minSize}rem + (${maxSize} - ${minSize}) * ((100vw - ${minViewport}px) / (${maxViewport} - ${minViewport})), ${maxSize}rem)`;
    },
    []
  );

  return {
    getResponsiveFontSize,
    getResponsiveSpacing,
    getFluidFontSize,
    currentBreakpoint: breakpoint,
  };
};

/**
 * Hook for responsive navigation
 */
export const useResponsiveNavigation = () => {
  const { isMobile, isTablet } = useResponsive();
  const [isMenuOpen, setIsMenuOpen] = useState(false);

  const toggleMenu = useCallback(() => {
    setIsMenuOpen(prev => !prev);
  }, []);

  const closeMenu = useCallback(() => {
    setIsMenuOpen(false);
  }, []);

  // Auto-close menu when switching to desktop
  useEffect(() => {
    if (!isMobile && !isTablet) {
      setIsMenuOpen(false);
    }
  }, [isMobile, isTablet]);

  const getNavigationConfig = useCallback(() => {
    return {
      showToggle: isMobile,
      isCollapsible: isMobile || isTablet,
      menuStyle: isMobile ? 'overlay' : isTablet ? 'collapse' : 'horizontal',
      maxVisibleItems: isMobile ? 3 : isTablet ? 5 : 8,
    };
  }, [isMobile, isTablet]);

  return {
    isMenuOpen,
    toggleMenu,
    closeMenu,
    getNavigationConfig,
    shouldShowMobileMenu: isMobile,
  };
};

// Utility functions
function debounce<T extends (...args: any[]) => void>(func: T, wait: number): T {
  let timeout: ReturnType<typeof setTimeout>;
  
  return ((...args: Parameters<T>) => {
    clearTimeout(timeout);
    timeout = setTimeout(() => func(...args), wait);
  }) as T;
}

function getConnectionMultiplier(connectionType?: string): number {
  if (!connectionType) return 1;
  
  switch (connectionType) {
    case 'slow-2g':
    case '2g':
      return 0.5;
    case '3g':
      return 0.75;
    case '4g':
    case '5g':
    default:
      return 1;
  }
}

export default useResponsive;
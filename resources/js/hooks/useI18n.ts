import { useState, useEffect, useContext } from 'react';
import i18n from 'i18next';
import { initReactI18next, useTranslation as useReactI18n } from 'react-i18next';
import Backend from 'i18next-http-backend';
import LanguageDetector from 'i18next-browser-languagedetector';

// Initialize i18n
i18n
  .use(Backend)
  .use(LanguageDetector)
  .use(initReactI18next)
  .init({
    lng: 'en', // Default language
    fallbackLng: 'en',
    debug: process.env.NODE_ENV === 'development',
    
    interpolation: {
      escapeValue: false, // React already does escaping
      formatSeparator: ',',
      format: (value, format, lng) => {
        if (format === 'uppercase') return value.toUpperCase();
        if (format === 'lowercase') return value.toLowerCase();
        if (format === 'capitalize') return value.charAt(0).toUpperCase() + value.slice(1);
        if (format === 'number') return new Intl.NumberFormat(lng).format(value);
        if (format === 'currency') return new Intl.NumberFormat(lng, { style: 'currency', currency: 'USD' }).format(value);
        if (format === 'date') return new Intl.DateTimeFormat(lng).format(new Date(value));
        if (format === 'datetime') return new Intl.DateTimeFormat(lng, { 
          year: 'numeric', month: 'short', day: 'numeric', 
          hour: 'numeric', minute: 'numeric' 
        }).format(new Date(value));
        return value;
      }
    },
    
    backend: {
      loadPath: '/api/i18n/{{lng}}/{{ns}}',
      addPath: '/api/i18n/add/{{lng}}/{{ns}}',
    },
    
    detection: {
      order: ['localStorage', 'sessionStorage', 'navigator', 'htmlTag'],
      caches: ['localStorage', 'sessionStorage'],
      lookupLocalStorage: 'i18nextLng',
      lookupSessionStorage: 'i18nextLng',
    },
    
    ns: ['common', 'navigation', 'course', 'user', 'admin'],
    defaultNS: 'common',
    
    react: {
      useSuspense: false,
      bindI18n: 'languageChanged',
      bindI18nStore: 'added removed',
      transEmptyNodeValue: '',
      transSupportBasicHtmlNodes: true,
      transKeepBasicHtmlNodesFor: ['br', 'strong', 'i', 'em', 'span'],
    },
  });

export interface I18nConfig {
  locale: string;
  direction: 'ltr' | 'rtl';
  translations: Record<string, string>;
}

export interface UseI18nReturn {
  t: (key: string, options?: any) => string;
  i18n: typeof i18n;
  locale: string;
  setLocale: (locale: string) => Promise<void>;
  isRtl: boolean;
  direction: 'ltr' | 'rtl';
  formatNumber: (value: number, options?: Intl.NumberFormatOptions) => string;
  formatCurrency: (value: number, currency?: string) => string;
  formatDate: (value: Date | string | number, options?: Intl.DateTimeFormatOptions) => string;
  formatDateTime: (value: Date | string | number, options?: Intl.DateTimeFormatOptions) => string;
  formatRelativeTime: (value: Date | string | number) => string;
  pluralize: (key: string, count: number, options?: any) => string;
  isLoading: boolean;
  error: string | null;
}

/**
 * Enhanced i18n hook with Moodle-compatible features
 */
export const useI18n = (): UseI18nReturn => {
  const { t, i18n: reactI18n } = useReactI18n();
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const locale = reactI18n.language || 'en';
  const isRtl = ['ar', 'he', 'fa', 'ur', 'ku', 'dv', 'yi'].includes(locale.split('-')[0]);
  const direction = isRtl ? 'rtl' : 'ltr';

  // Set locale and handle loading states
  const setLocale = async (newLocale: string): Promise<void> => {
    if (newLocale === locale) return;

    setIsLoading(true);
    setError(null);

    try {
      await reactI18n.changeLanguage(newLocale);
      
      // Update document attributes
      document.documentElement.lang = newLocale;
      document.documentElement.dir = ['ar', 'he', 'fa', 'ur', 'ku', 'dv', 'yi'].includes(newLocale.split('-')[0]) ? 'rtl' : 'ltr';
      
      // Update body class for styling
      document.body.className = document.body.className.replace(/\b(ltr|rtl)\b/g, '');
      document.body.classList.add(document.documentElement.dir);
      
      // Store preference
      localStorage.setItem('i18nextLng', newLocale);
      
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to change language');
    } finally {
      setIsLoading(false);
    }
  };

  // Format numbers according to locale
  const formatNumber = (value: number, options: Intl.NumberFormatOptions = {}): string => {
    return new Intl.NumberFormat(locale, options).format(value);
  };

  // Format currency according to locale
  const formatCurrency = (value: number, currency = 'USD'): string => {
    return new Intl.NumberFormat(locale, {
      style: 'currency',
      currency: currency,
    }).format(value);
  };

  // Format date according to locale
  const formatDate = (value: Date | string | number, options: Intl.DateTimeFormatOptions = {}): string => {
    const date = new Date(value);
    return new Intl.DateTimeFormat(locale, {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      ...options,
    }).format(date);
  };

  // Format date and time according to locale
  const formatDateTime = (value: Date | string | number, options: Intl.DateTimeFormatOptions = {}): string => {
    const date = new Date(value);
    return new Intl.DateTimeFormat(locale, {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: 'numeric',
      minute: 'numeric',
      ...options,
    }).format(date);
  };

  // Format relative time (e.g., "2 hours ago")
  const formatRelativeTime = (value: Date | string | number): string => {
    const date = new Date(value);
    const now = new Date();
    const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);

    const formatter = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });

    if (Math.abs(diffInSeconds) < 60) {
      return formatter.format(-diffInSeconds, 'second');
    }

    const diffInMinutes = Math.floor(diffInSeconds / 60);
    if (Math.abs(diffInMinutes) < 60) {
      return formatter.format(-diffInMinutes, 'minute');
    }

    const diffInHours = Math.floor(diffInMinutes / 60);
    if (Math.abs(diffInHours) < 24) {
      return formatter.format(-diffInHours, 'hour');
    }

    const diffInDays = Math.floor(diffInHours / 24);
    if (Math.abs(diffInDays) < 30) {
      return formatter.format(-diffInDays, 'day');
    }

    const diffInMonths = Math.floor(diffInDays / 30);
    if (Math.abs(diffInMonths) < 12) {
      return formatter.format(-diffInMonths, 'month');
    }

    const diffInYears = Math.floor(diffInMonths / 12);
    return formatter.format(-diffInYears, 'year');
  };

  // Handle pluralization with count
  const pluralize = (key: string, count: number, options: any = {}): string => {
    return t(key, { count, ...options });
  };

  // Enhanced translation function with fallbacks
  const translate = (key: string, options: any = {}): string => {
    const translation = t(key, options);
    
    // If translation is the same as the key, it might be missing
    if (translation === key && process.env.NODE_ENV === 'development') {
      console.warn(`Missing translation for key: ${key} (locale: ${locale})`);
    }
    
    return translation;
  };

  // Initialize document attributes on mount
  useEffect(() => {
    document.documentElement.lang = locale;
    document.documentElement.dir = direction;
    document.body.classList.remove('ltr', 'rtl');
    document.body.classList.add(direction);
  }, [locale, direction]);

  // Handle i18n events
  useEffect(() => {
    const handleLanguageChanged = () => {
      setIsLoading(false);
      setError(null);
    };

    const handleFailedLoading = (lng: string, ns: string, msg: string) => {
      setError(`Failed to load ${ns} for ${lng}: ${msg}`);
      setIsLoading(false);
    };

    reactI18n.on('languageChanged', handleLanguageChanged);
    reactI18n.on('failedLoading', handleFailedLoading);

    return () => {
      reactI18n.off('languageChanged', handleLanguageChanged);
      reactI18n.off('failedLoading', handleFailedLoading);
    };
  }, [reactI18n]);

  return {
    t: translate,
    i18n: reactI18n,
    locale,
    setLocale,
    isRtl,
    direction,
    formatNumber,
    formatCurrency,
    formatDate,
    formatDateTime,
    formatRelativeTime,
    pluralize,
    isLoading,
    error,
  };
};

/**
 * Hook for loading plugin-specific translations
 */
export const usePluginI18n = (pluginType: string, pluginName: string) => {
  const { t, locale, isLoading: baseLoading, error: baseError } = useI18n();
  const [pluginLoading, setPluginLoading] = useState(false);
  const [pluginError, setPluginError] = useState<string | null>(null);

  const loadPluginTranslations = async () => {
    if (pluginLoading) return;

    setPluginLoading(true);
    setPluginError(null);

    try {
      const response = await fetch(`/api/i18n/plugin/${pluginType}/${pluginName}/${locale}`);
      if (!response.ok) {
        throw new Error(`Failed to load plugin translations: ${response.statusText}`);
      }

      const translations = await response.json();
      
      // Add translations to i18n instance
      Object.entries(translations).forEach(([key, value]) => {
        i18n.addResource(locale, `${pluginType}_${pluginName}`, key, value);
      });

    } catch (err) {
      setPluginError(err instanceof Error ? err.message : 'Failed to load plugin translations');
    } finally {
      setPluginLoading(false);
    }
  };

  // Load plugin translations when hook is used
  useEffect(() => {
    loadPluginTranslations();
  }, [pluginType, pluginName, locale]);

  const tp = (key: string, options: any = {}) => {
    return t(`${pluginType}_${pluginName}:${key}`, options);
  };

  return {
    t: tp,
    isLoading: baseLoading || pluginLoading,
    error: baseError || pluginError,
    reload: loadPluginTranslations,
  };
};

export default useI18n;
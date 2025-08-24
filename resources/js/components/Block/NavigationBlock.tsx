import React, { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { ChevronDownIcon, ChevronRightIcon } from '@heroicons/react/24/outline';

interface NavigationItem {
  label: string;
  url: string;
  children?: NavigationItem[];
  icon?: string;
  active?: boolean;
}

interface NavigationSection {
  title: string;
  items: NavigationItem[];
  collapsible?: boolean;
  defaultExpanded?: boolean;
}

interface NavigationBlockProps {
  navigation: {
    site?: Record<string, string>;
    course?: Record<string, string>;
    sections?: Record<string, string>;
  };
  config?: {
    show_site_nav?: boolean;
    show_course_nav?: boolean;
    show_sections?: boolean;
    max_sections?: number;
    collapsible?: boolean;
  };
  className?: string;
  ariaLabel?: string;
}

export const NavigationBlock: React.FC<NavigationBlockProps> = ({
  navigation = {},
  config = {
    show_site_nav: true,
    show_course_nav: true,
    show_sections: true,
    max_sections: 10,
    collapsible: true,
  },
  className = '',
  ariaLabel,
}) => {
  const { t } = useTranslation();
  const [expandedSections, setExpandedSections] = useState<Set<string>>(
    new Set(['site', 'course'])
  );

  const toggleSection = (sectionId: string) => {
    if (!config.collapsible) return;
    
    const newExpanded = new Set(expandedSections);
    if (newExpanded.has(sectionId)) {
      newExpanded.delete(sectionId);
    } else {
      newExpanded.add(sectionId);
    }
    setExpandedSections(newExpanded);
  };

  const buildNavigationSections = (): NavigationSection[] => {
    const sections: NavigationSection[] = [];

    // Site navigation
    if (config.show_site_nav && navigation.site) {
      sections.push({
        title: t('navigation.site'),
        items: Object.entries(navigation.site).map(([label, url]) => ({
          label,
          url,
          active: window.location.pathname === url,
        })),
        collapsible: config.collapsible,
        defaultExpanded: true,
      });
    }

    // Course navigation
    if (config.show_course_nav && navigation.course) {
      sections.push({
        title: t('navigation.course'),
        items: Object.entries(navigation.course).map(([label, url]) => ({
          label,
          url,
          active: window.location.pathname === url,
        })),
        collapsible: config.collapsible,
        defaultExpanded: true,
      });
    }

    // Course sections
    if (config.show_sections && navigation.sections) {
      const sectionEntries = Object.entries(navigation.sections);
      const limitedSections = config.max_sections 
        ? sectionEntries.slice(0, config.max_sections)
        : sectionEntries;

      sections.push({
        title: t('navigation.sections'),
        items: limitedSections.map(([label, url]) => ({
          label,
          url,
          active: window.location.pathname === url,
        })),
        collapsible: config.collapsible,
        defaultExpanded: false,
      });
    }

    return sections;
  };

  const renderNavigationItem = (item: NavigationItem, index: number) => (
    <li key={index} className="nav-item">
      <a
        href={item.url}
        className={`nav-link block px-3 py-2 text-sm rounded-md transition-colors duration-200 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 ${
          item.active 
            ? 'bg-blue-50 dark:bg-blue-900 text-blue-700 dark:text-blue-200 font-medium' 
            : 'text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100'
        }`}
        aria-current={item.active ? 'page' : undefined}
        tabIndex={0}
      >
        {item.icon && (
          <span className="inline-block w-4 h-4 mr-2" aria-hidden="true">
            <i className={item.icon}></i>
          </span>
        )}
        {item.label}
      </a>
    </li>
  );

  const renderNavigationSection = (section: NavigationSection, index: number) => {
    const sectionId = `section-${index}`;
    const isExpanded = expandedSections.has(sectionId);
    const headingId = `heading-${sectionId}`;
    const contentId = `content-${sectionId}`;

    return (
      <div key={index} className="nav-section mb-4">
        <div className="flex items-center justify-between mb-2">
          {section.collapsible ? (
            <button
              onClick={() => toggleSection(sectionId)}
              className="flex items-center w-full text-left text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide hover:text-gray-800 dark:hover:text-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 rounded-sm px-1 py-1"
              aria-expanded={isExpanded}
              aria-controls={contentId}
              id={headingId}
            >
              {isExpanded ? (
                <ChevronDownIcon className="w-4 h-4 mr-1" aria-hidden="true" />
              ) : (
                <ChevronRightIcon className="w-4 h-4 mr-1" aria-hidden="true" />
              )}
              {section.title}
            </button>
          ) : (
            <h3 
              className="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide px-1"
              id={headingId}
            >
              {section.title}
            </h3>
          )}
        </div>

        <div
          id={contentId}
          role={section.collapsible ? 'region' : undefined}
          aria-labelledby={headingId}
          className={`nav-section-content ${
            section.collapsible ? (isExpanded ? 'block' : 'hidden') : 'block'
          }`}
        >
          <ul className="nav-list space-y-1" role="list">
            {section.items.map((item, itemIndex) => 
              renderNavigationItem(item, itemIndex)
            )}
          </ul>
        </div>
      </div>
    );
  };

  const sections = buildNavigationSections();

  if (sections.length === 0) {
    return (
      <div 
        className={`navigation-block p-4 ${className}`}
        role="navigation"
        aria-label={ariaLabel || t('navigation.label')}
      >
        <p className="text-sm text-gray-500 dark:text-gray-400">
          {t('navigation.no_items')}
        </p>
      </div>
    );
  }

  return (
    <nav 
      className={`navigation-block bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 ${className}`}
      role="navigation"
      aria-label={ariaLabel || t('navigation.label')}
    >
      <div className="space-y-4">
        {sections.map((section, index) => 
          renderNavigationSection(section, index)
        )}
      </div>
    </nav>
  );
};

export default NavigationBlock;
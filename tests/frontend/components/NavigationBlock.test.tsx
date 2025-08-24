import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { axe, toHaveNoViolations } from 'jest-axe';
import { NavigationBlock } from '@components/Block/NavigationBlock';

// Extend Jest matchers
expect.extend(toHaveNoViolations);

// Mock react-i18next
jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string) => {
      const translations: Record<string, string> = {
        'navigation.site': 'Site',
        'navigation.course': 'Course',
        'navigation.sections': 'Course Content',
        'navigation.label': 'Site navigation',
        'navigation.no_items': 'No navigation items available',
      };
      return translations[key] || key;
    },
  }),
}));

// Mock Heroicons
jest.mock('@heroicons/react/24/outline', () => ({
  ChevronDownIcon: ({ className }: { className: string }) => 
    <div className={className} data-testid="chevron-down">▼</div>,
  ChevronRightIcon: ({ className }: { className: string }) => 
    <div className={className} data-testid="chevron-right">▶</div>,
}));

describe('NavigationBlock', () => {
  const mockNavigation = {
    site: {
      'Dashboard': '/dashboard',
      'Courses': '/courses',
      'Users': '/users',
    },
    course: {
      'Course Home': '/course/1',
      'Participants': '/course/1/participants',
      'Grades': '/course/1/grades',
    },
    sections: {
      'Topic 1': '/course/1/section/1',
      'Topic 2': '/course/1/section/2',
      'Topic 3': '/course/1/section/3',
    },
  };

  const defaultConfig = {
    show_site_nav: true,
    show_course_nav: true,
    show_sections: true,
    max_sections: 10,
    collapsible: true,
  };

  beforeEach(() => {
    // Mock window.location.pathname
    Object.defineProperty(window, 'location', {
      value: { pathname: '/dashboard' },
      writable: true,
    });
  });

  describe('Rendering', () => {
    it('renders navigation block with default props', () => {
      render(<NavigationBlock />);
      
      expect(screen.getByRole('navigation')).toBeInTheDocument();
      expect(screen.getByLabelText('Site navigation')).toBeInTheDocument();
    });

    it('displays all navigation sections when configured', () => {
      render(
        <NavigationBlock 
          navigation={mockNavigation} 
          config={defaultConfig} 
        />
      );

      expect(screen.getByText('Site')).toBeInTheDocument();
      expect(screen.getByText('Course')).toBeInTheDocument();
      expect(screen.getByText('Course Content')).toBeInTheDocument();
    });

    it('renders navigation items as links', () => {
      render(
        <NavigationBlock 
          navigation={mockNavigation} 
          config={defaultConfig} 
        />
      );

      expect(screen.getByRole('link', { name: 'Dashboard' })).toHaveAttribute('href', '/dashboard');
      expect(screen.getByRole('link', { name: 'Courses' })).toHaveAttribute('href', '/courses');
      expect(screen.getByRole('link', { name: 'Course Home' })).toHaveAttribute('href', '/course/1');
    });

    it('marks active navigation items correctly', () => {
      window.location.pathname = '/dashboard';
      
      render(
        <NavigationBlock 
          navigation={mockNavigation} 
          config={defaultConfig} 
        />
      );

      const activeLink = screen.getByRole('link', { name: 'Dashboard' });
      expect(activeLink).toHaveAttribute('aria-current', 'page');
      expect(activeLink).toHaveClass('bg-blue-50');
    });

    it('shows empty state when no navigation items', () => {
      render(<NavigationBlock navigation={{}} />);
      
      expect(screen.getByText('No navigation items available')).toBeInTheDocument();
    });

    it('applies custom className and ariaLabel', () => {
      const { container } = render(
        <NavigationBlock 
          className="custom-nav-class"
          ariaLabel="Custom navigation label"
        />
      );

      expect(container.firstChild).toHaveClass('custom-nav-class');
      expect(screen.getByLabelText('Custom navigation label')).toBeInTheDocument();
    });
  });

  describe('Configuration Options', () => {
    it('hides site navigation when show_site_nav is false', () => {
      render(
        <NavigationBlock 
          navigation={mockNavigation}
          config={{ ...defaultConfig, show_site_nav: false }}
        />
      );

      expect(screen.queryByText('Site')).not.toBeInTheDocument();
      expect(screen.queryByRole('link', { name: 'Dashboard' })).not.toBeInTheDocument();
    });

    it('hides course navigation when show_course_nav is false', () => {
      render(
        <NavigationBlock 
          navigation={mockNavigation}
          config={{ ...defaultConfig, show_course_nav: false }}
        />
      );

      expect(screen.queryByText('Course')).not.toBeInTheDocument();
      expect(screen.queryByRole('link', { name: 'Course Home' })).not.toBeInTheDocument();
    });

    it('limits sections according to max_sections config', () => {
      render(
        <NavigationBlock 
          navigation={mockNavigation}
          config={{ ...defaultConfig, max_sections: 2 }}
        />
      );

      expect(screen.getByRole('link', { name: 'Topic 1' })).toBeInTheDocument();
      expect(screen.getByRole('link', { name: 'Topic 2' })).toBeInTheDocument();
      expect(screen.queryByRole('link', { name: 'Topic 3' })).not.toBeInTheDocument();
    });

    it('renders non-collapsible sections when collapsible is false', () => {
      render(
        <NavigationBlock 
          navigation={mockNavigation}
          config={{ ...defaultConfig, collapsible: false }}
        />
      );

      // Should not have collapse buttons
      expect(screen.queryByTestId('chevron-down')).not.toBeInTheDocument();
      expect(screen.queryByTestId('chevron-right')).not.toBeInTheDocument();
      
      // All sections should be visible
      expect(screen.getByRole('link', { name: 'Dashboard' })).toBeVisible();
      expect(screen.getByRole('link', { name: 'Course Home' })).toBeVisible();
    });
  });

  describe('Collapsible Functionality', () => {
    it('toggles section visibility when clicking collapse button', async () => {
      const user = userEvent.setup();
      
      render(
        <NavigationBlock 
          navigation={mockNavigation}
          config={defaultConfig}
        />
      );

      // Find the course section toggle button
      const courseToggle = screen.getByRole('button', { name: /course/i });
      
      // Initially expanded - links should be visible
      expect(screen.getByRole('link', { name: 'Course Home' })).toBeVisible();
      
      // Click to collapse
      await user.click(courseToggle);
      
      // Links should be hidden
      expect(screen.getByRole('link', { name: 'Course Home' })).not.toBeVisible();
      expect(courseToggle).toHaveAttribute('aria-expanded', 'false');
      
      // Click to expand again
      await user.click(courseToggle);
      
      // Links should be visible again
      expect(screen.getByRole('link', { name: 'Course Home' })).toBeVisible();
      expect(courseToggle).toHaveAttribute('aria-expanded', 'true');
    });

    it('shows correct chevron icons based on expansion state', async () => {
      const user = userEvent.setup();
      
      render(
        <NavigationBlock 
          navigation={mockNavigation}
          config={defaultConfig}
        />
      );

      const courseToggle = screen.getByRole('button', { name: /course/i });
      
      // Initially expanded - should show down chevron
      expect(screen.getByTestId('chevron-down')).toBeInTheDocument();
      
      // Click to collapse
      await user.click(courseToggle);
      
      // Should show right chevron
      expect(screen.getByTestId('chevron-right')).toBeInTheDocument();
    });

    it('maintains proper ARIA attributes for collapsible sections', () => {
      render(
        <NavigationBlock 
          navigation={mockNavigation}
          config={defaultConfig}
        />
      );

      const courseToggle = screen.getByRole('button', { name: /course/i });
      const courseContent = screen.getByRole('region');
      
      expect(courseToggle).toHaveAttribute('aria-expanded', 'true');
      expect(courseToggle).toHaveAttribute('aria-controls', courseContent.id);
      expect(courseContent).toHaveAttribute('aria-labelledby', courseToggle.id);
    });
  });

  describe('Keyboard Navigation', () => {
    it('supports keyboard navigation for links', async () => {
      const user = userEvent.setup();
      
      render(
        <NavigationBlock 
          navigation={mockNavigation}
          config={defaultConfig}
        />
      );

      const firstLink = screen.getByRole('link', { name: 'Dashboard' });
      const secondLink = screen.getByRole('link', { name: 'Courses' });
      
      // Tab to first link
      await user.tab();
      expect(firstLink).toHaveFocus();
      
      // Tab to second link
      await user.tab();
      expect(secondLink).toHaveFocus();
    });

    it('supports keyboard navigation for collapse buttons', async () => {
      const user = userEvent.setup();
      
      render(
        <NavigationBlock 
          navigation={mockNavigation}
          config={defaultConfig}
        />
      );

      const courseToggle = screen.getByRole('button', { name: /course/i });
      
      // Focus on the button
      courseToggle.focus();
      expect(courseToggle).toHaveFocus();
      
      // Press Enter to toggle
      await user.keyboard('{Enter}');
      
      expect(courseToggle).toHaveAttribute('aria-expanded', 'false');
    });

    it('handles Space key for toggle buttons', async () => {
      const user = userEvent.setup();
      
      render(
        <NavigationBlock 
          navigation={mockNavigation}
          config={defaultConfig}
        />
      );

      const courseToggle = screen.getByRole('button', { name: /course/i });
      courseToggle.focus();
      
      // Press Space to toggle
      await user.keyboard(' ');
      
      expect(courseToggle).toHaveAttribute('aria-expanded', 'false');
    });
  });

  describe('Accessibility', () => {
    it('has no accessibility violations', async () => {
      const { container } = render(
        <NavigationBlock 
          navigation={mockNavigation}
          config={defaultConfig}
        />
      );

      const results = await axe(container);
      expect(results).toHaveNoViolations();
    });

    it('provides proper ARIA labels and roles', () => {
      render(
        <NavigationBlock 
          navigation={mockNavigation}
          config={defaultConfig}
        />
      );

      expect(screen.getByRole('navigation')).toHaveAttribute('aria-label', 'Site navigation');
      
      const lists = screen.getAllByRole('list');
      expect(lists).toHaveLength(3); // One for each section
      
      const links = screen.getAllByRole('link');
      expect(links.length).toBeGreaterThan(0);
      links.forEach(link => {
        expect(link).toHaveAttribute('href');
      });
    });

    it('maintains focus management during collapse/expand', async () => {
      const user = userEvent.setup();
      
      render(
        <NavigationBlock 
          navigation={mockNavigation}
          config={defaultConfig}
        />
      );

      const courseToggle = screen.getByRole('button', { name: /course/i });
      
      // Focus and toggle
      await user.click(courseToggle);
      
      // Button should still have focus
      expect(courseToggle).toHaveFocus();
    });

    it('supports screen reader announcements', () => {
      render(
        <NavigationBlock 
          navigation={mockNavigation}
          config={defaultConfig}
        />
      );

      // Check for live regions and proper labeling
      const navigation = screen.getByRole('navigation');
      expect(navigation).toBeInTheDocument();
      
      // Active links should be announced
      const activeLink = screen.getByRole('link', { name: 'Dashboard' });
      expect(activeLink).toHaveAttribute('aria-current', 'page');
    });
  });

  describe('Responsive Design', () => {
    it('applies responsive classes correctly', () => {
      const { container } = render(
        <NavigationBlock 
          navigation={mockNavigation}
          config={defaultConfig}
        />
      );

      const navigationBlock = container.querySelector('.navigation-block');
      expect(navigationBlock).toHaveClass('rounded-lg', 'shadow-sm', 'border');
    });

    it('supports dark mode classes', () => {
      render(
        <NavigationBlock 
          navigation={mockNavigation}
          config={defaultConfig}
        />
      );

      const links = screen.getAllByRole('link');
      links.forEach(link => {
        expect(link).toHaveClass('dark:text-gray-300', 'dark:hover:text-gray-100');
      });
    });
  });

  describe('Error Handling', () => {
    it('handles missing navigation data gracefully', () => {
      render(<NavigationBlock navigation={undefined as any} />);
      
      expect(screen.getByText('No navigation items available')).toBeInTheDocument();
    });

    it('handles invalid URLs gracefully', () => {
      const invalidNavigation = {
        site: {
          'Invalid Link': '',
          'Another Link': 'javascript:alert("test")', // Should be sanitized
        },
      };
      
      render(<NavigationBlock navigation={invalidNavigation} />);
      
      const links = screen.getAllByRole('link');
      expect(links[0]).toHaveAttribute('href', '');
      // The second link should be sanitized - implementation dependent
    });

    it('handles missing translation gracefully', () => {
      // Mock missing translation
      jest.mocked(require('react-i18next').useTranslation).mockReturnValue({
        t: (key: string) => `Missing: ${key}`,
      });
      
      render(<NavigationBlock navigation={mockNavigation} />);
      
      // Should still render even with missing translations
      expect(screen.getByRole('navigation')).toBeInTheDocument();
    });
  });

  describe('Performance', () => {
    it('renders efficiently with large navigation sets', () => {
      // Create a large navigation set
      const largeNavigation = {
        sections: Object.fromEntries(
          Array.from({ length: 100 }, (_, i) => [`Topic ${i + 1}`, `/topic/${i + 1}`])
        ),
      };
      
      const startTime = performance.now();
      render(
        <NavigationBlock 
          navigation={largeNavigation} 
          config={{ ...defaultConfig, max_sections: 50 }}
        />
      );
      const endTime = performance.now();
      
      // Should render within reasonable time (adjust threshold as needed)
      expect(endTime - startTime).toBeLessThan(100);
      
      // Should respect max_sections limit
      const links = screen.getAllByRole('link');
      expect(links).toHaveLength(50);
    });

    it('memoizes section calculations', () => {
      const { rerender } = render(
        <NavigationBlock 
          navigation={mockNavigation}
          config={defaultConfig}
        />
      );

      // Re-render with same props
      rerender(
        <NavigationBlock 
          navigation={mockNavigation}
          config={defaultConfig}
        />
      );

      // Navigation should still work correctly
      expect(screen.getAllByRole('link')).toHaveLength(9); // 3 site + 3 course + 3 sections
    });
  });
});
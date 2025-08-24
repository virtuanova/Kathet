<?php

namespace App\Core\Plugin\Contracts;

/**
 * Interface for Moodle themes
 */
interface ThemeInterface extends PluginInterface
{
    /**
     * Get theme layouts
     */
    public function getLayouts(): array;

    /**
     * Get theme configuration
     */
    public function getThemeConfig(): array;

    /**
     * Get CSS files for theme
     */
    public function getCssFiles(): array;

    /**
     * Get JavaScript files for theme
     */
    public function getJsFiles(): array;

    /**
     * Get theme templates
     */
    public function getTemplates(): array;

    /**
     * Get theme colors/variables
     */
    public function getColors(): array;

    /**
     * Get theme fonts
     */
    public function getFonts(): array;

    /**
     * Get responsive breakpoints
     */
    public function getBreakpoints(): array;

    /**
     * Check if theme supports dark mode
     */
    public function supportsDarkMode(): bool;

    /**
     * Get theme settings schema
     */
    public function getSettingsSchema(): array;

    /**
     * Compile theme assets
     */
    public function compileAssets(): bool;

    /**
     * Get theme preview image
     */
    public function getPreviewImage(): string;

    /**
     * Get theme screenshots
     */
    public function getScreenshots(): array;

    /**
     * Get supported page types
     */
    public function getSupportedPageTypes(): array;

    /**
     * Get theme regions (for blocks)
     */
    public function getRegions(): array;

    /**
     * Get default block regions
     */
    public function getDefaultRegions(): array;

    /**
     * Get theme parent (if inheriting)
     */
    public function getParent(): ?string;

    /**
     * Get theme dependencies
     */
    public function getThemeDependencies(): array;

    /**
     * Get renderers
     */
    public function getRenderers(): array;

    /**
     * Render layout
     */
    public function renderLayout(string $layout, array $data): string;

    /**
     * Render for React/Vue (JSON data)
     */
    public function getReactThemeData(): array;

    /**
     * Convert Mustache templates to Blade
     */
    public function convertTemplatesToBlade(): array;

    /**
     * Convert SCSS to CSS with theme variables
     */
    public function convertScss(): string;

    /**
     * Get theme customization options
     */
    public function getCustomizationOptions(): array;

    /**
     * Apply customizations
     */
    public function applyCustomizations(array $customizations): bool;

    /**
     * Export theme as Blade/React compatible
     */
    public function exportForModernFramework(): array;

    /**
     * Get theme RTL support
     */
    public function supportsRtl(): bool;

    /**
     * Get accessibility features
     */
    public function getAccessibilityFeatures(): array;

    /**
     * Get theme performance metrics
     */
    public function getPerformanceMetrics(): array;

    /**
     * Validate theme integrity
     */
    public function validateTheme(): array;

    /**
     * Get theme migration status
     */
    public function getMigrationStatus(): array;
}
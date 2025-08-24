<?php

namespace App\Core\Plugin\Contracts;

/**
 * Interface for Moodle blocks
 */
interface BlockInterface extends PluginInterface
{
    /**
     * Get block content
     */
    public function getContent(array $config = []): array;

    /**
     * Get block title
     */
    public function getTitle(): string;

    /**
     * Check if block has configuration
     */
    public function hasConfig(): bool;

    /**
     * Get configuration form
     */
    public function getConfigForm(): ?string;

    /**
     * Process configuration form
     */
    public function processConfig(array $data): bool;

    /**
     * Get block configuration
     */
    public function getConfig(): array;

    /**
     * Set block configuration
     */
    public function setConfig(array $config): void;

    /**
     * Check if block is configurable per instance
     */
    public function isConfigurablePerInstance(): bool;

    /**
     * Get supported page types where block can be displayed
     */
    public function getSupportedPageTypes(): array;

    /**
     * Check if block can be docked
     */
    public function canDock(): bool;

    /**
     * Check if block supports multiple instances
     */
    public function supportsMultipleInstances(): bool;

    /**
     * Get block regions where this block can be placed
     */
    public function getSupportedRegions(): array;

    /**
     * Get default region for this block
     */
    public function getDefaultRegion(): string;

    /**
     * Get default weight for ordering
     */
    public function getDefaultWeight(): int;

    /**
     * Check if block should be shown on page
     */
    public function shouldShow(string $pageType, array $context): bool;

    /**
     * Get block HTML
     */
    public function getHtml(array $config = []): string;

    /**
     * Get block for React/Vue frontend
     */
    public function getReactComponent(): ?string;

    /**
     * Get block data for API
     */
    public function getApiData(array $config = []): array;

    /**
     * Handle AJAX requests
     */
    public function handleAjax(string $action, array $params): array;

    /**
     * Get required CSS files
     */
    public function getCssFiles(): array;

    /**
     * Get required JavaScript files
     */
    public function getJsFiles(): array;

    /**
     * Get block instance data
     */
    public function getInstanceData(int $instanceId): array;

    /**
     * Create new block instance
     */
    public function createInstance(array $data): int;

    /**
     * Update block instance
     */
    public function updateInstance(int $instanceId, array $data): bool;

    /**
     * Delete block instance
     */
    public function deleteInstance(int $instanceId): bool;

    /**
     * Get block capabilities required
     */
    public function getRequiredCapabilities(): array;

    /**
     * Check user permissions for block
     */
    public function checkPermissions(int $userId, string $action): bool;

    /**
     * Get caching settings
     */
    public function getCacheSettings(): array;

    /**
     * Get block accessibility information
     */
    public function getAccessibilityInfo(): array;
}
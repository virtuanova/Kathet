<?php

namespace App\Core\Plugin\Contracts;

/**
 * Base interface for all Moodle-compatible plugins
 */
interface PluginInterface
{
    /**
     * Initialize the plugin
     */
    public function init(): void;

    /**
     * Get plugin name
     */
    public function getName(): string;

    /**
     * Get plugin version
     */
    public function getVersion(): string;

    /**
     * Get plugin type
     */
    public function getType(): string;

    /**
     * Get plugin dependencies
     */
    public function getDependencies(): array;

    /**
     * Install the plugin
     */
    public function install(): bool;

    /**
     * Uninstall the plugin
     */
    public function uninstall(): bool;

    /**
     * Upgrade the plugin
     */
    public function upgrade(?string $oldVersion = null): bool;

    /**
     * Check if plugin is compatible with current LMS version
     */
    public function isCompatible(): bool;

    /**
     * Get plugin configuration schema
     */
    public function getConfigSchema(): array;

    /**
     * Get plugin settings
     */
    public function getSettings(): array;

    /**
     * Update plugin settings
     */
    public function updateSettings(array $settings): bool;
}
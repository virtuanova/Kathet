<?php

namespace App\Core\Plugin\Contracts;

/**
 * Interface for Moodle activity modules (mods)
 */
interface ModuleInterface extends PluginInterface
{
    /**
     * Get supported features of this module
     */
    public function getSupportedFeatures(): array;

    /**
     * Add instance of this module to course
     */
    public function addInstance(array $data): int;

    /**
     * Update instance of this module
     */
    public function updateInstance(int $instanceId, array $data): bool;

    /**
     * Delete instance of this module
     */
    public function deleteInstance(int $instanceId): bool;

    /**
     * Get instance data
     */
    public function getInstance(int $instanceId): ?array;

    /**
     * Get view for displaying the module
     */
    public function getView(int $instanceId, int $userId): string;

    /**
     * Get edit form for the module
     */
    public function getEditForm(): string;

    /**
     * Process form submission
     */
    public function processForm(array $data): bool;

    /**
     * Get grading information
     */
    public function getGradingInfo(int $instanceId): array;

    /**
     * Update grades for users
     */
    public function updateGrades(int $instanceId, array $grades): bool;

    /**
     * Get completion state
     */
    public function getCompletionState(int $instanceId, int $userId): int;

    /**
     * Get backup data
     */
    public function getBackupData(int $instanceId): array;

    /**
     * Restore from backup data
     */
    public function restoreFromBackup(array $data): int;

    /**
     * Get module icon
     */
    public function getIcon(): string;

    /**
     * Get module description
     */
    public function getDescription(): string;

    /**
     * Get module capabilities
     */
    public function getCapabilities(): array;

    /**
     * Get database schema for module tables
     */
    public function getDatabaseSchema(): array;

    /**
     * Get navigation items for this module
     */
    public function getNavigationItems(int $instanceId): array;

    /**
     * Handle AJAX requests
     */
    public function handleAjax(string $action, array $params): array;

    /**
     * Get RSS feed if supported
     */
    public function getRssFeed(int $instanceId): ?string;

    /**
     * Search within module content
     */
    public function search(string $query, int $courseId = null): array;

    /**
     * Get file areas used by this module
     */
    public function getFileAreas(): array;

    /**
     * Process uploaded files
     */
    public function processFiles(int $instanceId, array $files): bool;
}
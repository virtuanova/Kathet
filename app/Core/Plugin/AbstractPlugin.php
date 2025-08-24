<?php

namespace App\Core\Plugin;

use App\Core\Plugin\Contracts\PluginInterface;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * Abstract base class for Moodle plugins
 */
abstract class AbstractPlugin implements PluginInterface
{
    protected string $pluginPath;
    protected PluginManager $pluginManager;
    protected array $pluginInfo;
    protected array $config;

    public function __construct(string $pluginPath, PluginManager $pluginManager)
    {
        $this->pluginPath = $pluginPath;
        $this->pluginManager = $pluginManager;
        $this->loadPluginInfo();
        $this->loadConfig();
    }

    /**
     * Initialize the plugin
     */
    public function init(): void
    {
        // Register database tables if needed
        $this->registerDatabaseTables();
        
        // Register capabilities
        $this->registerCapabilities();
        
        // Register language strings
        $this->registerLanguageStrings();
        
        // Call child initialization
        $this->initPlugin();
    }

    /**
     * Child classes can override this for specific initialization
     */
    protected function initPlugin(): void
    {
        // Override in child classes
    }

    /**
     * Get plugin name
     */
    public function getName(): string
    {
        return $this->pluginInfo['component'] ?? basename($this->pluginPath);
    }

    /**
     * Get plugin version
     */
    public function getVersion(): string
    {
        return (string) ($this->pluginInfo['version'] ?? '1.0.0');
    }

    /**
     * Get plugin type
     */
    public function getType(): string
    {
        return explode('_', $this->getName())[0];
    }

    /**
     * Get plugin dependencies
     */
    public function getDependencies(): array
    {
        return $this->pluginInfo['dependencies'] ?? [];
    }

    /**
     * Install the plugin
     */
    public function install(): bool
    {
        try {
            // Create database tables
            $this->createDatabaseTables();
            
            // Insert default data
            $this->insertDefaultData();
            
            // Run install script
            $this->runInstallScript();
            
            // Mark as installed
            $this->markAsInstalled();
            
            return true;
        } catch (\Exception $e) {
            \Log::error("Plugin installation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Uninstall the plugin
     */
    public function uninstall(): bool
    {
        try {
            // Run uninstall script
            $this->runUninstallScript();
            
            // Drop database tables
            $this->dropDatabaseTables();
            
            // Clean up files
            $this->cleanupFiles();
            
            // Mark as uninstalled
            $this->markAsUninstalled();
            
            return true;
        } catch (\Exception $e) {
            \Log::error("Plugin uninstallation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Upgrade the plugin
     */
    public function upgrade(?string $oldVersion = null): bool
    {
        try {
            $currentVersion = $this->getInstalledVersion();
            $newVersion = $this->getVersion();
            
            if (version_compare($newVersion, $currentVersion, '<=')) {
                return true; // No upgrade needed
            }
            
            // Run upgrade scripts
            $this->runUpgradeScript($currentVersion, $newVersion);
            
            // Update version
            $this->updateInstalledVersion($newVersion);
            
            return true;
        } catch (\Exception $e) {
            \Log::error("Plugin upgrade failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if plugin is compatible with current LMS version
     */
    public function isCompatible(): bool
    {
        $requires = $this->pluginInfo['requires'] ?? null;
        
        if (!$requires) {
            return true;
        }
        
        $currentVersion = config('app.moodle_version', '2023100900');
        
        return version_compare($currentVersion, $requires, '>=');
    }

    /**
     * Get plugin configuration schema
     */
    public function getConfigSchema(): array
    {
        $schemaFile = $this->pluginPath . '/config_schema.php';
        
        if (File::exists($schemaFile)) {
            return include $schemaFile;
        }
        
        return [];
    }

    /**
     * Get plugin settings
     */
    public function getSettings(): array
    {
        return $this->config;
    }

    /**
     * Update plugin settings
     */
    public function updateSettings(array $settings): bool
    {
        try {
            foreach ($settings as $key => $value) {
                DB::table('mdl_config_plugins')
                    ->updateOrInsert(
                        ['plugin' => $this->getName(), 'name' => $key],
                        ['value' => $value]
                    );
            }
            
            $this->config = array_merge($this->config, $settings);
            
            // Clear cache
            Cache::forget("plugin_config_{$this->getName()}");
            
            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to update plugin settings: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Load plugin info from version.php
     */
    protected function loadPluginInfo(): void
    {
        $versionFile = $this->pluginPath . '/version.php';
        
        if (File::exists($versionFile)) {
            $plugin = [];
            include $versionFile;
            $this->pluginInfo = $plugin;
        } else {
            $this->pluginInfo = [];
        }
    }

    /**
     * Load plugin configuration
     */
    protected function loadConfig(): void
    {
        $cacheKey = "plugin_config_{$this->getName()}";
        
        $this->config = Cache::remember($cacheKey, 3600, function () {
            return DB::table('mdl_config_plugins')
                ->where('plugin', $this->getName())
                ->pluck('value', 'name')
                ->toArray();
        });
    }

    /**
     * Register database tables
     */
    protected function registerDatabaseTables(): void
    {
        // Override in child classes if needed
    }

    /**
     * Create database tables
     */
    protected function createDatabaseTables(): void
    {
        $installFile = $this->pluginPath . '/db/install.xml';
        
        if (File::exists($installFile)) {
            $this->executeXmlDbInstall($installFile);
        }
    }

    /**
     * Drop database tables
     */
    protected function dropDatabaseTables(): void
    {
        $uninstallFile = $this->pluginPath . '/db/uninstall.php';
        
        if (File::exists($uninstallFile)) {
            include $uninstallFile;
            
            $uninstallFunction = "xmldb_{$this->getName()}_uninstall";
            if (function_exists($uninstallFunction)) {
                $uninstallFunction();
            }
        }
    }

    /**
     * Execute XML database installation
     */
    protected function executeXmlDbInstall(string $xmlFile): void
    {
        // Parse and execute Moodle's XML DB format
        // This would need a full XML DB parser implementation
        // For now, we'll look for a PHP equivalent
        
        $phpInstallFile = dirname($xmlFile) . '/install.php';
        if (File::exists($phpInstallFile)) {
            include $phpInstallFile;
            
            $installFunction = "xmldb_{$this->getName()}_install";
            if (function_exists($installFunction)) {
                $installFunction();
            }
        }
    }

    /**
     * Insert default data
     */
    protected function insertDefaultData(): void
    {
        $dataFile = $this->pluginPath . '/db/install.php';
        
        if (File::exists($dataFile)) {
            include $dataFile;
        }
    }

    /**
     * Run install script
     */
    protected function runInstallScript(): void
    {
        // Override in child classes
    }

    /**
     * Run uninstall script
     */
    protected function runUninstallScript(): void
    {
        // Override in child classes
    }

    /**
     * Run upgrade script
     */
    protected function runUpgradeScript(string $oldVersion, string $newVersion): void
    {
        $upgradeFile = $this->pluginPath . '/db/upgrade.php';
        
        if (File::exists($upgradeFile)) {
            include $upgradeFile;
            
            $upgradeFunction = "xmldb_{$this->getName()}_upgrade";
            if (function_exists($upgradeFunction)) {
                $upgradeFunction($oldVersion);
            }
        }
    }

    /**
     * Register capabilities
     */
    protected function registerCapabilities(): void
    {
        $capabilitiesFile = $this->pluginPath . '/db/access.php';
        
        if (File::exists($capabilitiesFile)) {
            $capabilities = [];
            include $capabilitiesFile;
            
            // Register capabilities in the system
            foreach ($capabilities as $capability => $definition) {
                $this->registerCapability($capability, $definition);
            }
        }
    }

    /**
     * Register a single capability
     */
    protected function registerCapability(string $capability, array $definition): void
    {
        DB::table('mdl_capabilities')->updateOrInsert(
            ['name' => $capability],
            [
                'captype' => $definition['captype'] ?? 'write',
                'contextlevel' => $definition['contextlevel'] ?? 50, // CONTEXT_COURSE
                'component' => $this->getName(),
                'riskbitmask' => $definition['riskbitmask'] ?? 0,
            ]
        );
    }

    /**
     * Register language strings
     */
    protected function registerLanguageStrings(): void
    {
        $langPath = $this->pluginPath . '/lang';
        
        if (File::exists($langPath)) {
            // Register language files with Laravel's translator
            app('translator')->addNamespace($this->getName(), $langPath);
        }
    }

    /**
     * Get installed version
     */
    protected function getInstalledVersion(): string
    {
        return DB::table('mdl_config_plugins')
            ->where('plugin', $this->getName())
            ->where('name', 'version')
            ->value('value') ?? '0';
    }

    /**
     * Update installed version
     */
    protected function updateInstalledVersion(string $version): void
    {
        DB::table('mdl_config_plugins')->updateOrInsert(
            ['plugin' => $this->getName(), 'name' => 'version'],
            ['value' => $version]
        );
    }

    /**
     * Mark plugin as installed
     */
    protected function markAsInstalled(): void
    {
        $this->updateInstalledVersion($this->getVersion());
    }

    /**
     * Mark plugin as uninstalled
     */
    protected function markAsUninstalled(): void
    {
        DB::table('mdl_config_plugins')
            ->where('plugin', $this->getName())
            ->delete();
    }

    /**
     * Clean up plugin files
     */
    protected function cleanupFiles(): void
    {
        // Remove uploaded files, caches, etc.
        $pluginDataPath = storage_path("app/plugins/{$this->getName()}");
        if (File::exists($pluginDataPath)) {
            File::deleteDirectory($pluginDataPath);
        }
    }

    /**
     * Get plugin path
     */
    public function getPluginPath(): string
    {
        return $this->pluginPath;
    }

    /**
     * Get relative URL for plugin assets
     */
    public function getAssetUrl(string $path = ''): string
    {
        $pluginName = basename($this->pluginPath);
        $pluginType = basename(dirname($this->pluginPath));
        
        return url("plugins/{$pluginType}/{$pluginName}/{$path}");
    }

    /**
     * Load a template/view file
     */
    public function loadTemplate(string $templateName, array $data = []): string
    {
        $templatePath = $this->pluginPath . "/templates/{$templateName}.mustache";
        
        if (File::exists($templatePath)) {
            $content = File::get($templatePath);
            
            // Basic Mustache template rendering
            foreach ($data as $key => $value) {
                $content = str_replace("{{$key}}", $value, $content);
            }
            
            return $content;
        }
        
        throw new \Exception("Template {$templateName} not found in plugin");
    }

    /**
     * Get language string
     */
    public function getString(string $key, array $params = []): string
    {
        $langKey = "{$this->getName()}::{$key}";
        
        if (app('translator')->has($langKey)) {
            return trans($langKey, $params);
        }
        
        return $key; // Fallback to key if translation not found
    }

    /**
     * Log plugin activity
     */
    protected function log(string $message, string $level = 'info'): void
    {
        \Log::{$level}("[Plugin {$this->getName()}] {$message}");
    }
}
<?php

namespace App\Core\Plugin;

use App\Core\Plugin\Contracts\PluginInterface;
use App\Core\Plugin\Contracts\ModuleInterface;
use App\Core\Plugin\Contracts\BlockInterface;
use App\Core\Plugin\Contracts\ThemeInterface;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

/**
 * Moodle Plugin Manager
 * Handles loading and management of Moodle-compatible plugins (mods, blocks, themes)
 */
class PluginManager
{
    protected string $pluginsPath;
    protected Collection $loadedPlugins;
    protected Collection $enabledPlugins;
    protected array $pluginTypes = [
        'mod' => ModuleInterface::class,
        'block' => BlockInterface::class,
        'theme' => ThemeInterface::class,
    ];

    public function __construct()
    {
        $this->pluginsPath = base_path('plugins');
        $this->loadedPlugins = collect();
        $this->enabledPlugins = collect();
        $this->ensurePluginDirectories();
    }

    /**
     * Load all enabled plugins
     */
    public function loadPlugins(): void
    {
        $enabledPlugins = $this->getEnabledPlugins();

        foreach ($enabledPlugins as $plugin) {
            try {
                $this->loadPlugin($plugin['type'], $plugin['name']);
            } catch (\Exception $e) {
                \Log::error("Failed to load plugin {$plugin['type']}/{$plugin['name']}: " . $e->getMessage());
            }
        }
    }

    /**
     * Load a specific plugin
     */
    public function loadPlugin(string $type, string $name): PluginInterface
    {
        $cacheKey = "plugin.{$type}.{$name}";
        
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        $pluginPath = $this->getPluginPath($type, $name);
        $pluginClass = $this->getPluginClass($type, $name);

        if (!class_exists($pluginClass)) {
            $this->loadPluginFiles($pluginPath);
        }

        if (!class_exists($pluginClass)) {
            throw new \Exception("Plugin class {$pluginClass} not found");
        }

        $plugin = new $pluginClass($pluginPath, $this);
        
        if (!$plugin instanceof $this->pluginTypes[$type]) {
            throw new \Exception("Plugin must implement {$this->pluginTypes[$type]}");
        }

        // Initialize plugin
        $plugin->init();
        
        // Register plugin routes, views, etc.
        $this->registerPlugin($plugin);
        
        $this->loadedPlugins->put("{$type}/{$name}", $plugin);
        Cache::put($cacheKey, $plugin, 3600);

        return $plugin;
    }

    /**
     * Get plugin information from version.php (Moodle standard)
     */
    public function getPluginInfo(string $type, string $name): array
    {
        $versionFile = $this->getPluginPath($type, $name) . '/version.php';
        
        if (!File::exists($versionFile)) {
            throw new \Exception("Plugin version file not found: {$versionFile}");
        }

        // Safely extract plugin info from Moodle's version.php
        $plugin = [];
        $component = '';
        $version = 0;
        $release = '';
        $maturity = '';
        $dependencies = [];

        // Include the version file in isolated scope
        include $versionFile;

        return [
            'component' => $plugin['component'] ?? $component ?? "{$type}_{$name}",
            'version' => $plugin['version'] ?? $version,
            'release' => $plugin['release'] ?? $release,
            'maturity' => $plugin['maturity'] ?? $maturity,
            'dependencies' => $plugin['dependencies'] ?? $dependencies,
            'requires' => $plugin['requires'] ?? null,
        ];
    }

    /**
     * Install a plugin from Moodle plugin directory structure
     */
    public function installPlugin(string $sourcePath, string $type, string $name): bool
    {
        $targetPath = $this->getPluginPath($type, $name);

        if (File::exists($targetPath)) {
            throw new \Exception("Plugin {$type}/{$name} already exists");
        }

        // Copy plugin files
        File::copyDirectory($sourcePath, $targetPath);

        // Validate plugin structure
        if (!$this->validatePluginStructure($type, $name)) {
            File::deleteDirectory($targetPath);
            throw new \Exception("Invalid plugin structure");
        }

        // Run plugin installation if install.php exists
        $this->runPluginInstaller($type, $name);

        // Add to enabled plugins
        $this->enablePlugin($type, $name);

        return true;
    }

    /**
     * Enable a plugin
     */
    public function enablePlugin(string $type, string $name): void
    {
        $enabled = $this->getEnabledPlugins();
        
        if (!$enabled->contains(function ($plugin) use ($type, $name) {
            return $plugin['type'] === $type && $plugin['name'] === $name;
        })) {
            $enabled->push(['type' => $type, 'name' => $name]);
            $this->saveEnabledPlugins($enabled);
        }
    }

    /**
     * Disable a plugin
     */
    public function disablePlugin(string $type, string $name): void
    {
        $enabled = $this->getEnabledPlugins();
        $filtered = $enabled->reject(function ($plugin) use ($type, $name) {
            return $plugin['type'] === $type && $plugin['name'] === $name;
        });
        
        $this->saveEnabledPlugins($filtered);
        
        // Remove from loaded plugins
        $this->loadedPlugins->forget("{$type}/{$name}");
        
        // Clear cache
        Cache::forget("plugin.{$type}.{$name}");
    }

    /**
     * Get all available plugins
     */
    public function getAvailablePlugins(): Collection
    {
        $plugins = collect();

        foreach (array_keys($this->pluginTypes) as $type) {
            $typePath = $this->pluginsPath . "/{$type}";
            
            if (File::exists($typePath)) {
                $pluginDirs = File::directories($typePath);
                
                foreach ($pluginDirs as $pluginDir) {
                    $name = basename($pluginDir);
                    try {
                        $info = $this->getPluginInfo($type, $name);
                        $plugins->push([
                            'type' => $type,
                            'name' => $name,
                            'info' => $info,
                            'path' => $pluginDir,
                            'enabled' => $this->isPluginEnabled($type, $name),
                            'loaded' => $this->isPluginLoaded($type, $name),
                        ]);
                    } catch (\Exception $e) {
                        // Skip invalid plugins
                        continue;
                    }
                }
            }
        }

        return $plugins;
    }

    /**
     * Get loaded plugin
     */
    public function getPlugin(string $type, string $name): ?PluginInterface
    {
        return $this->loadedPlugins->get("{$type}/{$name}");
    }

    /**
     * Check if plugin is enabled
     */
    public function isPluginEnabled(string $type, string $name): bool
    {
        return $this->getEnabledPlugins()->contains(function ($plugin) use ($type, $name) {
            return $plugin['type'] === $type && $plugin['name'] === $name;
        });
    }

    /**
     * Check if plugin is loaded
     */
    public function isPluginLoaded(string $type, string $name): bool
    {
        return $this->loadedPlugins->has("{$type}/{$name}");
    }

    /**
     * Register plugin with Laravel
     */
    protected function registerPlugin(PluginInterface $plugin): void
    {
        // Register routes
        if (method_exists($plugin, 'registerRoutes')) {
            $plugin->registerRoutes();
        }

        // Register views
        if (method_exists($plugin, 'getViewPaths')) {
            foreach ($plugin->getViewPaths() as $namespace => $path) {
                view()->addNamespace($namespace, $path);
            }
        }

        // Register translations
        if (method_exists($plugin, 'getTranslationPaths')) {
            foreach ($plugin->getTranslationPaths() as $namespace => $path) {
                app('translator')->addNamespace($namespace, $path);
            }
        }

        // Register assets
        if (method_exists($plugin, 'getAssetPaths')) {
            // Assets will be handled by the asset pipeline
        }
    }

    /**
     * Load plugin PHP files
     */
    protected function loadPluginFiles(string $pluginPath): void
    {
        // Load lib.php (main plugin functions)
        $libFile = $pluginPath . '/lib.php';
        if (File::exists($libFile)) {
            require_once $libFile;
        }

        // Load classes
        $classesPath = $pluginPath . '/classes';
        if (File::exists($classesPath)) {
            $classFiles = File::allFiles($classesPath);
            foreach ($classFiles as $file) {
                if ($file->getExtension() === 'php') {
                    require_once $file->getPathname();
                }
            }
        }
    }

    /**
     * Get plugin class name
     */
    protected function getPluginClass(string $type, string $name): string
    {
        return "\\{$type}_{$name}\\PluginClass";
    }

    /**
     * Get plugin path
     */
    protected function getPluginPath(string $type, string $name): string
    {
        return $this->pluginsPath . "/{$type}/{$name}";
    }

    /**
     * Validate plugin directory structure
     */
    protected function validatePluginStructure(string $type, string $name): bool
    {
        $pluginPath = $this->getPluginPath($type, $name);
        
        // Check required files
        $requiredFiles = ['version.php'];
        
        foreach ($requiredFiles as $file) {
            if (!File::exists($pluginPath . '/' . $file)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Run plugin installer
     */
    protected function runPluginInstaller(string $type, string $name): void
    {
        $installFile = $this->getPluginPath($type, $name) . '/db/install.php';
        
        if (File::exists($installFile)) {
            include $installFile;
            
            // Call plugin install function if exists
            $installFunction = "xmldb_{$type}_{$name}_install";
            if (function_exists($installFunction)) {
                $installFunction();
            }
        }
    }

    /**
     * Get enabled plugins from config
     */
    protected function getEnabledPlugins(): Collection
    {
        if ($this->enabledPlugins->isEmpty()) {
            $configFile = storage_path('app/plugins/enabled.json');
            
            if (File::exists($configFile)) {
                $data = json_decode(File::get($configFile), true);
                $this->enabledPlugins = collect($data);
            }
        }

        return $this->enabledPlugins;
    }

    /**
     * Save enabled plugins to config
     */
    protected function saveEnabledPlugins(Collection $plugins): void
    {
        $configFile = storage_path('app/plugins/enabled.json');
        $configDir = dirname($configFile);
        
        if (!File::exists($configDir)) {
            File::makeDirectory($configDir, 0755, true);
        }
        
        File::put($configFile, $plugins->toJson(JSON_PRETTY_PRINT));
        $this->enabledPlugins = $plugins;
    }

    /**
     * Ensure plugin directories exist
     */
    protected function ensurePluginDirectories(): void
    {
        if (!File::exists($this->pluginsPath)) {
            File::makeDirectory($this->pluginsPath, 0755, true);
        }

        foreach (array_keys($this->pluginTypes) as $type) {
            $typePath = $this->pluginsPath . "/{$type}";
            if (!File::exists($typePath)) {
                File::makeDirectory($typePath, 0755, true);
            }
        }
    }
}
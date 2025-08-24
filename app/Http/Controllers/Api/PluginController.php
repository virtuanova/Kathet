<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Core\Plugin\PluginManager;
use App\Core\Block\BlockManager;
use App\Core\Module\ModuleLoader;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;

/**
 * Plugin Management API Controller
 */
class PluginController extends Controller
{
    protected PluginManager $pluginManager;
    protected BlockManager $blockManager;
    protected ModuleLoader $moduleLoader;

    public function __construct(
        PluginManager $pluginManager,
        BlockManager $blockManager,
        ModuleLoader $moduleLoader
    ) {
        $this->pluginManager = $pluginManager;
        $this->blockManager = $blockManager;
        $this->moduleLoader = $moduleLoader;
    }

    /**
     * Get all available plugins
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $plugins = $this->pluginManager->getAvailablePlugins();

            // Filter by type if specified
            if ($request->has('type')) {
                $plugins = $plugins->where('type', $request->type);
            }

            // Filter by status if specified
            if ($request->has('status')) {
                $enabled = $request->status === 'enabled';
                $plugins = $plugins->where('enabled', $enabled);
            }

            return response()->json([
                'success' => true,
                'plugins' => $plugins->values(),
                'summary' => [
                    'total' => $plugins->count(),
                    'enabled' => $plugins->where('enabled', true)->count(),
                    'disabled' => $plugins->where('enabled', false)->count(),
                    'types' => $plugins->groupBy('type')->map->count(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch plugins',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get plugin details
     */
    public function show(string $type, string $name): JsonResponse
    {
        try {
            $plugin = $this->pluginManager->getPlugin($type, $name);
            
            if (!$plugin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plugin not found'
                ], 404);
            }

            $pluginInfo = [
                'name' => $plugin->getName(),
                'type' => $plugin->getType(),
                'version' => $plugin->getVersion(),
                'dependencies' => $plugin->getDependencies(),
                'settings' => $plugin->getSettings(),
                'config_schema' => $plugin->getConfigSchema(),
                'compatible' => $plugin->isCompatible(),
                'enabled' => $this->pluginManager->isPluginEnabled($type, $name),
                'loaded' => $this->pluginManager->isPluginLoaded($type, $name),
            ];

            // Add type-specific information
            if ($type === 'mod' && method_exists($plugin, 'getSupportedFeatures')) {
                $pluginInfo['features'] = $plugin->getSupportedFeatures();
                $pluginInfo['capabilities'] = $plugin->getCapabilities();
            } elseif ($type === 'block' && method_exists($plugin, 'getSupportedPageTypes')) {
                $pluginInfo['page_types'] = $plugin->getSupportedPageTypes();
                $pluginInfo['regions'] = $plugin->getSupportedRegions();
                $pluginInfo['configurable'] = $plugin->hasConfig();
            }

            return response()->json([
                'success' => true,
                'plugin' => $pluginInfo
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get plugin details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enable a plugin
     */
    public function enable(string $type, string $name): JsonResponse
    {
        try {
            if ($this->pluginManager->isPluginEnabled($type, $name)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plugin is already enabled'
                ], 400);
            }

            $this->pluginManager->enablePlugin($type, $name);
            
            // Load the plugin if it's a critical type
            if (in_array($type, ['mod', 'block'])) {
                $this->pluginManager->loadPlugin($type, $name);
            }

            return response()->json([
                'success' => true,
                'message' => 'Plugin enabled successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to enable plugin',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Disable a plugin
     */
    public function disable(string $type, string $name): JsonResponse
    {
        try {
            if (!$this->pluginManager->isPluginEnabled($type, $name)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plugin is already disabled'
                ], 400);
            }

            $this->pluginManager->disablePlugin($type, $name);

            return response()->json([
                'success' => true,
                'message' => 'Plugin disabled successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to disable plugin',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Install a plugin from uploaded file
     */
    public function install(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'plugin_file' => 'required|file|mimes:zip',
            'type' => 'required|in:mod,block,theme',
            'force' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $uploadedFile = $request->file('plugin_file');
            $tempPath = storage_path('tmp/plugin_install');
            $extractPath = $tempPath . '/' . uniqid();

            // Create temp directory
            File::ensureDirectoryExists($extractPath);

            // Extract ZIP file
            $zip = new \ZipArchive;
            if ($zip->open($uploadedFile->getPathname()) === TRUE) {
                $zip->extractTo($extractPath);
                $zip->close();
            } else {
                throw new \Exception('Failed to extract plugin archive');
            }

            // Find plugin directory and determine name
            $pluginDirs = File::directories($extractPath);
            if (empty($pluginDirs)) {
                throw new \Exception('Invalid plugin archive structure');
            }

            $pluginPath = $pluginDirs[0];
            $pluginName = basename($pluginPath);

            // Validate plugin structure
            if (!File::exists($pluginPath . '/version.php')) {
                throw new \Exception('Invalid plugin: version.php not found');
            }

            // Install plugin
            $result = $this->pluginManager->installPlugin(
                $pluginPath,
                $request->type,
                $pluginName
            );

            // Cleanup temp files
            File::deleteDirectory($tempPath);

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => "Plugin {$pluginName} installed successfully",
                    'plugin' => [
                        'name' => $pluginName,
                        'type' => $request->type
                    ]
                ]);
            } else {
                throw new \Exception('Plugin installation failed');
            }

        } catch (\Exception $e) {
            // Cleanup temp files on error
            if (isset($tempPath) && File::exists($tempPath)) {
                File::deleteDirectory($tempPath);
            }

            return response()->json([
                'success' => false,
                'message' => 'Plugin installation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update plugin settings
     */
    public function updateSettings(Request $request, string $type, string $name): JsonResponse
    {
        try {
            $plugin = $this->pluginManager->getPlugin($type, $name);
            
            if (!$plugin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plugin not found'
                ], 404);
            }

            $settings = $request->input('settings', []);
            $result = $plugin->updateSettings($settings);

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Plugin settings updated successfully'
                ]);
            } else {
                throw new \Exception('Failed to update plugin settings');
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update plugin settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get plugin configuration form
     */
    public function getConfigForm(string $type, string $name): JsonResponse
    {
        try {
            $plugin = $this->pluginManager->getPlugin($type, $name);
            
            if (!$plugin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plugin not found'
                ], 404);
            }

            $schema = $plugin->getConfigSchema();
            $settings = $plugin->getSettings();

            return response()->json([
                'success' => true,
                'config_form' => [
                    'schema' => $schema,
                    'current_values' => $settings,
                    'plugin_name' => $plugin->getName(),
                    'plugin_type' => $plugin->getType(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get plugin configuration',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available blocks for a page
     */
    public function getAvailableBlocks(Request $request): JsonResponse
    {
        try {
            $pageType = $request->input('page_type', 'site-index');
            $context = $request->input('context', []);

            $blocks = $this->blockManager->getAvailableBlocks($pageType, $context);

            return response()->json([
                'success' => true,
                'blocks' => $blocks
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get available blocks',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get blocks for a page (for frontend)
     */
    public function getPageBlocks(Request $request): JsonResponse
    {
        try {
            $pageType = $request->input('page_type', 'site-index');
            $context = $request->input('context', []);

            $blocks = $this->blockManager->getBlocksForApi($pageType, $context);

            return response()->json([
                'success' => true,
                'blocks' => $blocks
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get page blocks',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add block to page
     */
    public function addBlock(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'block_name' => 'required|string',
            'page_type' => 'required|string',
            'region' => 'required|string',
            'config' => 'array',
            'context' => 'array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $instanceId = $this->blockManager->createBlockInstance(
                $request->block_name,
                $request->page_type,
                $request->region,
                $request->input('config', []),
                $request->input('context', [])
            );

            return response()->json([
                'success' => true,
                'message' => 'Block added successfully',
                'block_instance_id' => $instanceId
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add block',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available modules
     */
    public function getAvailableModules(): JsonResponse
    {
        try {
            $modules = $this->moduleLoader->getAvailableModules();

            return response()->json([
                'success' => true,
                'modules' => $modules
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get available modules',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get system status for plugins
     */
    public function getSystemStatus(): JsonResponse
    {
        try {
            $status = [
                'plugins_directory' => base_path('plugins'),
                'plugins_directory_writable' => is_writable(base_path('plugins')),
                'cache_directory' => storage_path('app/plugins'),
                'cache_directory_writable' => is_writable(storage_path('app/plugins')),
                'loaded_plugins' => [
                    'mods' => $this->pluginManager->getAvailablePlugins()->where('type', 'mod')->where('loaded', true)->count(),
                    'blocks' => $this->pluginManager->getAvailablePlugins()->where('type', 'block')->where('loaded', true)->count(),
                    'themes' => $this->pluginManager->getAvailablePlugins()->where('type', 'theme')->where('loaded', true)->count(),
                ],
                'database_tables' => [
                    'mdl_modules' => \Schema::hasTable('mdl_modules'),
                    'mdl_block_instances' => \Schema::hasTable('mdl_block_instances'),
                    'mdl_config_plugins' => \Schema::hasTable('mdl_config_plugins'),
                ],
                'php_extensions' => [
                    'zip' => extension_loaded('zip'),
                    'json' => extension_loaded('json'),
                    'mbstring' => extension_loaded('mbstring'),
                ],
            ];

            return response()->json([
                'success' => true,
                'status' => $status
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get system status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear plugin cache
     */
    public function clearCache(): JsonResponse
    {
        try {
            // Clear Laravel cache
            \Artisan::call('cache:clear');
            
            // Clear plugin-specific cache
            $cacheDir = storage_path('app/plugins/cache');
            if (File::exists($cacheDir)) {
                File::deleteDirectory($cacheDir);
            }

            return response()->json([
                'success' => true,
                'message' => 'Plugin cache cleared successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear plugin cache',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
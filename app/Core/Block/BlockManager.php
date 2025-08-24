<?php

namespace App\Core\Block;

use App\Core\Plugin\Contracts\BlockInterface;
use App\Core\Plugin\PluginManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

/**
 * Manages Moodle-compatible blocks system
 */
class BlockManager
{
    protected PluginManager $pluginManager;
    protected Collection $loadedBlocks;
    protected array $blockInstances = [];

    public function __construct(PluginManager $pluginManager)
    {
        $this->pluginManager = $pluginManager;
        $this->loadedBlocks = collect();
    }

    /**
     * Load blocks for a specific page/context
     */
    public function loadBlocksForPage(string $pageType, array $context = []): Collection
    {
        $cacheKey = "blocks_page_{$pageType}_" . md5(serialize($context));
        
        return Cache::remember($cacheKey, 300, function() use ($pageType, $context) {
            return $this->getPageBlocks($pageType, $context);
        });
    }

    /**
     * Get blocks for a specific page
     */
    protected function getPageBlocks(string $pageType, array $context): Collection
    {
        $blocks = collect();
        
        // Get block instances from database
        $blockInstances = $this->getBlockInstancesForPage($pageType, $context);
        
        foreach ($blockInstances as $instance) {
            try {
                $block = $this->loadBlock($instance['blockname'], $instance);
                if ($block && $block->shouldShow($pageType, $context)) {
                    $blocks->push([
                        'instance' => $instance,
                        'block' => $block,
                        'content' => $block->getContent($instance['config'] ?? []),
                        'html' => $block->getHtml($instance['config'] ?? []),
                    ]);
                }
            } catch (\Exception $e) {
                \Log::error("Failed to load block {$instance['blockname']}: " . $e->getMessage());
            }
        }
        
        return $blocks->sortBy('instance.weight');
    }

    /**
     * Get block instances for a page from database
     */
    protected function getBlockInstancesForPage(string $pageType, array $context): array
    {
        $contextId = $context['id'] ?? 1; // System context by default
        
        return DB::table('mdl_block_instances as bi')
            ->join('mdl_block_positions as bp', 'bi.id', '=', 'bp.blockinstanceid')
            ->where('bp.pagetype', $pageType)
            ->where('bp.contextid', $contextId)
            ->where('bi.visible', 1)
            ->select([
                'bi.id',
                'bi.blockname',
                'bi.configdata',
                'bp.region',
                'bp.weight',
                'bp.visible as position_visible'
            ])
            ->orderBy('bp.weight')
            ->get()
            ->map(function($item) {
                $item->config = $item->configdata ? unserialize($item->configdata) : [];
                return (array) $item;
            })
            ->toArray();
    }

    /**
     * Load a specific block
     */
    public function loadBlock(string $blockName, array $instanceData = []): ?BlockInterface
    {
        $cacheKey = "block_{$blockName}";
        
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        try {
            $block = $this->pluginManager->loadPlugin('block', $blockName);
            
            if (!$block instanceof BlockInterface) {
                throw new \Exception("Block {$blockName} does not implement BlockInterface");
            }

            // Set instance data if provided
            if (!empty($instanceData)) {
                $block->setConfig($instanceData['config'] ?? []);
            }

            Cache::put($cacheKey, $block, 3600);
            $this->loadedBlocks->put($blockName, $block);

            return $block;

        } catch (\Exception $e) {
            \Log::error("Failed to load block {$blockName}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Create a new block instance
     */
    public function createBlockInstance(string $blockName, string $pageType, string $region, array $config = [], array $context = []): int
    {
        $contextId = $context['id'] ?? 1;
        
        DB::beginTransaction();
        
        try {
            // Create block instance
            $instanceId = DB::table('mdl_block_instances')->insertGetId([
                'blockname' => $blockName,
                'parentcontextid' => $contextId,
                'showinsubcontexts' => 1,
                'pagetypepattern' => $pageType,
                'subpagepattern' => null,
                'defaultregion' => $region,
                'defaultweight' => $this->getNextWeight($pageType, $region, $contextId),
                'configdata' => serialize($config),
                'timecreated' => time(),
                'timemodified' => time(),
            ]);
            
            // Create block position
            DB::table('mdl_block_positions')->insert([
                'blockinstanceid' => $instanceId,
                'contextid' => $contextId,
                'pagetype' => $pageType,
                'subpage' => '',
                'visible' => 1,
                'region' => $region,
                'weight' => $this->getNextWeight($pageType, $region, $contextId),
            ]);
            
            DB::commit();
            
            // Clear cache
            $this->clearBlockCache($pageType, $contextId);
            
            return $instanceId;
            
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Update block instance
     */
    public function updateBlockInstance(int $instanceId, array $config): bool
    {
        try {
            DB::table('mdl_block_instances')
                ->where('id', $instanceId)
                ->update([
                    'configdata' => serialize($config),
                    'timemodified' => time(),
                ]);
            
            // Clear related caches
            $this->clearBlockCacheForInstance($instanceId);
            
            return true;
            
        } catch (\Exception $e) {
            \Log::error("Failed to update block instance {$instanceId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete block instance
     */
    public function deleteBlockInstance(int $instanceId): bool
    {
        DB::beginTransaction();
        
        try {
            // Get instance info for cache clearing
            $instance = DB::table('mdl_block_instances')->where('id', $instanceId)->first();
            
            // Delete positions
            DB::table('mdl_block_positions')->where('blockinstanceid', $instanceId)->delete();
            
            // Delete instance
            DB::table('mdl_block_instances')->where('id', $instanceId)->delete();
            
            DB::commit();
            
            // Clear caches
            if ($instance) {
                $this->clearBlockCacheForInstance($instanceId);
            }
            
            return true;
            
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error("Failed to delete block instance {$instanceId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Move block to different region or position
     */
    public function moveBlock(int $instanceId, string $region, int $weight, array $context = []): bool
    {
        $contextId = $context['id'] ?? 1;
        
        try {
            DB::table('mdl_block_positions')
                ->where('blockinstanceid', $instanceId)
                ->where('contextid', $contextId)
                ->update([
                    'region' => $region,
                    'weight' => $weight,
                ]);
            
            // Clear cache
            $this->clearBlockCacheForInstance($instanceId);
            
            return true;
            
        } catch (\Exception $e) {
            \Log::error("Failed to move block {$instanceId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Toggle block visibility
     */
    public function toggleBlockVisibility(int $instanceId, array $context = []): bool
    {
        $contextId = $context['id'] ?? 1;
        
        try {
            $currentVisibility = DB::table('mdl_block_positions')
                ->where('blockinstanceid', $instanceId)
                ->where('contextid', $contextId)
                ->value('visible');
            
            DB::table('mdl_block_positions')
                ->where('blockinstanceid', $instanceId)
                ->where('contextid', $contextId)
                ->update(['visible' => $currentVisibility ? 0 : 1]);
            
            $this->clearBlockCacheForInstance($instanceId);
            
            return true;
            
        } catch (\Exception $e) {
            \Log::error("Failed to toggle block visibility {$instanceId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get available blocks that can be added to a page
     */
    public function getAvailableBlocks(string $pageType, array $context = []): Collection
    {
        $availableBlocks = collect();
        
        // Get all block plugins
        $blockPlugins = $this->pluginManager->getAvailablePlugins()->where('type', 'block');
        
        foreach ($blockPlugins as $plugin) {
            if ($plugin['enabled']) {
                try {
                    $block = $this->loadBlock($plugin['name']);
                    
                    if ($block && in_array($pageType, $block->getSupportedPageTypes())) {
                        $availableBlocks->push([
                            'name' => $plugin['name'],
                            'title' => $block->getTitle(),
                            'description' => $block->getDescription() ?? '',
                            'multiple_instances' => $block->supportsMultipleInstances(),
                            'configurable' => $block->hasConfig(),
                        ]);
                    }
                } catch (\Exception $e) {
                    // Skip blocks that can't be loaded
                    continue;
                }
            }
        }
        
        return $availableBlocks->sortBy('title');
    }

    /**
     * Get blocks for React/Vue frontend (JSON format)
     */
    public function getBlocksForApi(string $pageType, array $context = []): array
    {
        $blocks = $this->loadBlocksForPage($pageType, $context);
        
        return $blocks->groupBy('instance.region')->map(function($regionBlocks, $region) {
            return [
                'region' => $region,
                'blocks' => $regionBlocks->map(function($blockData) {
                    $block = $blockData['block'];
                    $instance = $blockData['instance'];
                    
                    return [
                        'id' => $instance['id'],
                        'name' => $instance['blockname'],
                        'title' => $block->getTitle(),
                        'content' => $blockData['content'],
                        'component' => $block->getReactComponent(),
                        'data' => $block->getApiData($instance['config'] ?? []),
                        'configurable' => $block->hasConfig(),
                        'weight' => $instance['weight'],
                    ];
                })->toArray()
            ];
        })->values()->toArray();
    }

    /**
     * Render blocks for a region in Blade templates
     */
    public function renderBlocksForRegion(string $region, string $pageType, array $context = []): string
    {
        $blocks = $this->loadBlocksForPage($pageType, $context)
            ->where('instance.region', $region);
        
        $html = '';
        
        foreach ($blocks as $blockData) {
            $html .= "<div class=\"block block-{$blockData['instance']['blockname']}\" data-block-id=\"{$blockData['instance']['id']}\">";
            $html .= $blockData['html'];
            $html .= "</div>";
        }
        
        return $html;
    }

    /**
     * Get next weight for block positioning
     */
    protected function getNextWeight(string $pageType, string $region, int $contextId): int
    {
        return DB::table('mdl_block_positions')
            ->where('pagetype', $pageType)
            ->where('region', $region)
            ->where('contextid', $contextId)
            ->max('weight') + 1;
    }

    /**
     * Clear block cache for a page
     */
    protected function clearBlockCache(string $pageType, int $contextId): void
    {
        $pattern = "blocks_page_{$pageType}_*";
        // In a real implementation, you'd clear cache by pattern
        Cache::flush(); // For now, just flush all
    }

    /**
     * Clear cache for a specific block instance
     */
    protected function clearBlockCacheForInstance(int $instanceId): void
    {
        // Get all pages where this block might appear and clear their caches
        $positions = DB::table('mdl_block_positions')
            ->where('blockinstanceid', $instanceId)
            ->get();
        
        foreach ($positions as $position) {
            $this->clearBlockCache($position->pagetype, $position->contextid);
        }
    }

    /**
     * Install default Moodle blocks
     */
    public function installDefaultBlocks(): void
    {
        $defaultBlocks = [
            'navigation' => 'Navigation block',
            'settings' => 'Settings block',
            'course_list' => 'Course list block',
            'calendar_month' => 'Calendar block',
            'recent_activity' => 'Recent activity block',
            'online_users' => 'Online users block',
            'html' => 'HTML block',
        ];

        foreach ($defaultBlocks as $blockName => $description) {
            try {
                // Check if block is already installed
                if (!$this->pluginManager->isPluginEnabled('block', $blockName)) {
                    // Install block plugin if available
                    $this->installBlockPlugin($blockName);
                }
            } catch (\Exception $e) {
                \Log::info("Could not install default block {$blockName}: " . $e->getMessage());
            }
        }
    }

    /**
     * Install a block plugin
     */
    protected function installBlockPlugin(string $blockName): bool
    {
        // This would typically install from plugins directory
        // For now, just mark as enabled if it exists
        
        $pluginPath = base_path("plugins/block/{$blockName}");
        
        if (File::exists($pluginPath)) {
            return $this->pluginManager->enablePlugin('block', $blockName);
        }
        
        return false;
    }

    /**
     * Export block configuration
     */
    public function exportBlockConfiguration(string $pageType, array $context = []): array
    {
        $contextId = $context['id'] ?? 1;
        
        $instances = DB::table('mdl_block_instances as bi')
            ->join('mdl_block_positions as bp', 'bi.id', '=', 'bp.blockinstanceid')
            ->where('bp.pagetype', $pageType)
            ->where('bp.contextid', $contextId)
            ->select([
                'bi.blockname',
                'bi.configdata',
                'bp.region',
                'bp.weight',
                'bp.visible'
            ])
            ->get()
            ->toArray();
        
        return [
            'page_type' => $pageType,
            'context_id' => $contextId,
            'blocks' => $instances,
            'exported_at' => now()->toISOString(),
        ];
    }

    /**
     * Import block configuration
     */
    public function importBlockConfiguration(array $config): bool
    {
        DB::beginTransaction();
        
        try {
            $pageType = $config['page_type'];
            $contextId = $config['context_id'];
            
            // Clear existing blocks for this page
            $existingInstances = DB::table('mdl_block_instances as bi')
                ->join('mdl_block_positions as bp', 'bi.id', '=', 'bp.blockinstanceid')
                ->where('bp.pagetype', $pageType)
                ->where('bp.contextid', $contextId)
                ->pluck('bi.id');
            
            foreach ($existingInstances as $instanceId) {
                $this->deleteBlockInstance($instanceId);
            }
            
            // Create new blocks from config
            foreach ($config['blocks'] as $blockConfig) {
                $this->createBlockInstance(
                    $blockConfig->blockname,
                    $pageType,
                    $blockConfig->region,
                    unserialize($blockConfig->configdata ?? ''),
                    ['id' => $contextId]
                );
            }
            
            DB::commit();
            return true;
            
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error("Failed to import block configuration: " . $e->getMessage());
            return false;
        }
    }
}
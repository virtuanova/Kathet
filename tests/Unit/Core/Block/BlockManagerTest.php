<?php

namespace Tests\Unit\Core\Block;

use Tests\TestCase;
use App\Core\Block\BlockManager;
use App\Core\Plugin\PluginManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

/**
 * Unit tests for BlockManager
 * 
 * Tests block management functionality including:
 * - Block loading and positioning
 * - Instance creation and management
 * - Cache management
 * - Region-based rendering
 * - API data generation
 */
class BlockManagerTest extends TestCase
{
    use RefreshDatabase;

    protected BlockManager $blockManager;
    protected $pluginManagerMock;
    protected $cacheMock;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->pluginManagerMock = Mockery::mock(PluginManager::class);
        $this->cacheMock = Mockery::mock('alias:Illuminate\Support\Facades\Cache');
        
        $this->blockManager = new BlockManager($this->pluginManagerMock);
    }

    public function testBlockManagerInitialization()
    {
        $this->assertInstanceOf(BlockManager::class, $this->blockManager);
    }

    public function testLoadBlocksForPageUsesCache()
    {
        $pageType = 'course-view';
        $context = ['id' => 1];
        $cacheKey = "blocks_page_{$pageType}_" . md5(serialize($context));
        
        $expectedBlocks = collect([
            [
                'instance' => ['id' => 1, 'blockname' => 'navigation'],
                'block' => Mockery::mock('App\Core\Plugin\Contracts\BlockInterface'),
                'content' => ['navigation' => []],
                'html' => '<div>Navigation</div>'
            ]
        ]);

        $this->cacheMock
            ->shouldReceive('remember')
            ->with($cacheKey, 300, \Mockery::type('callable'))
            ->once()
            ->andReturn($expectedBlocks);

        $blocks = $this->blockManager->loadBlocksForPage($pageType, $context);
        
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $blocks);
        $this->assertEquals($expectedBlocks, $blocks);
    }

    public function testGetPageBlocksRetrievesFromDatabase()
    {
        $pageType = 'course-view';
        $context = ['id' => 1];

        // Mock database query
        DB::shouldReceive('table')
            ->with('mdl_block_instances as bi')
            ->once()
            ->andReturnSelf();
            
        DB::shouldReceive('join')
            ->with('mdl_block_positions as bp', 'bi.id', '=', 'bp.blockinstanceid')
            ->once()
            ->andReturnSelf();
            
        DB::shouldReceive('where')
            ->with('bp.pagetype', $pageType)
            ->once()
            ->andReturnSelf();
            
        DB::shouldReceive('where')
            ->with('bp.contextid', 1)
            ->once()
            ->andReturnSelf();
            
        DB::shouldReceive('where')
            ->with('bi.visible', 1)
            ->once()
            ->andReturnSelf();
            
        DB::shouldReceive('select')
            ->once()
            ->andReturnSelf();
            
        DB::shouldReceive('orderBy')
            ->with('bp.weight')
            ->once()
            ->andReturnSelf();
            
        DB::shouldReceive('get')
            ->once()
            ->andReturn(collect([
                (object)[
                    'id' => 1,
                    'blockname' => 'navigation',
                    'configdata' => '',
                    'region' => 'side-pre',
                    'weight' => 0,
                    'position_visible' => 1
                ]
            ]));

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->blockManager);
        $method = $reflection->getMethod('getPageBlocks');
        $method->setAccessible(true);

        $blocks = $method->invoke($this->blockManager, $pageType, $context);
        
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $blocks);
    }

    public function testLoadBlockReturnsBlockInstance()
    {
        $blockName = 'navigation';
        $instanceData = ['config' => ['show_sections' => true]];
        
        $mockBlock = Mockery::mock('App\Core\Plugin\Contracts\BlockInterface');
        $mockBlock->shouldReceive('setConfig')
            ->with($instanceData['config'])
            ->once();

        $this->cacheMock
            ->shouldReceive('get')
            ->with("block_{$blockName}")
            ->once()
            ->andReturn(null);

        $this->pluginManagerMock
            ->shouldReceive('loadPlugin')
            ->with('block', $blockName)
            ->once()
            ->andReturn($mockBlock);

        $this->cacheMock
            ->shouldReceive('put')
            ->with("block_{$blockName}", $mockBlock, 3600)
            ->once()
            ->andReturn(true);

        $block = $this->blockManager->loadBlock($blockName, $instanceData);
        
        $this->assertEquals($mockBlock, $block);
    }

    public function testCreateBlockInstanceInsertsToDatabase()
    {
        $blockName = 'navigation';
        $pageType = 'course-view';
        $region = 'side-pre';
        $config = ['show_sections' => true];
        $context = ['id' => 1];

        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('commit')->once();

        // Mock block instance insertion
        DB::shouldReceive('table')
            ->with('mdl_block_instances')
            ->once()
            ->andReturnSelf();
            
        DB::shouldReceive('insertGetId')
            ->withArgs(function($data) use ($blockName, $region, $config) {
                return $data['blockname'] === $blockName &&
                       $data['defaultregion'] === $region &&
                       $data['configdata'] === serialize($config);
            })
            ->once()
            ->andReturn(123);

        // Mock block position insertion
        DB::shouldReceive('table')
            ->with('mdl_block_positions')
            ->once()
            ->andReturnSelf();
            
        DB::shouldReceive('insert')
            ->withArgs(function($data) use ($pageType, $region) {
                return $data['blockinstanceid'] === 123 &&
                       $data['pagetype'] === $pageType &&
                       $data['region'] === $region;
            })
            ->once()
            ->andReturn(true);

        // Mock getNextWeight call
        DB::shouldReceive('table')
            ->with('mdl_block_positions')
            ->twice() // called twice - once for weight, once for insertion
            ->andReturnSelf();
            
        DB::shouldReceive('where')
            ->andReturnSelf();
            
        DB::shouldReceive('max')
            ->with('weight')
            ->once()
            ->andReturn(5);

        $instanceId = $this->blockManager->createBlockInstance($blockName, $pageType, $region, $config, $context);
        
        $this->assertEquals(123, $instanceId);
    }

    public function testUpdateBlockInstanceModifiesConfiguration()
    {
        $instanceId = 123;
        $config = ['show_sections' => false, 'max_sections' => 5];

        DB::shouldReceive('table')
            ->with('mdl_block_instances')
            ->once()
            ->andReturnSelf();
            
        DB::shouldReceive('where')
            ->with('id', $instanceId)
            ->once()
            ->andReturnSelf();
            
        DB::shouldReceive('update')
            ->withArgs(function($data) use ($config) {
                return $data['configdata'] === serialize($config) &&
                       isset($data['timemodified']);
            })
            ->once()
            ->andReturn(1);

        $result = $this->blockManager->updateBlockInstance($instanceId, $config);
        
        $this->assertTrue($result);
    }

    public function testDeleteBlockInstanceRemovesFromDatabase()
    {
        $instanceId = 123;

        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('commit')->once();

        // Mock getting instance info
        DB::shouldReceive('table')
            ->with('mdl_block_instances')
            ->once()
            ->andReturnSelf();
            
        DB::shouldReceive('where')
            ->with('id', $instanceId)
            ->once()
            ->andReturnSelf();
            
        DB::shouldReceive('first')
            ->once()
            ->andReturn((object)['id' => $instanceId, 'blockname' => 'navigation']);

        // Mock delete positions
        DB::shouldReceive('table')
            ->with('mdl_block_positions')
            ->once()
            ->andReturnSelf();
            
        DB::shouldReceive('where')
            ->with('blockinstanceid', $instanceId)
            ->once()
            ->andReturnSelf();
            
        DB::shouldReceive('delete')
            ->once()
            ->andReturn(1);

        // Mock delete instance
        DB::shouldReceive('table')
            ->with('mdl_block_instances')
            ->once()
            ->andReturnSelf();
            
        DB::shouldReceive('where')
            ->with('id', $instanceId)
            ->once()
            ->andReturnSelf();
            
        DB::shouldReceive('delete')
            ->once()
            ->andReturn(1);

        $result = $this->blockManager->deleteBlockInstance($instanceId);
        
        $this->assertTrue($result);
    }

    public function testMoveBlockUpdatesPosition()
    {
        $instanceId = 123;
        $region = 'side-post';
        $weight = 10;
        $context = ['id' => 1];

        DB::shouldReceive('table')
            ->with('mdl_block_positions')
            ->once()
            ->andReturnSelf();
            
        DB::shouldReceive('where')
            ->with('blockinstanceid', $instanceId)
            ->once()
            ->andReturnSelf();
            
        DB::shouldReceive('where')
            ->with('contextid', 1)
            ->once()
            ->andReturnSelf();
            
        DB::shouldReceive('update')
            ->withArgs(function($data) use ($region, $weight) {
                return $data['region'] === $region &&
                       $data['weight'] === $weight;
            })
            ->once()
            ->andReturn(1);

        $result = $this->blockManager->moveBlock($instanceId, $region, $weight, $context);
        
        $this->assertTrue($result);
    }

    public function testToggleBlockVisibilityChangesState()
    {
        $instanceId = 123;
        $context = ['id' => 1];

        // Mock getting current visibility
        DB::shouldReceive('table')
            ->with('mdl_block_positions')
            ->twice()
            ->andReturnSelf();
            
        DB::shouldReceive('where')
            ->with('blockinstanceid', $instanceId)
            ->twice()
            ->andReturnSelf();
            
        DB::shouldReceive('where')
            ->with('contextid', 1)
            ->twice()
            ->andReturnSelf();
            
        DB::shouldReceive('value')
            ->with('visible')
            ->once()
            ->andReturn(1);

        DB::shouldReceive('update')
            ->with(['visible' => 0])
            ->once()
            ->andReturn(1);

        $result = $this->blockManager->toggleBlockVisibility($instanceId, $context);
        
        $this->assertTrue($result);
    }

    public function testGetAvailableBlocksFiltersbyPageType()
    {
        $pageType = 'course-view';
        $context = [];

        $mockPlugins = collect([
            ['name' => 'navigation', 'type' => 'block', 'enabled' => true],
            ['name' => 'settings', 'type' => 'block', 'enabled' => true],
            ['name' => 'disabled_block', 'type' => 'block', 'enabled' => false]
        ]);

        $this->pluginManagerMock
            ->shouldReceive('getAvailablePlugins')
            ->once()
            ->andReturn($mockPlugins);

        // Mock block loading for enabled blocks
        $mockNavigationBlock = Mockery::mock('App\Core\Plugin\Contracts\BlockInterface');
        $mockNavigationBlock->shouldReceive('getSupportedPageTypes')
            ->once()
            ->andReturn(['course-view', 'site-index']);
        $mockNavigationBlock->shouldReceive('getTitle')
            ->once()
            ->andReturn('Navigation');
        $mockNavigationBlock->shouldReceive('getDescription')
            ->once()
            ->andReturn('Site navigation');
        $mockNavigationBlock->shouldReceive('supportsMultipleInstances')
            ->once()
            ->andReturn(false);
        $mockNavigationBlock->shouldReceive('hasConfig')
            ->once()
            ->andReturn(true);

        $mockSettingsBlock = Mockery::mock('App\Core\Plugin\Contracts\BlockInterface');
        $mockSettingsBlock->shouldReceive('getSupportedPageTypes')
            ->once()
            ->andReturn(['admin-*']);

        $this->pluginManagerMock
            ->shouldReceive('loadPlugin')
            ->with('block', 'navigation')
            ->once()
            ->andReturn($mockNavigationBlock);

        $this->pluginManagerMock
            ->shouldReceive('loadPlugin')
            ->with('block', 'settings')
            ->once()
            ->andReturn($mockSettingsBlock);

        $availableBlocks = $this->blockManager->getAvailableBlocks($pageType, $context);
        
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $availableBlocks);
        $this->assertEquals(1, $availableBlocks->count()); // Only navigation should match
        
        $firstBlock = $availableBlocks->first();
        $this->assertEquals('navigation', $firstBlock['name']);
        $this->assertEquals('Navigation', $firstBlock['title']);
    }

    public function testGetBlocksForApiGeneratesJsonStructure()
    {
        $pageType = 'course-view';
        $context = ['id' => 1];

        $mockBlocks = collect([
            [
                'instance' => [
                    'id' => 1,
                    'blockname' => 'navigation',
                    'region' => 'side-pre',
                    'weight' => 0,
                    'config' => ['show_sections' => true]
                ],
                'block' => Mockery::mock('App\Core\Plugin\Contracts\BlockInterface'),
                'content' => ['navigation' => []],
                'html' => '<div>Navigation</div>'
            ]
        ]);

        $mockBlocks->first()['block']
            ->shouldReceive('getTitle')
            ->once()
            ->andReturn('Navigation');

        $mockBlocks->first()['block']
            ->shouldReceive('getReactComponent')
            ->once()
            ->andReturn('NavigationBlock');

        $mockBlocks->first()['block']
            ->shouldReceive('getApiData')
            ->with(['show_sections' => true])
            ->once()
            ->andReturn(['navigation' => [], 'config' => ['show_sections' => true]]);

        $mockBlocks->first()['block']
            ->shouldReceive('hasConfig')
            ->once()
            ->andReturn(true);

        // Mock the loadBlocksForPage call
        $this->cacheMock
            ->shouldReceive('remember')
            ->once()
            ->andReturn($mockBlocks);

        $apiBlocks = $this->blockManager->getBlocksForApi($pageType, $context);
        
        $this->assertIsArray($apiBlocks);
        $this->assertCount(1, $apiBlocks); // One region
        
        $region = $apiBlocks[0];
        $this->assertEquals('side-pre', $region['region']);
        $this->assertArrayHasKey('blocks', $region);
        $this->assertCount(1, $region['blocks']);
        
        $block = $region['blocks'][0];
        $this->assertEquals(1, $block['id']);
        $this->assertEquals('navigation', $block['name']);
        $this->assertEquals('Navigation', $block['title']);
        $this->assertEquals('NavigationBlock', $block['component']);
    }

    public function testRenderBlocksForRegionGeneratesHtml()
    {
        $region = 'side-pre';
        $pageType = 'course-view';
        $context = ['id' => 1];

        $mockBlocks = collect([
            [
                'instance' => [
                    'id' => 1,
                    'blockname' => 'navigation',
                    'region' => 'side-pre'
                ],
                'html' => '<div class="navigation-content">Navigation</div>'
            ]
        ]);

        // Mock the loadBlocksForPage call
        $this->cacheMock
            ->shouldReceive('remember')
            ->once()
            ->andReturn($mockBlocks);

        $html = $this->blockManager->renderBlocksForRegion($region, $pageType, $context);
        
        $this->assertIsString($html);
        $this->assertStringContains('block block-navigation', $html);
        $this->assertStringContains('data-block-id="1"', $html);
        $this->assertStringContains('Navigation', $html);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
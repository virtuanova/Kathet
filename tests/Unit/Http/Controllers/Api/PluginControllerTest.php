<?php

namespace Tests\Unit\Http\Controllers\Api;

use Tests\TestCase;
use App\Http\Controllers\Api\PluginController;
use App\Core\Plugin\PluginManager;
use App\Core\Block\BlockManager;
use App\Core\Module\ModuleLoader;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

/**
 * Unit tests for PluginController API
 * 
 * Tests all API endpoints for plugin management including:
 * - Plugin listing and filtering
 * - Plugin enabling/disabling
 * - Plugin installation from ZIP files
 * - Settings management
 * - Block management
 * - Module management
 * - System status reporting
 */
class PluginControllerTest extends TestCase
{
    use RefreshDatabase;

    protected PluginController $controller;
    protected $pluginManagerMock;
    protected $blockManagerMock;
    protected $moduleLoaderMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pluginManagerMock = Mockery::mock(PluginManager::class);
        $this->blockManagerMock = Mockery::mock(BlockManager::class);
        $this->moduleLoaderMock = Mockery::mock(ModuleLoader::class);

        $this->controller = new PluginController(
            $this->pluginManagerMock,
            $this->blockManagerMock,
            $this->moduleLoaderMock
        );
    }

    public function testIndexReturnsAllPlugins()
    {
        $mockPlugins = collect([
            ['name' => 'quiz', 'type' => 'mod', 'enabled' => true],
            ['name' => 'navigation', 'type' => 'block', 'enabled' => true],
            ['name' => 'boost', 'type' => 'theme', 'enabled' => false],
        ]);

        $this->pluginManagerMock
            ->shouldReceive('getAvailablePlugins')
            ->once()
            ->andReturn($mockPlugins);

        $request = new Request();
        $response = $this->controller->index($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertCount(3, $data['plugins']);
        $this->assertEquals(3, $data['summary']['total']);
        $this->assertEquals(2, $data['summary']['enabled']);
        $this->assertEquals(1, $data['summary']['disabled']);
    }

    public function testIndexFiltersPluginsByType()
    {
        $mockPlugins = collect([
            ['name' => 'quiz', 'type' => 'mod', 'enabled' => true],
            ['name' => 'assignment', 'type' => 'mod', 'enabled' => false],
            ['name' => 'navigation', 'type' => 'block', 'enabled' => true],
        ]);

        $this->pluginManagerMock
            ->shouldReceive('getAvailablePlugins')
            ->once()
            ->andReturn($mockPlugins);

        $request = new Request(['type' => 'mod']);
        $response = $this->controller->index($request);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertCount(2, $data['plugins']);
        
        foreach ($data['plugins'] as $plugin) {
            $this->assertEquals('mod', $plugin['type']);
        }
    }

    public function testIndexFiltersPluginsByStatus()
    {
        $mockPlugins = collect([
            ['name' => 'quiz', 'type' => 'mod', 'enabled' => true],
            ['name' => 'assignment', 'type' => 'mod', 'enabled' => false],
            ['name' => 'navigation', 'type' => 'block', 'enabled' => true],
        ]);

        $this->pluginManagerMock
            ->shouldReceive('getAvailablePlugins')
            ->once()
            ->andReturn($mockPlugins);

        $request = new Request(['status' => 'enabled']);
        $response = $this->controller->index($request);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertCount(2, $data['plugins']);
        
        foreach ($data['plugins'] as $plugin) {
            $this->assertTrue($plugin['enabled']);
        }
    }

    public function testIndexHandlesExceptions()
    {
        $this->pluginManagerMock
            ->shouldReceive('getAvailablePlugins')
            ->once()
            ->andThrow(new \Exception('Test exception'));

        $request = new Request();
        $response = $this->controller->index($request);

        $this->assertEquals(500, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Failed to fetch plugins', $data['message']);
        $this->assertEquals('Test exception', $data['error']);
    }

    public function testShowReturnsPluginDetails()
    {
        $mockPlugin = Mockery::mock('App\Core\Plugin\Contracts\ModuleInterface');
        $mockPlugin->shouldReceive('getName')->andReturn('quiz');
        $mockPlugin->shouldReceive('getType')->andReturn('mod');
        $mockPlugin->shouldReceive('getVersion')->andReturn('2024012400');
        $mockPlugin->shouldReceive('getDependencies')->andReturn(['core' => '2024011500']);
        $mockPlugin->shouldReceive('getSettings')->andReturn(['attempts' => 3]);
        $mockPlugin->shouldReceive('getConfigSchema')->andReturn(['attempts' => ['type' => 'number']]);
        $mockPlugin->shouldReceive('isCompatible')->andReturn(true);
        $mockPlugin->shouldReceive('getSupportedFeatures')->andReturn(['grade' => true]);
        $mockPlugin->shouldReceive('getCapabilities')->andReturn(['mod/quiz:view' => []]);

        $this->pluginManagerMock
            ->shouldReceive('getPlugin')
            ->with('mod', 'quiz')
            ->once()
            ->andReturn($mockPlugin);

        $this->pluginManagerMock
            ->shouldReceive('isPluginEnabled')
            ->with('mod', 'quiz')
            ->once()
            ->andReturn(true);

        $this->pluginManagerMock
            ->shouldReceive('isPluginLoaded')
            ->with('mod', 'quiz')
            ->once()
            ->andReturn(true);

        $response = $this->controller->show('mod', 'quiz');

        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('quiz', $data['plugin']['name']);
        $this->assertEquals('mod', $data['plugin']['type']);
        $this->assertTrue($data['plugin']['enabled']);
        $this->assertTrue($data['plugin']['loaded']);
        $this->assertArrayHasKey('features', $data['plugin']);
        $this->assertArrayHasKey('capabilities', $data['plugin']);
    }

    public function testShowReturnsNotFoundForInvalidPlugin()
    {
        $this->pluginManagerMock
            ->shouldReceive('getPlugin')
            ->with('mod', 'nonexistent')
            ->once()
            ->andReturn(null);

        $response = $this->controller->show('mod', 'nonexistent');

        $this->assertEquals(404, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Plugin not found', $data['message']);
    }

    public function testEnablePluginSuccessfully()
    {
        $this->pluginManagerMock
            ->shouldReceive('isPluginEnabled')
            ->with('mod', 'quiz')
            ->once()
            ->andReturn(false);

        $this->pluginManagerMock
            ->shouldReceive('enablePlugin')
            ->with('mod', 'quiz')
            ->once()
            ->andReturn(true);

        $this->pluginManagerMock
            ->shouldReceive('loadPlugin')
            ->with('mod', 'quiz')
            ->once()
            ->andReturn(true);

        $response = $this->controller->enable('mod', 'quiz');

        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Plugin enabled successfully', $data['message']);
    }

    public function testEnablePluginFailsWhenAlreadyEnabled()
    {
        $this->pluginManagerMock
            ->shouldReceive('isPluginEnabled')
            ->with('mod', 'quiz')
            ->once()
            ->andReturn(true);

        $response = $this->controller->enable('mod', 'quiz');

        $this->assertEquals(400, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Plugin is already enabled', $data['message']);
    }

    public function testDisablePluginSuccessfully()
    {
        $this->pluginManagerMock
            ->shouldReceive('isPluginEnabled')
            ->with('mod', 'quiz')
            ->once()
            ->andReturn(true);

        $this->pluginManagerMock
            ->shouldReceive('disablePlugin')
            ->with('mod', 'quiz')
            ->once()
            ->andReturn(true);

        $response = $this->controller->disable('mod', 'quiz');

        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Plugin disabled successfully', $data['message']);
    }

    public function testInstallPluginWithValidFile()
    {
        // Mock uploaded file
        $uploadedFile = Mockery::mock(UploadedFile::class);
        $uploadedFile->shouldReceive('getPathname')->andReturn('/tmp/test.zip');

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('all')->andReturn([
            'plugin_file' => $uploadedFile,
            'type' => 'mod',
            'force' => false
        ]);
        $request->shouldReceive('file')
            ->with('plugin_file')
            ->andReturn($uploadedFile);
        $request->shouldReceive('input')
            ->with('type')
            ->andReturn('mod');

        // Mock validation
        $validator = Mockery::mock();
        $validator->shouldReceive('fails')->andReturn(false);
        
        Validator::shouldReceive('make')->andReturn($validator);

        // Mock file operations
        \Illuminate\Support\Facades\File::shouldReceive('ensureDirectoryExists')->andReturn(true);
        \Illuminate\Support\Facades\File::shouldReceive('directories')->andReturn(['/tmp/extracted/plugin']);
        \Illuminate\Support\Facades\File::shouldReceive('exists')->andReturn(true);
        \Illuminate\Support\Facades\File::shouldReceive('deleteDirectory')->andReturn(true);

        $this->pluginManagerMock
            ->shouldReceive('installPlugin')
            ->once()
            ->andReturn(true);

        $response = $this->controller->install($request);

        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertStringContains('installed successfully', $data['message']);
        $this->assertArrayHasKey('plugin', $data);
    }

    public function testInstallPluginFailsWithInvalidFile()
    {
        $request = new Request([
            'type' => 'mod',
            'force' => false
        ]);

        $response = $this->controller->install($request);

        $this->assertEquals(422, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertArrayHasKey('errors', $data);
    }

    public function testUpdateSettingsSuccessfully()
    {
        $mockPlugin = Mockery::mock('App\Core\Plugin\Contracts\ModuleInterface');
        $mockPlugin->shouldReceive('updateSettings')
            ->with(['attempts' => 5, 'time_limit' => 3600])
            ->once()
            ->andReturn(true);

        $this->pluginManagerMock
            ->shouldReceive('getPlugin')
            ->with('mod', 'quiz')
            ->once()
            ->andReturn($mockPlugin);

        $request = new Request([
            'settings' => [
                'attempts' => 5,
                'time_limit' => 3600
            ]
        ]);

        $response = $this->controller->updateSettings($request, 'mod', 'quiz');

        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Plugin settings updated successfully', $data['message']);
    }

    public function testGetConfigFormReturnsFormData()
    {
        $mockPlugin = Mockery::mock('App\Core\Plugin\Contracts\ModuleInterface');
        $mockPlugin->shouldReceive('getConfigSchema')->andReturn([
            'attempts' => ['type' => 'number', 'label' => 'Attempts allowed']
        ]);
        $mockPlugin->shouldReceive('getSettings')->andReturn(['attempts' => 3]);
        $mockPlugin->shouldReceive('getName')->andReturn('quiz');
        $mockPlugin->shouldReceive('getType')->andReturn('mod');

        $this->pluginManagerMock
            ->shouldReceive('getPlugin')
            ->with('mod', 'quiz')
            ->once()
            ->andReturn($mockPlugin);

        $response = $this->controller->getConfigForm('mod', 'quiz');

        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('config_form', $data);
        $this->assertArrayHasKey('schema', $data['config_form']);
        $this->assertArrayHasKey('current_values', $data['config_form']);
        $this->assertEquals('quiz', $data['config_form']['plugin_name']);
    }

    public function testGetAvailableBlocksReturnsBlockList()
    {
        $mockBlocks = collect([
            [
                'name' => 'navigation',
                'title' => 'Navigation',
                'description' => 'Site navigation',
                'multiple_instances' => false,
                'configurable' => true,
            ]
        ]);

        $this->blockManagerMock
            ->shouldReceive('getAvailableBlocks')
            ->with('site-index', [])
            ->once()
            ->andReturn($mockBlocks);

        $request = new Request(['page_type' => 'site-index']);
        
        $response = $this->controller->getAvailableBlocks($request);

        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertCount(1, $data['blocks']);
        $this->assertEquals('navigation', $data['blocks'][0]['name']);
    }

    public function testAddBlockSuccessfully()
    {
        $this->blockManagerMock
            ->shouldReceive('createBlockInstance')
            ->with('navigation', 'course-view', 'side-pre', ['show_sections' => true], [])
            ->once()
            ->andReturn(123);

        $request = new Request([
            'block_name' => 'navigation',
            'page_type' => 'course-view',
            'region' => 'side-pre',
            'config' => ['show_sections' => true],
            'context' => []
        ]);

        $response = $this->controller->addBlock($request);

        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Block added successfully', $data['message']);
        $this->assertEquals(123, $data['block_instance_id']);
    }

    public function testAddBlockFailsWithInvalidData()
    {
        $request = new Request([
            'block_name' => '',  // Invalid - required
            'page_type' => 'course-view',
            'region' => 'side-pre'
        ]);

        $response = $this->controller->addBlock($request);

        $this->assertEquals(422, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertArrayHasKey('errors', $data);
    }

    public function testGetAvailableModulesReturnsModuleList()
    {
        $mockModules = collect([
            [
                'name' => 'quiz',
                'info' => ['version' => '2024012400'],
                'icon' => 'fa-question-circle',
                'description' => 'Interactive quiz activity',
                'features' => ['grade' => true],
                'capabilities' => ['mod/quiz:view' => []],
            ]
        ]);

        $this->moduleLoaderMock
            ->shouldReceive('getAvailableModules')
            ->once()
            ->andReturn($mockModules);

        $response = $this->controller->getAvailableModules();

        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertCount(1, $data['modules']);
        $this->assertEquals('quiz', $data['modules'][0]['name']);
    }

    public function testGetSystemStatusReturnsSystemInfo()
    {
        // Mock Schema facade
        \Illuminate\Support\Facades\Schema::shouldReceive('hasTable')
            ->andReturn(true);

        $response = $this->controller->getSystemStatus();

        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('plugins_directory', $data['status']);
        $this->assertArrayHasKey('database_tables', $data['status']);
        $this->assertArrayHasKey('php_extensions', $data['status']);
    }

    public function testClearCacheSuccessfully()
    {
        // Mock Artisan facade
        \Illuminate\Support\Facades\Artisan::shouldReceive('call')
            ->with('cache:clear')
            ->once();

        \Illuminate\Support\Facades\File::shouldReceive('exists')->andReturn(true);
        \Illuminate\Support\Facades\File::shouldReceive('deleteDirectory')->andReturn(true);

        $response = $this->controller->clearCache();

        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Plugin cache cleared successfully', $data['message']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
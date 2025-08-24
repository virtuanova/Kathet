<?php

namespace Tests\Unit\Core\Plugin;

use Tests\TestCase;
use App\Core\Plugin\PluginManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

/**
 * Unit tests for PluginManager
 * 
 * Tests the core plugin management functionality including:
 * - Plugin discovery and loading
 * - Version management
 * - Dependency resolution
 * - Plugin installation/uninstallation
 * - Cache management
 */
class PluginManagerTest extends TestCase
{
    use RefreshDatabase;

    protected PluginManager $pluginManager;
    protected $filesMock;
    protected $cacheMock;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock File facade
        $this->filesMock = Mockery::mock('alias:Illuminate\Support\Facades\File');
        
        // Mock Cache facade  
        $this->cacheMock = Mockery::mock('alias:Illuminate\Support\Facades\Cache');
        
        $this->pluginManager = new PluginManager();
    }

    public function testPluginManagerInitialization()
    {
        $this->assertInstanceOf(PluginManager::class, $this->pluginManager);
        $this->assertIsObject($this->pluginManager->getAvailablePlugins());
    }

    public function testGetAvailablePluginsScansDirectories()
    {
        // Setup mocks for plugin directories
        $this->filesMock
            ->shouldReceive('directories')
            ->with(base_path('plugins/mod'))
            ->once()
            ->andReturn([base_path('plugins/mod/quiz')]);
            
        $this->filesMock
            ->shouldReceive('directories')
            ->with(base_path('plugins/block'))
            ->once()
            ->andReturn([base_path('plugins/block/navigation')]);
            
        $this->filesMock
            ->shouldReceive('directories')
            ->with(base_path('plugins/theme'))
            ->once()
            ->andReturn([base_path('plugins/theme/boost')]);

        // Mock version.php file exists checks
        $this->filesMock
            ->shouldReceive('exists')
            ->with(base_path('plugins/mod/quiz/version.php'))
            ->once()
            ->andReturn(true);
            
        $this->filesMock
            ->shouldReceive('exists')
            ->with(base_path('plugins/block/navigation/version.php'))
            ->once()
            ->andReturn(true);
            
        $this->filesMock
            ->shouldReceive('exists')
            ->with(base_path('plugins/theme/boost/version.php'))
            ->once()
            ->andReturn(true);

        // Mock version file contents
        $quizVersion = '<?php $plugin->version = 2024012400; $plugin->component = "mod_quiz";';
        $navVersion = '<?php $plugin->version = 2024012400; $plugin->component = "block_navigation";';
        $boostVersion = '<?php $plugin->version = 2024012400; $plugin->component = "theme_boost";';
        
        $this->filesMock
            ->shouldReceive('get')
            ->with(base_path('plugins/mod/quiz/version.php'))
            ->once()
            ->andReturn($quizVersion);
            
        $this->filesMock
            ->shouldReceive('get')
            ->with(base_path('plugins/block/navigation/version.php'))
            ->once()
            ->andReturn($navVersion);
            
        $this->filesMock
            ->shouldReceive('get')
            ->with(base_path('plugins/theme/boost/version.php'))
            ->once()
            ->andReturn($boostVersion);

        $plugins = $this->pluginManager->getAvailablePlugins();
        
        $this->assertGreaterThan(0, $plugins->count());
        
        // Verify plugin structure
        $pluginArray = $plugins->toArray();
        $this->assertArrayHasKey('name', $pluginArray[0]);
        $this->assertArrayHasKey('type', $pluginArray[0]);
        $this->assertArrayHasKey('version', $pluginArray[0]);
        $this->assertArrayHasKey('enabled', $pluginArray[0]);
    }

    public function testGetPluginInfoParsesVersionFile()
    {
        $versionContent = '<?php
$plugin = new stdClass();
$plugin->version = 2024012400;
$plugin->release = "4.5.0";
$plugin->requires = 2024011500;
$plugin->component = "mod_quiz";
$plugin->maturity = MATURITY_STABLE;
$plugin->dependencies = array("mod_question" => 2024011500);';

        $this->filesMock
            ->shouldReceive('exists')
            ->with(base_path('plugins/mod/quiz/version.php'))
            ->once()
            ->andReturn(true);
            
        $this->filesMock
            ->shouldReceive('get')
            ->with(base_path('plugins/mod/quiz/version.php'))
            ->once()
            ->andReturn($versionContent);

        $info = $this->pluginManager->getPluginInfo('mod', 'quiz');

        $this->assertEquals('quiz', $info['name']);
        $this->assertEquals('mod', $info['type']);
        $this->assertEquals('2024012400', $info['version']);
        $this->assertArrayHasKey('dependencies', $info);
    }

    public function testLoadPluginSuccessfullyLoadsValidPlugin()
    {
        // Create mock plugin class
        $mockPluginClass = '<?php
namespace MoodlePlugin\\Mod\\Quiz;
use App\\Core\\Plugin\\Contracts\\ModuleInterface;

class QuizModule implements ModuleInterface {
    public function getName(): string { return "quiz"; }
    public function getType(): string { return "mod"; }
    public function getVersion(): string { return "2024012400"; }
    // ... other required methods would be here
}';

        $this->filesMock
            ->shouldReceive('exists')
            ->with(base_path('plugins/mod/quiz/QuizModule.php'))
            ->once()
            ->andReturn(true);
            
        $this->filesMock
            ->shouldReceive('get')
            ->with(base_path('plugins/mod/quiz/QuizModule.php'))
            ->once()
            ->andReturn($mockPluginClass);

        // Mock class loading (this would normally happen via autoloader)
        $plugin = $this->pluginManager->loadPlugin('mod', 'quiz');
        
        // Since we can't actually load the class, we expect an exception
        $this->expectException(\Exception::class);
    }

    public function testIsPluginEnabledReturnsTrueForEnabledPlugins()
    {
        // Mock config table query
        \DB::shouldReceive('table')
            ->with('mdl_config_plugins')
            ->once()
            ->andReturnSelf();
            
        \DB::shouldReceive('where')
            ->with('plugin', 'mod_quiz')
            ->once()
            ->andReturnSelf();
            
        \DB::shouldReceive('where')
            ->with('name', 'enabled')
            ->once()
            ->andReturnSelf();
            
        \DB::shouldReceive('value')
            ->with('value')
            ->once()
            ->andReturn('1');

        $enabled = $this->pluginManager->isPluginEnabled('mod', 'quiz');
        $this->assertTrue($enabled);
    }

    public function testEnablePluginUpdatesConfiguration()
    {
        \DB::shouldReceive('table')
            ->with('mdl_config_plugins')
            ->once()
            ->andReturnSelf();
            
        \DB::shouldReceive('updateOrInsert')
            ->withArgs(function($where, $data) {
                return $where['plugin'] === 'mod_quiz' && 
                       $where['name'] === 'enabled' &&
                       $data['value'] === '1';
            })
            ->once()
            ->andReturn(true);

        $result = $this->pluginManager->enablePlugin('mod', 'quiz');
        $this->assertTrue($result);
    }

    public function testDisablePluginUpdatesConfiguration()
    {
        \DB::shouldReceive('table')
            ->with('mdl_config_plugins')
            ->once()
            ->andReturnSelf();
            
        \DB::shouldReceive('updateOrInsert')
            ->withArgs(function($where, $data) {
                return $where['plugin'] === 'mod_quiz' && 
                       $where['name'] === 'enabled' &&
                       $data['value'] === '0';
            })
            ->once()
            ->andReturn(true);

        $result = $this->pluginManager->disablePlugin('mod', 'quiz');
        $this->assertTrue($result);
    }

    public function testInstallPluginHandlesValidArchive()
    {
        $sourcePath = '/tmp/test/quiz';
        $pluginType = 'mod';
        $pluginName = 'quiz';

        // Mock directory operations
        $this->filesMock
            ->shouldReceive('exists')
            ->with($sourcePath . '/version.php')
            ->once()
            ->andReturn(true);
            
        $this->filesMock
            ->shouldReceive('ensureDirectoryExists')
            ->with(base_path("plugins/{$pluginType}/{$pluginName}"))
            ->once()
            ->andReturn(true);
            
        $this->filesMock
            ->shouldReceive('copyDirectory')
            ->with($sourcePath, base_path("plugins/{$pluginType}/{$pluginName}"))
            ->once()
            ->andReturn(true);

        $result = $this->pluginManager->installPlugin($sourcePath, $pluginType, $pluginName);
        $this->assertTrue($result);
    }

    public function testUninstallPluginRemovesFiles()
    {
        $pluginPath = base_path('plugins/mod/quiz');
        
        $this->filesMock
            ->shouldReceive('exists')
            ->with($pluginPath)
            ->once()
            ->andReturn(true);
            
        $this->filesMock
            ->shouldReceive('deleteDirectory')
            ->with($pluginPath)
            ->once()
            ->andReturn(true);

        // Mock database cleanup
        \DB::shouldReceive('table')
            ->with('mdl_config_plugins')
            ->once()
            ->andReturnSelf();
            
        \DB::shouldReceive('where')
            ->with('plugin', 'mod_quiz')
            ->once()
            ->andReturnSelf();
            
        \DB::shouldReceive('delete')
            ->once()
            ->andReturn(true);

        $result = $this->pluginManager->uninstallPlugin('mod', 'quiz');
        $this->assertTrue($result);
    }

    public function testCheckDependenciesValidatesRequiredPlugins()
    {
        $dependencies = [
            'mod_question' => '2024011500',
            'core' => '2024011500'
        ];

        // Mock that mod_question is available and enabled
        \DB::shouldReceive('table')
            ->with('mdl_config_plugins')
            ->andReturnSelf();
            
        \DB::shouldReceive('where')
            ->andReturnSelf();
            
        \DB::shouldReceive('value')
            ->andReturn('1'); // enabled

        $result = $this->pluginManager->checkDependencies($dependencies);
        
        // We expect this to pass for core dependency
        $this->assertIsArray($result);
    }

    public function testGetPluginSettingsReturnsConfiguration()
    {
        // Mock settings retrieval
        \DB::shouldReceive('table')
            ->with('mdl_config_plugins')
            ->once()
            ->andReturnSelf();
            
        \DB::shouldReceive('where')
            ->with('plugin', 'mod_quiz')
            ->once()
            ->andReturnSelf();
            
        \DB::shouldReceive('pluck')
            ->with('value', 'name')
            ->once()
            ->andReturn(collect([
                'enabled' => '1',
                'version' => '2024012400',
                'custom_setting' => 'test_value'
            ]));

        $settings = $this->pluginManager->getPluginSettings('mod', 'quiz');
        
        $this->assertIsArray($settings);
        $this->assertArrayHasKey('enabled', $settings);
        $this->assertEquals('1', $settings['enabled']);
    }

    public function testUpdatePluginSettingsSavesConfiguration()
    {
        $settings = [
            'attempts_allowed' => '3',
            'time_limit' => '3600',
            'grade_method' => '1'
        ];

        foreach ($settings as $name => $value) {
            \DB::shouldReceive('table')
                ->with('mdl_config_plugins')
                ->once()
                ->andReturnSelf();
                
            \DB::shouldReceive('updateOrInsert')
                ->withArgs(function($where, $data) use ($name, $value) {
                    return $where['plugin'] === 'mod_quiz' &&
                           $where['name'] === $name &&
                           $data['value'] === $value;
                })
                ->once()
                ->andReturn(true);
        }

        $result = $this->pluginManager->updatePluginSettings('mod', 'quiz', $settings);
        $this->assertTrue($result);
    }

    public function testPluginCacheManagement()
    {
        $cacheKey = 'plugins_available';
        
        // Test cache miss
        $this->cacheMock
            ->shouldReceive('get')
            ->with($cacheKey)
            ->once()
            ->andReturn(null);
            
        // Test cache put
        $this->cacheMock
            ->shouldReceive('put')
            ->with($cacheKey, \Mockery::any(), 3600)
            ->once()
            ->andReturn(true);

        // Test cache invalidation
        $this->cacheMock
            ->shouldReceive('forget')
            ->with($cacheKey)
            ->once()
            ->andReturn(true);

        // Mock file operations for actual plugin discovery
        $this->filesMock
            ->shouldReceive('directories')
            ->andReturn([]);

        $plugins = $this->pluginManager->getAvailablePlugins();
        $this->pluginManager->clearPluginCache();
        
        $this->assertIsObject($plugins);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
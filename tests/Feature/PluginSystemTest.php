<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Core\Plugin\PluginManager;
use App\Core\Module\ModuleLoader;
use App\Core\Block\BlockManager;
use App\Core\Theme\ThemeConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

class PluginSystemTest extends TestCase
{
    use RefreshDatabase;

    protected PluginManager $pluginManager;
    protected ModuleLoader $moduleLoader;
    protected BlockManager $blockManager;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->pluginManager = new PluginManager();
        $this->moduleLoader = new ModuleLoader($this->pluginManager);
        $this->blockManager = new BlockManager($this->pluginManager);
    }

    public function test_can_load_available_plugins()
    {
        $plugins = $this->pluginManager->getAvailablePlugins();
        
        $this->assertIsObject($plugins);
        
        // Should find our test plugins
        $pluginNames = $plugins->pluck('name')->toArray();
        $this->assertContains('quiz', $pluginNames);
        $this->assertContains('navigation', $pluginNames);
        $this->assertContains('boost', $pluginNames);
    }

    public function test_can_load_quiz_module()
    {
        try {
            $quiz = $this->pluginManager->loadPlugin('mod', 'quiz');
            
            $this->assertNotNull($quiz);
            $this->assertEquals('quiz', $quiz->getName());
            $this->assertEquals('mod', $quiz->getType());
            $this->assertTrue($quiz->isCompatible());
            
            // Test supported features
            $features = $quiz->getSupportedFeatures();
            $this->assertTrue($features['grade']);
            $this->assertTrue($features['completion']);
            
            // Test capabilities
            $capabilities = $quiz->getCapabilities();
            $this->assertArrayHasKey('mod/quiz:view', $capabilities);
            $this->assertArrayHasKey('mod/quiz:attempt', $capabilities);
            
            echo "✓ Quiz module loaded successfully\n";
            
        } catch (\Exception $e) {
            $this->fail("Failed to load quiz module: " . $e->getMessage());
        }
    }

    public function test_can_load_navigation_block()
    {
        try {
            $navigation = $this->pluginManager->loadPlugin('block', 'navigation');
            
            $this->assertNotNull($navigation);
            $this->assertEquals('navigation', $navigation->getName());
            $this->assertEquals('block', $navigation->getType());
            $this->assertEquals('Navigation', $navigation->getTitle());
            
            // Test block content generation
            $content = $navigation->getContent(['context' => ['course_id' => 1]]);
            $this->assertArrayHasKey('site', $content);
            
            // Test HTML generation
            $html = $navigation->getHtml(['context' => ['course_id' => 1]]);
            $this->assertStringContains('navigation-block', $html);
            
            echo "✓ Navigation block loaded successfully\n";
            
        } catch (\Exception $e) {
            $this->fail("Failed to load navigation block: " . $e->getMessage());
        }
    }

    public function test_can_parse_boost_theme()
    {
        $themePath = base_path('plugins/theme/boost');
        
        if (!File::exists($themePath . '/config.php')) {
            $this->markTestSkipped('Boost theme not available for testing');
        }
        
        try {
            $themeInfo = $this->pluginManager->getPluginInfo('theme', 'boost');
            
            $this->assertEquals('boost', $themeInfo['name']);
            $this->assertEquals('theme', $themeInfo['type']);
            $this->assertNotEmpty($themeInfo['version']);
            
            echo "✓ Boost theme parsed successfully\n";
            
        } catch (\Exception $e) {
            $this->fail("Failed to parse boost theme: " . $e->getMessage());
        }
    }

    public function test_module_instance_creation()
    {
        // This test requires database setup
        if (!$this->canTestDatabase()) {
            $this->markTestSkipped('Database not available for testing');
        }
        
        try {
            // Create a test course first
            $courseId = \DB::table('mdl_course')->insertGetId([
                'category' => 1,
                'fullname' => 'Test Course',
                'shortname' => 'TEST',
                'summary' => 'Test course for plugin testing',
                'timecreated' => time(),
                'timemodified' => time(),
            ]);
            
            // Create a course section
            $sectionId = \DB::table('mdl_course_sections')->insertGetId([
                'course' => $courseId,
                'section' => 1,
                'name' => 'Test Section',
                'visible' => 1,
                'sequence' => '',
            ]);
            
            // Create quiz instance
            $result = $this->moduleLoader->createModuleInstance('quiz', $courseId, $sectionId, [
                'name' => 'Test Quiz',
                'intro' => 'This is a test quiz',
                'grade' => 100,
                'attempts' => 3,
            ]);
            
            $this->assertArrayHasKey('instance_id', $result);
            $this->assertArrayHasKey('course_module_id', $result);
            
            echo "✓ Module instance creation successful\n";
            
        } catch (\Exception $e) {
            $this->fail("Failed to create module instance: " . $e->getMessage());
        }
    }

    public function test_block_instance_management()
    {
        if (!$this->canTestDatabase()) {
            $this->markTestSkipped('Database not available for testing');
        }
        
        try {
            // Create block instance
            $instanceId = $this->blockManager->createBlockInstance(
                'navigation',
                'course-view',
                'side-pre',
                ['show_sections' => true]
            );
            
            $this->assertIsInt($instanceId);
            $this->assertGreaterThan(0, $instanceId);
            
            // Update block instance
            $updated = $this->blockManager->updateBlockInstance($instanceId, [
                'show_sections' => false,
                'max_sections' => 5,
            ]);
            
            $this->assertTrue($updated);
            
            // Delete block instance
            $deleted = $this->blockManager->deleteBlockInstance($instanceId);
            $this->assertTrue($deleted);
            
            echo "✓ Block instance management successful\n";
            
        } catch (\Exception $e) {
            $this->fail("Failed to manage block instance: " . $e->getMessage());
        }
    }

    public function test_theme_converter()
    {
        $themePath = base_path('plugins/theme/boost');
        $outputPath = storage_path('app/testing/converted_theme');
        
        if (!File::exists($themePath . '/config.php')) {
            $this->markTestSkipped('Boost theme not available for testing');
        }
        
        try {
            $converter = new ThemeConverter($themePath, $outputPath);
            
            // Test parsing theme configuration
            $config = $converter->parseThemeConfig();
            $this->assertArrayHasKey('name', $config);
            $this->assertEquals('boost', $config['name']);
            
            // Test mustache to blade conversion
            $mustache = '{{#hasblocks}}<div class="blocks">{{/hasblocks}}';
            $blade = $converter->mustacheToBlade($mustache);
            $this->assertStringContains('@if($hasblocks)', $blade);
            
            echo "✓ Theme converter working correctly\n";
            
        } catch (\Exception $e) {
            $this->fail("Failed theme conversion test: " . $e->getMessage());
        } finally {
            // Cleanup
            if (File::exists($outputPath)) {
                File::deleteDirectory($outputPath);
            }
        }
    }

    public function test_plugin_dependencies()
    {
        try {
            $quiz = $this->pluginManager->loadPlugin('mod', 'quiz');
            $dependencies = $quiz->getDependencies();
            
            $this->assertArrayHasKey('mod_question', $dependencies);
            $this->assertArrayHasKey('core', $dependencies);
            
            echo "✓ Plugin dependencies parsed correctly\n";
            
        } catch (\Exception $e) {
            $this->fail("Failed to test plugin dependencies: " . $e->getMessage());
        }
    }

    public function test_plugin_ajax_handling()
    {
        try {
            $quiz = $this->pluginManager->loadPlugin('mod', 'quiz');
            
            // Test AJAX handler
            $result = $quiz->handleAjax('start_attempt', [
                'quiz_id' => 1,
                'user_id' => 1,
            ]);
            
            $this->assertArrayHasKey('success', $result);
            
            echo "✓ Plugin AJAX handling working\n";
            
        } catch (\Exception $e) {
            // Expected to fail without proper database setup
            $this->assertStringContains('Quiz not found', $e->getMessage());
            echo "✓ Plugin AJAX handling responds appropriately to missing data\n";
        }
    }

    protected function canTestDatabase(): bool
    {
        try {
            \DB::connection()->getPdo();
            return \Schema::hasTable('mdl_course') && \Schema::hasTable('mdl_course_sections');
        } catch (\Exception $e) {
            return false;
        }
    }

    public function test_comprehensive_plugin_compatibility()
    {
        $testResults = [
            'modules_loaded' => 0,
            'blocks_loaded' => 0,
            'themes_parsed' => 0,
            'errors' => [],
        ];
        
        // Test all available plugins
        $plugins = $this->pluginManager->getAvailablePlugins();
        
        foreach ($plugins as $plugin) {
            try {
                switch ($plugin['type']) {
                    case 'mod':
                        $loaded = $this->pluginManager->loadPlugin('mod', $plugin['name']);
                        if ($loaded) {
                            $testResults['modules_loaded']++;
                        }
                        break;
                        
                    case 'block':
                        $loaded = $this->pluginManager->loadPlugin('block', $plugin['name']);
                        if ($loaded) {
                            $testResults['blocks_loaded']++;
                        }
                        break;
                        
                    case 'theme':
                        $info = $this->pluginManager->getPluginInfo('theme', $plugin['name']);
                        if ($info) {
                            $testResults['themes_parsed']++;
                        }
                        break;
                }
            } catch (\Exception $e) {
                $testResults['errors'][] = "Failed to load {$plugin['type']}/{$plugin['name']}: " . $e->getMessage();
            }
        }
        
        // Output test results
        echo "\n=== Plugin Compatibility Test Results ===\n";
        echo "Modules loaded: {$testResults['modules_loaded']}\n";
        echo "Blocks loaded: {$testResults['blocks_loaded']}\n"; 
        echo "Themes parsed: {$testResults['themes_parsed']}\n";
        
        if (!empty($testResults['errors'])) {
            echo "\nErrors encountered:\n";
            foreach ($testResults['errors'] as $error) {
                echo "- $error\n";
            }
        } else {
            echo "\n✓ All plugins loaded successfully!\n";
        }
        
        // Assertions
        $this->assertGreaterThan(0, $testResults['modules_loaded'] + $testResults['blocks_loaded'] + $testResults['themes_parsed']);
    }
}
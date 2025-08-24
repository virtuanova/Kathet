<?php

/**
 * Simple plugin system test script
 */

require_once 'app/Core/Plugin/PluginManager.php';
require_once 'app/Core/Plugin/Contracts/PluginInterface.php';
require_once 'app/Core/Plugin/Contracts/ModuleInterface.php';
require_once 'app/Core/Plugin/Contracts/BlockInterface.php';

// Mock some Laravel components for testing
if (!function_exists('base_path')) {
    function base_path($path = '') {
        return __DIR__ . ($path ? '/' . $path : '');
    }
}

if (!function_exists('storage_path')) {
    function storage_path($path = '') {
        return __DIR__ . '/storage' . ($path ? '/' . $path : '');
    }
}

if (!class_exists('File')) {
    class File {
        public static function exists($path) {
            return file_exists($path);
        }
        
        public static function directories($path) {
            if (!is_dir($path)) return [];
            return array_filter(glob($path . '/*'), 'is_dir');
        }
        
        public static function get($path) {
            return file_get_contents($path);
        }
    }
}

namespace Illuminate\Support {
    class Collection {
        protected $items = [];
        
        public function __construct($items = []) {
            $this->items = $items;
        }
        
        public function pluck($key) {
            return new Collection(array_column($this->items, $key));
        }
        
        public function toArray() {
            return $this->items;
        }
        
        public function where($key, $value) {
            $filtered = array_filter($this->items, function($item) use ($key, $value) {
                return isset($item[$key]) && $item[$key] == $value;
            });
            return new Collection(array_values($filtered));
        }
        
        public function count() {
            return count($this->items);
        }
    }
}

namespace {

// Laravel helper functions
if (!function_exists('collect')) {
    function collect($items = []) {
        return new Illuminate\Support\Collection($items);
    }
}

if (!function_exists('now')) {
    function now() {
        return new DateTime();
    }
}

if (!function_exists('route')) {
    function route($name, $params = []) {
        return "/$name";
    }
}

// Simple test functions
function testPluginDiscovery() {
    echo "Testing plugin discovery...\n";
    
    $pluginManager = new App\Core\Plugin\PluginManager();
    $plugins = $pluginManager->getAvailablePlugins();
    
    echo "Found " . $plugins->count() . " plugins:\n";
    
    foreach ($plugins->toArray() as $plugin) {
        echo "  - {$plugin['type']}/{$plugin['name']} (v{$plugin['version']})\n";
    }
    
    return $plugins->count() > 0;
}

function testQuizModuleLoading() {
    echo "\nTesting quiz module loading...\n";
    
    try {
        // Include the quiz module
        if (!file_exists('plugins/mod/quiz/QuizModule.php')) {
            echo "❌ Quiz module file not found\n";
            return false;
        }
        
        require_once 'plugins/mod/quiz/QuizModule.php';
        
        $quiz = new MoodlePlugin\Mod\Quiz\QuizModule();
        
        echo "✓ Quiz module name: " . $quiz->getName() . "\n";
        echo "✓ Quiz module type: " . $quiz->getType() . "\n";
        echo "✓ Quiz module version: " . $quiz->getVersion() . "\n";
        echo "✓ Quiz module description: " . $quiz->getDescription() . "\n";
        
        $features = $quiz->getSupportedFeatures();
        echo "✓ Supports grading: " . ($features['grade'] ? 'Yes' : 'No') . "\n";
        echo "✓ Supports completion: " . ($features['completion'] ? 'Yes' : 'No') . "\n";
        
        $capabilities = $quiz->getCapabilities();
        echo "✓ Has " . count($capabilities) . " capabilities defined\n";
        
        return true;
        
    } catch (Exception $e) {
        echo "❌ Error loading quiz module: " . $e->getMessage() . "\n";
        return false;
    }
}

function testNavigationBlockLoading() {
    echo "\nTesting navigation block loading...\n";
    
    try {
        if (!file_exists('plugins/block/navigation/NavigationBlock.php')) {
            echo "❌ Navigation block file not found\n";
            return false;
        }
        
        require_once 'plugins/block/navigation/NavigationBlock.php';
        
        $navigation = new MoodlePlugin\Block\Navigation\NavigationBlock();
        
        echo "✓ Navigation block name: " . $navigation->getName() . "\n";
        echo "✓ Navigation block title: " . $navigation->getTitle() . "\n";
        echo "✓ Navigation block type: " . $navigation->getType() . "\n";
        
        $pageTypes = $navigation->getSupportedPageTypes();
        echo "✓ Supports " . count($pageTypes) . " page types\n";
        
        $regions = $navigation->getSupportedRegions();
        echo "✓ Supports " . count($regions) . " regions: " . implode(', ', $regions) . "\n";
        
        echo "✓ Has config: " . ($navigation->hasConfig() ? 'Yes' : 'No') . "\n";
        echo "✓ Multiple instances: " . ($navigation->supportsMultipleInstances() ? 'Yes' : 'No') . "\n";
        
        return true;
        
    } catch (Exception $e) {
        echo "❌ Error loading navigation block: " . $e->getMessage() . "\n";
        return false;
    }
}

function testBoostThemeParsing() {
    echo "\nTesting boost theme parsing...\n";
    
    try {
        if (!file_exists('plugins/theme/boost/config.php')) {
            echo "❌ Boost theme config not found\n";
            return false;
        }
        
        if (!file_exists('plugins/theme/boost/version.php')) {
            echo "❌ Boost theme version not found\n";
            return false;
        }
        
        // Test version file parsing
        $versionContent = file_get_contents('plugins/theme/boost/version.php');
        echo "✓ Version file exists and readable\n";
        
        if (strpos($versionContent, 'theme_boost') !== false) {
            echo "✓ Theme component correctly defined\n";
        }
        
        // Test config file parsing
        $configContent = file_get_contents('plugins/theme/boost/config.php');
        echo "✓ Config file exists and readable\n";
        
        if (strpos($configContent, '$THEME->name = \'boost\'') !== false) {
            echo "✓ Theme name correctly defined\n";
        }
        
        if (strpos($configContent, '$THEME->layouts') !== false) {
            echo "✓ Theme layouts defined\n";
        }
        
        // Test template file
        if (file_exists('plugins/theme/boost/templates/drawers.mustache')) {
            echo "✓ Mustache template found\n";
            $templateContent = file_get_contents('plugins/theme/boost/templates/drawers.mustache');
            
            if (strpos($templateContent, '{{output.doctype}}') !== false) {
                echo "✓ Template contains Mustache variables\n";
            }
        }
        
        // Test SCSS file
        if (file_exists('plugins/theme/boost/scss/_variables.scss')) {
            echo "✓ SCSS variables file found\n";
            $scssContent = file_get_contents('plugins/theme/boost/scss/_variables.scss');
            
            if (strpos($scssContent, '$brand-primary') !== false) {
                echo "✓ SCSS contains theme variables\n";
            }
        }
        
        return true;
        
    } catch (Exception $e) {
        echo "❌ Error parsing boost theme: " . $e->getMessage() . "\n";
        return false;
    }
}

function testMustacheToBlade() {
    echo "\nTesting Mustache to Blade conversion...\n";
    
    try {
        require_once 'app/Core/Theme/ThemeConverter.php';
        
        $converter = new App\Core\Theme\ThemeConverter('/tmp', '/tmp');
        
        $testCases = [
            // Basic variable
            '{{sitename}}' => '{{{ $sitename }}}',
            // Conditional blocks
            '{{#hasblocks}}Content{{/hasblocks}}' => '@if($hasblocks)Content@endif',
            // Inverted blocks  
            '{{^hasblocks}}No blocks{{/hasblocks}}' => '@unless($hasblocks)No blocks@endunless',
            // Loops
            '{{#items}}{{name}}{{/items}}' => '@foreach($items as $item){{ $item["name"] }}@endforeach',
            // Comments
            '{{! This is a comment }}' => '{{-- This is a comment --}}',
            // Raw output
            '{{{output.content}}}' => '{!! $output["content"] !!}',
        ];
        
        $passedTests = 0;
        $totalTests = count($testCases);
        
        foreach ($testCases as $mustache => $expectedBlade) {
            $actualBlade = $converter->mustacheToBlade($mustache);
            
            if (trim($actualBlade) === trim($expectedBlade)) {
                echo "✓ '$mustache' -> '$actualBlade'\n";
                $passedTests++;
            } else {
                echo "❌ '$mustache' -> Expected: '$expectedBlade', Got: '$actualBlade'\n";
            }
        }
        
        echo "\nMustache to Blade conversion: $passedTests/$totalTests tests passed\n";
        return $passedTests === $totalTests;
        
    } catch (Exception $e) {
        echo "❌ Error testing Mustache to Blade conversion: " . $e->getMessage() . "\n";
        return false;
    }
}

function runAllTests() {
    echo "=== LMS Plugin System Compatibility Tests ===\n\n";
    
    $tests = [
        'Plugin Discovery' => 'testPluginDiscovery',
        'Quiz Module Loading' => 'testQuizModuleLoading', 
        'Navigation Block Loading' => 'testNavigationBlockLoading',
        'Boost Theme Parsing' => 'testBoostThemeParsing',
        'Mustache to Blade Conversion' => 'testMustacheToBlade',
    ];
    
    $results = [];
    $passed = 0;
    
    foreach ($tests as $testName => $testFunction) {
        echo str_repeat('=', 50) . "\n";
        $result = $testFunction();
        $results[$testName] = $result;
        
        if ($result) {
            $passed++;
            echo "✅ $testName: PASSED\n";
        } else {
            echo "❌ $testName: FAILED\n";
        }
        echo "\n";
    }
    
    echo str_repeat('=', 50) . "\n";
    echo "FINAL RESULTS:\n";
    echo "Tests passed: $passed/" . count($tests) . "\n";
    
    if ($passed === count($tests)) {
        echo "\n🎉 ALL TESTS PASSED! Plugin system is working correctly.\n";
    } else {
        echo "\n⚠️ Some tests failed. Check the output above for details.\n";
    }
    
    return $passed === count($tests);
}

// Run the tests
runAllTests();

} // End global namespace
<?php

namespace Tests\Unit\Core\Theme;

use Tests\TestCase;
use App\Core\Theme\ThemeConverter;
use Illuminate\Support\Facades\File;

/**
 * Unit tests for ThemeConverter
 * 
 * Tests theme conversion functionality including:
 * - Mustache to Blade template conversion
 * - SCSS variable extraction and processing
 * - React component generation
 * - Theme configuration parsing
 * - File structure validation
 */
class ThemeConverterTest extends TestCase
{
    protected ThemeConverter $converter;
    protected string $mockThemePath;
    protected string $mockOutputPath;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockThemePath = '/tmp/test-theme';
        $this->mockOutputPath = '/tmp/converted-theme';
        
        $this->converter = new ThemeConverter($this->mockThemePath, $this->mockOutputPath);
    }

    public function testThemeConverterInitialization()
    {
        $this->assertInstanceOf(ThemeConverter::class, $this->converter);
    }

    public function testMustacheToBladeConvertsBasicVariables()
    {
        $testCases = [
            // Basic variable output
            '{{sitename}}' => '{{{ $sitename }}}',
            '{{user.fullname}}' => '{{{ $user["fullname"] }}}',
            
            // Raw HTML output
            '{{{output.content}}}' => '{!! $output["content"] !!}',
            '{{{block.html}}}' => '{!! $block["html"] !!}',
            
            // Conditional blocks
            '{{#hasblocks}}Content{{/hasblocks}}' => '@if($hasblocks)Content@endif',
            '{{#user.loggedin}}Welcome{{/user.loggedin}}' => '@if($user["loggedin"])Welcome@endif',
            
            // Inverted conditionals
            '{{^hasblocks}}No blocks{{/hasblocks}}' => '@unless($hasblocks)No blocks@endunless',
            '{{^user.loggedin}}Please login{{/user.loggedin}}' => '@unless($user["loggedin"])Please login@endunless',
            
            // Loops
            '{{#items}}{{name}}{{/items}}' => '@foreach($items as $item){{ $item["name"] }}@endforeach',
            '{{#blocks}}{{title}}{{/blocks}}' => '@foreach($blocks as $block){{ $block["title"] }}@endforeach',
            
            // Complex nested structures
            '{{#course.sections}}{{#activities}}{{name}}{{/activities}}{{/course.sections}}' => 
                '@foreach($course["sections"] as $section)@foreach($section["activities"] as $activity){{ $activity["name"] }}@endforeach@endforeach',
            
            // Comments
            '{{! This is a comment }}' => '{{-- This is a comment --}}',
            '{{!-- Multi-line comment --}}' => '{{-- Multi-line comment --}}',
            
            // Mixed content
            '<div class="{{#visible}}show{{/visible}}{{^visible}}hide{{/visible}}">{{content}}</div>' =>
                '<div class="@if($visible)show@endif@unless($visible)hide@endunless">{{{ $content }}}</div>',
        ];

        foreach ($testCases as $mustache => $expectedBlade) {
            $actualBlade = $this->converter->mustacheToBlade($mustache);
            $this->assertEquals($expectedBlade, $actualBlade, 
                "Failed converting: $mustache");
        }
    }

    public function testMustacheToBladeHandlesComplexLayouts()
    {
        $mustacheTemplate = '
{{> core/head }}
<body {{{ output.bodyattributes }}}>
    {{#hasblocks}}
    <div class="blocks-container">
        {{#blocks}}
        <div class="block block-{{name}}">
            {{#title}}<h3>{{title}}</h3>{{/title}}
            {{{content}}}
        </div>
        {{/blocks}}
    </div>
    {{/hasblocks}}
    {{^hasblocks}}
    <div class="no-blocks">No blocks to display</div>
    {{/hasblocks}}
</body>';

        $expectedBlade = '
@include(\'core.head\')
<body {!! $output["bodyattributes"] !!}>
    @if($hasblocks)
    <div class="blocks-container">
        @foreach($blocks as $block)
        <div class="block block-{{ $block["name"] }}">
            @if($block["title"])<h3>{{ $block["title"] }}</h3>@endif
            {!! $block["content"] !!}
        </div>
        @endforeach
    </div>
    @endif
    @unless($hasblocks)
    <div class="no-blocks">No blocks to display</div>
    @endunless
</body>';

        $actualBlade = $this->converter->mustacheToBlade($mustacheTemplate);
        
        // Normalize whitespace for comparison
        $actual = preg_replace('/\s+/', ' ', trim($actualBlade));
        $expected = preg_replace('/\s+/', ' ', trim($expectedBlade));
        
        $this->assertEquals($expected, $actual);
    }

    public function testParseThemeConfigExtractsSettings()
    {
        $configContent = '<?php
$THEME->name = \'boost\';
$THEME->parents = [\'core\'];
$THEME->sheets = [\'custom\'];
$THEME->layouts = [
    \'base\' => [
        \'file\' => \'layout.php\',
        \'regions\' => [\'side-pre\', \'side-post\'],
    ],
    \'course\' => [
        \'file\' => \'course.php\',
        \'regions\' => [\'side-pre\'],
    ],
];
$THEME->enable_dock = false;
$THEME->javascripts_footer = [\'theme\'];';

        // Mock file existence and reading
        File::shouldReceive('exists')
            ->with($this->mockThemePath . '/config.php')
            ->once()
            ->andReturn(true);
            
        File::shouldReceive('get')
            ->with($this->mockThemePath . '/config.php')
            ->once()
            ->andReturn($configContent);

        $config = $this->converter->parseThemeConfig();

        $this->assertIsArray($config);
        $this->assertEquals('boost', $config['name']);
        $this->assertArrayHasKey('layouts', $config);
        $this->assertCount(2, $config['layouts']);
        $this->assertEquals('layout.php', $config['layouts']['base']['file']);
        $this->assertEquals(['side-pre', 'side-post'], $config['layouts']['base']['regions']);
    }

    public function testGenerateReactComponentCreatesValidJSX()
    {
        $componentName = 'NavigationBlock';
        $blockData = [
            'name' => 'navigation',
            'title' => 'Navigation',
            'content' => [
                'site' => ['Dashboard' => '/dashboard', 'Courses' => '/courses'],
                'course' => ['Participants' => '/participants']
            ]
        ];

        $jsx = $this->converter->generateReactComponent($componentName, $blockData);

        $this->assertStringContains('import React', $jsx);
        $this->assertStringContains("const {$componentName}", $jsx);
        $this->assertStringContains('export default', $jsx);
        $this->assertStringContains('navigation', $jsx);
        
        // Check that the JSX is valid
        $this->assertStringContains('<div', $jsx);
        $this->assertStringContains('</div>', $jsx);
        
        // Check TypeScript props interface
        $this->assertStringContains('interface Props', $jsx);
        $this->assertStringContains('React.FC<Props>', $jsx);
    }

    public function testProcessScssVariablesExtractsAndConverts()
    {
        $scssContent = '
// Brand colors
$brand-primary: #0f6cbf !default;
$brand-success: #5cb85c !default;
$brand-danger: #d9534f !default;

// Typography  
$font-family-base: "Helvetica Neue", Arial, sans-serif !default;
$font-size-base: 1rem !default;
$line-height-base: 1.5 !default;

// Components
$border-radius: .375rem !default;
$btn-padding-x: .75rem !default;
$btn-padding-y: .375rem !default;

// Custom theme variables
$sidebar-width: 250px;
$header-height: 60px;
$content-padding: 20px;

// Mixins
@mixin button-variant($color, $background, $border) {
    color: $color;
    background-color: $background;
    border-color: $border;
}';

        File::shouldReceive('exists')
            ->with($this->mockThemePath . '/scss/_variables.scss')
            ->once()
            ->andReturn(true);
            
        File::shouldReceive('get')
            ->with($this->mockThemePath . '/scss/_variables.scss')
            ->once()
            ->andReturn($scssContent);

        $variables = $this->converter->extractScssVariables();

        $this->assertIsArray($variables);
        $this->assertArrayHasKey('$brand-primary', $variables);
        $this->assertEquals('#0f6cbf', $variables['$brand-primary']);
        $this->assertArrayHasKey('$font-size-base', $variables);
        $this->assertEquals('1rem', $variables['$font-size-base']);
        $this->assertArrayHasKey('$sidebar-width', $variables);
        $this->assertEquals('250px', $variables['$sidebar-width']);
    }

    public function testConvertThemeCreatesCompleteStructure()
    {
        // Mock theme files
        $configContent = '<?php $THEME->name = "boost";';
        $templateContent = '{{> core/head }}<body>{{{output.content}}}</body>';
        $scssContent = '$primary: #007bff; $secondary: #6c757d;';
        
        File::shouldReceive('exists')
            ->andReturn(true);
            
        File::shouldReceive('get')
            ->with($this->mockThemePath . '/config.php')
            ->once()
            ->andReturn($configContent);
            
        File::shouldReceive('get')
            ->with($this->mockThemePath . '/templates/layout.mustache')
            ->once()
            ->andReturn($templateContent);
            
        File::shouldReceive('get')
            ->with($this->mockThemePath . '/scss/_variables.scss')
            ->once()
            ->andReturn($scssContent);

        File::shouldReceive('directories')
            ->with($this->mockThemePath . '/templates')
            ->once()
            ->andReturn([$this->mockThemePath . '/templates']);
            
        File::shouldReceive('files')
            ->with($this->mockThemePath . '/templates')
            ->once()
            ->andReturn([$this->mockThemePath . '/templates/layout.mustache']);

        File::shouldReceive('directories')
            ->with($this->mockThemePath . '/scss')
            ->once()
            ->andReturn([$this->mockThemePath . '/scss']);
            
        File::shouldReceive('files')
            ->with($this->mockThemePath . '/scss')
            ->once()
            ->andReturn([$this->mockThemePath . '/scss/_variables.scss']);

        // Mock output directory creation
        File::shouldReceive('ensureDirectoryExists')
            ->andReturn(true);

        // Mock file writing
        File::shouldReceive('put')
            ->andReturn(true);

        $result = $this->converter->convertTheme();

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('converted_files', $result);
        $this->assertArrayHasKey('theme_config', $result);
    }

    public function testValidateThemeStructureChecksRequiredFiles()
    {
        // Test with valid theme structure
        File::shouldReceive('exists')
            ->with($this->mockThemePath . '/config.php')
            ->once()
            ->andReturn(true);
            
        File::shouldReceive('exists')
            ->with($this->mockThemePath . '/version.php')
            ->once()
            ->andReturn(true);
            
        File::shouldReceive('isDirectory')
            ->with($this->mockThemePath . '/templates')
            ->once()
            ->andReturn(true);

        $isValid = $this->converter->validateThemeStructure();
        $this->assertTrue($isValid);
        
        // Test with invalid theme structure
        File::shouldReceive('exists')
            ->with($this->mockThemePath . '/config.php')
            ->once()
            ->andReturn(false);

        $isValid = $this->converter->validateThemeStructure();
        $this->assertFalse($isValid);
    }

    public function testGenerateThemeManifestCreatesMetadata()
    {
        $themeConfig = [
            'name' => 'boost',
            'parents' => ['core'],
            'layouts' => ['base' => ['file' => 'layout.php']]
        ];

        $convertedFiles = [
            'templates/layout.blade.php',
            'styles/variables.css',
            'react/components/BoostTheme.tsx'
        ];

        File::shouldReceive('put')
            ->withArgs(function($path, $content) {
                $data = json_decode($content, true);
                return str_ends_with($path, 'theme.json') &&
                       $data['name'] === 'boost' &&
                       $data['type'] === 'converted_moodle_theme';
            })
            ->once()
            ->andReturn(true);

        $manifest = $this->converter->generateThemeManifest($themeConfig, $convertedFiles);

        $this->assertIsArray($manifest);
        $this->assertEquals('boost', $manifest['name']);
        $this->assertEquals('converted_moodle_theme', $manifest['type']);
        $this->assertArrayHasKey('converted_files', $manifest);
        $this->assertArrayHasKey('blade_templates', $manifest);
        $this->assertArrayHasKey('react_components', $manifest);
    }

    public function testOptimizeAssetsMinifiesAndProcesses()
    {
        $cssContent = '
        /* Comment to remove */
        .container {
            padding: 20px;
            margin: 10px;
            background-color: #ffffff;
        }
        
        .button {
            display: inline-block;
            color: #000000;
        }';

        $jsContent = '
        // Comment to remove
        function initializeTheme() {
            console.log("Theme initialized");
            return true;
        }
        
        const config = {
            version: "1.0.0",
            debug: false
        };';

        File::shouldReceive('get')
            ->andReturn($cssContent, $jsContent);
            
        File::shouldReceive('put')
            ->twice()
            ->andReturn(true);

        $optimized = $this->converter->optimizeAssets([
            'styles/theme.css' => $cssContent,
            'scripts/theme.js' => $jsContent
        ]);

        $this->assertIsArray($optimized);
        $this->assertArrayHasKey('styles/theme.min.css', $optimized);
        $this->assertArrayHasKey('scripts/theme.min.js', $optimized);
        
        // Check that comments are removed and content is minified
        $minifiedCss = $optimized['styles/theme.min.css'];
        $this->assertStringNotContains('Comment to remove', $minifiedCss);
        $this->assertLessThan(strlen($cssContent), strlen($minifiedCss));
    }
}
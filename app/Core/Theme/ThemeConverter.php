<?php

namespace App\Core\Theme;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Converts Moodle themes to Laravel Blade and React/Vue compatible formats
 */
class ThemeConverter
{
    protected string $moodleThemePath;
    protected string $outputPath;
    protected array $conversionLog = [];
    protected array $convertedFiles = [];
    protected array $errors = [];

    public function __construct(string $moodleThemePath, string $outputPath)
    {
        $this->moodleThemePath = $moodleThemePath;
        $this->outputPath = $outputPath;
    }

    /**
     * Convert a complete Moodle theme
     */
    public function convertTheme(): array
    {
        $this->log('Starting theme conversion...');

        try {
            // 1. Parse theme configuration
            $themeConfig = $this->parseThemeConfig();
            
            // 2. Convert templates (Mustache to Blade)
            $this->convertTemplates();
            
            // 3. Convert styles (SCSS to CSS/Tailwind)
            $this->convertStyles();
            
            // 4. Convert JavaScript
            $this->convertJavaScript();
            
            // 5. Convert layouts
            $this->convertLayouts();
            
            // 6. Generate React/Vue components
            $this->generateReactComponents();
            
            // 7. Create theme configuration for Laravel
            $this->createLaravelThemeConfig($themeConfig);
            
            // 8. Generate asset manifest
            $this->generateAssetManifest();

            $this->log('Theme conversion completed successfully');

            return [
                'success' => true,
                'converted_files' => $this->convertedFiles,
                'log' => $this->conversionLog,
                'errors' => $this->errors
            ];

        } catch (\Exception $e) {
            $this->error("Theme conversion failed: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'log' => $this->conversionLog,
                'errors' => $this->errors
            ];
        }
    }

    /**
     * Parse Moodle theme configuration
     */
    protected function parseThemeConfig(): array
    {
        $configFile = $this->moodleThemePath . '/config.php';
        
        if (!File::exists($configFile)) {
            throw new \Exception('Theme config.php not found');
        }

        // Extract theme configuration safely
        $THEME = new \stdClass();
        include $configFile;

        return [
            'name' => $THEME->name ?? basename($this->moodleThemePath),
            'parents' => $THEME->parents ?? [],
            'sheets' => $THEME->sheets ?? [],
            'layouts' => $THEME->layouts ?? [],
            'rendererfactory' => $THEME->rendererfactory ?? null,
            'csspostprocess' => $THEME->csspostprocess ?? null,
            'javascripts' => $THEME->javascripts ?? [],
            'javascripts_footer' => $THEME->javascripts_footer ?? [],
            'supportscssoptimisation' => $THEME->supportscssoptimisation ?? false,
            'yuicssmodules' => $THEME->yuicssmodules ?? [],
            'enable_dock' => $THEME->enable_dock ?? false,
            'settings' => $THEME->settings ?? [],
        ];
    }

    /**
     * Convert Mustache templates to Blade templates
     */
    protected function convertTemplates(): void
    {
        $templatesPath = $this->moodleThemePath . '/templates';
        
        if (!File::exists($templatesPath)) {
            $this->log('No templates directory found, skipping template conversion');
            return;
        }

        $templateFiles = File::allFiles($templatesPath);
        $outputTemplatesPath = $this->outputPath . '/templates';
        
        File::ensureDirectoryExists($outputTemplatesPath);

        foreach ($templateFiles as $file) {
            if ($file->getExtension() === 'mustache') {
                $this->convertMustacheTemplate($file, $outputTemplatesPath);
            }
        }
    }

    /**
     * Convert individual Mustache template to Blade
     */
    protected function convertMustacheTemplate(\SplFileInfo $file, string $outputPath): void
    {
        $content = File::get($file->getPathname());
        $fileName = $file->getFilenameWithoutExtension();
        
        $this->log("Converting template: {$fileName}");

        // Convert Mustache syntax to Blade
        $bladeContent = $this->mustacheToBlade($content);
        
        // Save as Blade template
        $bladeFile = $outputPath . "/{$fileName}.blade.php";
        File::put($bladeFile, $bladeContent);
        
        // Also create React/Vue component version
        $this->createReactTemplate($fileName, $content, $outputPath);
        
        $this->convertedFiles[] = $bladeFile;
    }

    /**
     * Convert Mustache syntax to Blade syntax
     */
    protected function mustacheToBlade(string $content): string
    {
        // Basic Mustache to Blade conversions
        $conversions = [
            // Variables
            '/\{\{([^{}#\/\^>!]+)\}\}/' => '{{ $1 }}',
            
            // Sections (loops)
            '/\{\{#(\w+)\}\}/' => '@foreach($1 as $item)',
            '/\{\{\/(\w+)\}\}/' => '@endforeach',
            
            // Inverted sections
            '/\{\{\^(\w+)\}\}/' => '@empty($1)',
            
            // Partials
            '/\{\{>(\w+)\}\}/' => '@include(\'$1\')',
            
            // Comments
            '/\{\{!(.+?)\}\}/' => '{{-- $1 --}}',
            
            // Unescaped variables
            '/\{\{\{(.+?)\}\}\}/' => '{!! $1 !!}',
        ];

        foreach ($conversions as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content);
        }

        // Add Blade template directive
        $content = "{{-- Converted from Mustache template --}}\n" . $content;

        return $content;
    }

    /**
     * Create React component from template
     */
    protected function createReactTemplate(string $name, string $mustacheContent, string $outputPath): void
    {
        $componentName = Str::studly($name);
        $reactPath = $outputPath . '/react';
        
        File::ensureDirectoryExists($reactPath);

        // Convert to React JSX
        $jsxContent = $this->mustacheToJsx($mustacheContent);
        
        $reactComponent = $this->generateReactComponent($componentName, $jsxContent);
        
        $reactFile = $reactPath . "/{$componentName}.tsx";
        File::put($reactFile, $reactComponent);
        
        $this->convertedFiles[] = $reactFile;
    }

    /**
     * Convert Mustache to JSX
     */
    protected function mustacheToJsx(string $content): string
    {
        $conversions = [
            // Variables
            '/\{\{([^{}#\/\^>!]+)\}\}/' => '{$1}',
            
            // Sections (loops)
            '/\{\{#(\w+)\}\}(.+?)\{\{\/\1\}\}/s' => '{$1.map(item => ($2))}',
            
            // Conditionals
            '/\{\{\^(\w+)\}\}(.+?)\{\{\/\1\}\}/s' => '{!$1 && ($2)}',
            
            // Classes
            '/class="/' => 'className="',
            
            // For attributes
            '/for="/' => 'htmlFor="',
        ];

        foreach ($conversions as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content);
        }

        return $content;
    }

    /**
     * Generate React component wrapper
     */
    protected function generateReactComponent(string $name, string $jsx): string
    {
        return "import React from 'react';

interface {$name}Props {
  // Define props based on template variables
  [key: string]: any;
}

const {$name}: React.FC<{$name}Props> = (props) => {
  return (
    <div className=\"moodle-template-{$name}\">
      {$jsx}
    </div>
  );
};

export default {$name};
";
    }

    /**
     * Convert SCSS styles
     */
    protected function convertStyles(): void
    {
        $stylesPath = $this->moodleThemePath . '/scss';
        $legacyStylesPath = $this->moodleThemePath . '/style';
        
        $outputStylesPath = $this->outputPath . '/styles';
        File::ensureDirectoryExists($outputStylesPath);

        // Convert SCSS files
        if (File::exists($stylesPath)) {
            $this->convertScssFiles($stylesPath, $outputStylesPath);
        }

        // Convert legacy CSS files
        if (File::exists($legacyStylesPath)) {
            $this->convertLegacyCss($legacyStylesPath, $outputStylesPath);
        }

        // Generate Tailwind configuration
        $this->generateTailwindConfig();
    }

    /**
     * Convert SCSS files
     */
    protected function convertScssFiles(string $inputPath, string $outputPath): void
    {
        $scssFiles = File::allFiles($inputPath);

        foreach ($scssFiles as $file) {
            if ($file->getExtension() === 'scss') {
                $this->convertScssFile($file, $outputPath);
            }
        }
    }

    /**
     * Convert individual SCSS file
     */
    protected function convertScssFile(\SplFileInfo $file, string $outputPath): void
    {
        $content = File::get($file->getPathname());
        $fileName = $file->getFilenameWithoutExtension();
        
        $this->log("Converting SCSS: {$fileName}");

        // Convert Moodle SCSS variables to CSS custom properties
        $cssContent = $this->scssToCss($content);
        
        // Save as CSS
        $cssFile = $outputPath . "/{$fileName}.css";
        File::put($cssFile, $cssContent);
        
        // Create Tailwind-compatible version
        $tailwindContent = $this->cssToTailwind($cssContent);
        $tailwindFile = $outputPath . "/{$fileName}.tailwind.css";
        File::put($tailwindFile, $tailwindContent);
        
        $this->convertedFiles[] = $cssFile;
        $this->convertedFiles[] = $tailwindFile;
    }

    /**
     * Convert SCSS to CSS (basic conversion)
     */
    protected function scssToCss(string $scss): string
    {
        // Basic SCSS to CSS conversion
        // In a real implementation, you'd use a proper SCSS compiler
        
        $conversions = [
            // Variables
            '/\$([a-zA-Z0-9_-]+):\s*([^;]+);/' => '--$1: $2;',
            '/\$([a-zA-Z0-9_-]+)/' => 'var(--$1)',
            
            // Nesting (simplified)
            '/\.([a-zA-Z0-9_-]+)\s*\{([^{}]*\.([a-zA-Z0-9_-]+)[^{}]*)\}/' => '.$1 { } .$1 .$3 { $2 }',
            
            // Mixins (remove @include, keep content)
            '/@include\s+[^;]+;/' => '',
            
            // Remove @import of SCSS files
            '/@import\s+["\']([^"\']+)\.scss["\'];/' => '',
        ];

        foreach ($conversions as $pattern => $replacement) {
            $scss = preg_replace($pattern, $replacement, $scss);
        }

        return "/* Converted from SCSS */\n" . $scss;
    }

    /**
     * Extract CSS classes and suggest Tailwind equivalents
     */
    protected function cssToTailwind(string $css): string
    {
        // This would analyze CSS and suggest Tailwind classes
        // For now, just add Tailwind utilities for common Moodle patterns
        
        $tailwindUtilities = "
/* Tailwind utilities for Moodle theme */
@tailwind base;
@tailwind components;
@tailwind utilities;

/* Moodle-specific component classes */
@layer components {
  .moodle-course-header {
    @apply bg-blue-600 text-white p-4 rounded-lg mb-4;
  }
  
  .moodle-activity {
    @apply border border-gray-300 rounded-lg p-4 mb-4 hover:shadow-md transition-shadow;
  }
  
  .moodle-block {
    @apply bg-white border border-gray-200 rounded-lg shadow-sm mb-4;
  }
  
  .moodle-block-header {
    @apply bg-gray-50 px-4 py-2 border-b border-gray-200 font-semibold;
  }
  
  .moodle-block-content {
    @apply p-4;
  }
}

/* Original converted CSS */
{$css}
";

        return $tailwindUtilities;
    }

    /**
     * Generate Tailwind configuration
     */
    protected function generateTailwindConfig(): void
    {
        $tailwindConfig = [
            'content' => [
                './resources/**/*.blade.php',
                './resources/**/*.js',
                './resources/**/*.tsx',
                './plugins/**/*.php',
                './plugins/**/*.js',
                './plugins/**/*.tsx',
            ],
            'theme' => [
                'extend' => [
                    'colors' => [
                        'moodle' => [
                            'primary' => '#0f6cbf',
                            'secondary' => '#495057',
                            'success' => '#28a745',
                            'warning' => '#ffc107',
                            'danger' => '#dc3545',
                        ],
                    ],
                    'fontFamily' => [
                        'moodle' => ['Inter', 'system-ui', 'sans-serif'],
                    ],
                ],
            ],
            'plugins' => [
                // Moodle-specific plugins would go here
            ],
        ];

        $configFile = $this->outputPath . '/tailwind.config.js';
        $configContent = "module.exports = " . json_encode($tailwindConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . ";";
        
        File::put($configFile, $configContent);
        $this->convertedFiles[] = $configFile;
    }

    /**
     * Convert JavaScript files
     */
    protected function convertJavaScript(): void
    {
        $jsPath = $this->moodleThemePath . '/javascript';
        
        if (!File::exists($jsPath)) {
            $this->log('No JavaScript directory found, skipping JS conversion');
            return;
        }

        $outputJsPath = $this->outputPath . '/js';
        File::ensureDirectoryExists($outputJsPath);

        $jsFiles = File::allFiles($jsPath);

        foreach ($jsFiles as $file) {
            if ($file->getExtension() === 'js') {
                $this->convertJavaScriptFile($file, $outputJsPath);
            }
        }
    }

    /**
     * Convert individual JavaScript file
     */
    protected function convertJavaScriptFile(\SplFileInfo $file, string $outputPath): void
    {
        $content = File::get($file->getPathname());
        $fileName = $file->getFilenameWithoutExtension();
        
        $this->log("Converting JavaScript: {$fileName}");

        // Convert to modern ES6+ and TypeScript
        $modernJs = $this->modernizeJavaScript($content);
        $typescript = $this->jsToTypeScript($modernJs);

        // Save both versions
        $jsFile = $outputPath . "/{$fileName}.js";
        $tsFile = $outputPath . "/{$fileName}.ts";
        
        File::put($jsFile, $modernJs);
        File::put($tsFile, $typescript);
        
        $this->convertedFiles[] = $jsFile;
        $this->convertedFiles[] = $tsFile;
    }

    /**
     * Modernize JavaScript code
     */
    protected function modernizeJavaScript(string $js): string
    {
        // Basic modernization
        $conversions = [
            // var to const/let
            '/var\s+([a-zA-Z_$][a-zA-Z0-9_$]*)\s*=\s*([^;]+);/' => 'const $1 = $2;',
            
            // jQuery to vanilla JS (basic patterns)
            '/\$\(document\)\.ready\(function\(\)\s*\{/' => 'document.addEventListener("DOMContentLoaded", function() {',
            '/\$\(\'([^\']+)\'\)\.click\(/' => 'document.querySelector(\'$1\').addEventListener("click", ',
            '/\$\(\'([^\']+)\'\)\.hide\(\)/' => 'document.querySelector(\'$1\').style.display = "none"',
            '/\$\(\'([^\']+)\'\)\.show\(\)/' => 'document.querySelector(\'$1\').style.display = "block"',
            
            // Function declarations to arrow functions (simple cases)
            '/function\s+([a-zA-Z_$][a-zA-Z0-9_$]*)\s*\(([^)]*)\)\s*\{/' => 'const $1 = ($2) => {',
        ];

        foreach ($conversions as $pattern => $replacement) {
            $js = preg_replace($pattern, $replacement, $js);
        }

        return "// Modernized JavaScript\n" . $js;
    }

    /**
     * Convert JavaScript to TypeScript
     */
    protected function jsToTypeScript(string $js): string
    {
        // Add TypeScript annotations
        $ts = "// Generated TypeScript from Moodle theme JavaScript\n\n";
        
        // Add type definitions for common Moodle objects
        $ts .= "interface MoodleConfig {\n";
        $ts .= "  wwwroot: string;\n";
        $ts .= "  sesskey: string;\n";
        $ts .= "  themerev: number;\n";
        $ts .= "}\n\n";
        
        $ts .= "declare const M: any; // Moodle global object\n";
        $ts .= "declare const Y: any; // YUI object\n\n";
        
        $ts .= $js;
        
        return $ts;
    }

    /**
     * Convert layouts
     */
    protected function convertLayouts(): void
    {
        $layoutPath = $this->moodleThemePath . '/layout';
        
        if (!File::exists($layoutPath)) {
            $this->log('No layout directory found, creating default layouts');
            $this->createDefaultLayouts();
            return;
        }

        $outputLayoutPath = $this->outputPath . '/layouts';
        File::ensureDirectoryExists($outputLayoutPath);

        $layoutFiles = File::files($layoutPath);

        foreach ($layoutFiles as $file) {
            if ($file->getExtension() === 'php') {
                $this->convertLayoutFile($file, $outputLayoutPath);
            }
        }
    }

    /**
     * Convert layout file
     */
    protected function convertLayoutFile(\SplFileInfo $file, string $outputPath): void
    {
        $content = File::get($file->getPathname());
        $fileName = $file->getFilenameWithoutExtension();
        
        $this->log("Converting layout: {$fileName}");

        // Convert PHP layout to Blade layout
        $bladeLayout = $this->phpLayoutToBlade($content);
        
        $bladeFile = $outputPath . "/{$fileName}.blade.php";
        File::put($bladeFile, $bladeLayout);
        
        $this->convertedFiles[] = $bladeFile;
    }

    /**
     * Convert PHP layout to Blade
     */
    protected function phpLayoutToBlade(string $php): string
    {
        // This is a complex conversion that would need careful analysis
        // For now, provide a basic structure
        
        return "{{-- Converted Moodle Layout --}}
<!DOCTYPE html>
<html lang=\"{{ app()->getLocale() }}\">
<head>
    <meta charset=\"utf-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">
    <title>@yield('title', config('app.name'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class=\"@yield('body-class')\">
    <div id=\"app\">
        @include('partials.header')
        
        <main class=\"main-content\">
            @yield('content')
        </main>
        
        @include('partials.footer')
    </div>
    
    @stack('scripts')
</body>
</html>";
    }

    /**
     * Generate React components
     */
    protected function generateReactComponents(): void
    {
        $reactPath = $this->outputPath . '/react/components';
        File::ensureDirectoryExists($reactPath);

        // Generate common Moodle components
        $this->generateMoodleReactComponents($reactPath);
    }

    /**
     * Generate common Moodle React components
     */
    protected function generateMoodleReactComponents(string $outputPath): void
    {
        $components = [
            'CourseHeader' => $this->generateCourseHeaderComponent(),
            'ActivityBlock' => $this->generateActivityBlockComponent(),
            'Navigation' => $this->generateNavigationComponent(),
            'UserMenu' => $this->generateUserMenuComponent(),
            'Block' => $this->generateBlockComponent(),
        ];

        foreach ($components as $name => $content) {
            $file = $outputPath . "/{$name}.tsx";
            File::put($file, $content);
            $this->convertedFiles[] = $file;
        }
    }

    /**
     * Generate course header component
     */
    protected function generateCourseHeaderComponent(): string
    {
        return "import React from 'react';

interface CourseHeaderProps {
  title: string;
  description?: string;
  imageUrl?: string;
  category?: string;
  enrollmentCount?: number;
}

const CourseHeader: React.FC<CourseHeaderProps> = ({
  title,
  description,
  imageUrl,
  category,
  enrollmentCount
}) => {
  return (
    <div className=\"moodle-course-header bg-gradient-to-r from-blue-600 to-blue-800 text-white rounded-lg overflow-hidden\">
      {imageUrl && (
        <div className=\"h-32 bg-cover bg-center\" style={{ backgroundImage: `url(\${imageUrl})` }} />
      )}
      <div className=\"p-6\">
        <div className=\"flex items-center justify-between\">
          <div>
            <h1 className=\"text-2xl font-bold mb-2\">{title}</h1>
            {category && (
              <span className=\"inline-block bg-blue-500 text-xs px-2 py-1 rounded\">{category}</span>
            )}
          </div>
          {enrollmentCount && (
            <div className=\"text-sm opacity-90\">
              {enrollmentCount} enrolled
            </div>
          )}
        </div>
        {description && (
          <p className=\"mt-4 opacity-90\">{description}</p>
        )}
      </div>
    </div>
  );
};

export default CourseHeader;";
    }

    /**
     * Generate activity block component
     */
    protected function generateActivityBlockComponent(): string
    {
        return "import React from 'react';

interface ActivityBlockProps {
  title: string;
  type: string;
  description?: string;
  dueDate?: string;
  completed?: boolean;
  onClick?: () => void;
}

const ActivityBlock: React.FC<ActivityBlockProps> = ({
  title,
  type,
  description,
  dueDate,
  completed = false,
  onClick
}) => {
  const getTypeIcon = (type: string) => {
    const icons = {
      assign: '📝',
      quiz: '❓',
      forum: '💬',
      resource: '📄',
      lesson: '📚',
    };
    return icons[type] || '📄';
  };

  return (
    <div 
      className={`moodle-activity border rounded-lg p-4 mb-4 cursor-pointer transition-all hover:shadow-md \${completed ? 'border-green-300 bg-green-50' : 'border-gray-300'}`}
      onClick={onClick}
    >
      <div className=\"flex items-start space-x-3\">
        <span className=\"text-2xl\">{getTypeIcon(type)}</span>
        <div className=\"flex-1\">
          <h3 className=\"font-semibold text-lg\">{title}</h3>
          <p className=\"text-sm text-gray-600 capitalize\">{type}</p>
          {description && (
            <p className=\"mt-2 text-sm text-gray-700\">{description}</p>
          )}
          {dueDate && (
            <p className=\"mt-2 text-sm text-orange-600\">Due: {dueDate}</p>
          )}
        </div>
        {completed && (
          <span className=\"text-green-600 font-semibold text-sm\">✓ Complete</span>
        )}
      </div>
    </div>
  );
};

export default ActivityBlock;";
    }

    // Continue with other component generators...
    protected function generateNavigationComponent(): string
    {
        return "import React from 'react';\n\n// Navigation component would go here\nexport default function Navigation() { return null; }";
    }

    protected function generateUserMenuComponent(): string
    {
        return "import React from 'react';\n\n// UserMenu component would go here\nexport default function UserMenu() { return null; }";
    }

    protected function generateBlockComponent(): string
    {
        return "import React from 'react';\n\n// Block component would go here\nexport default function Block() { return null; }";
    }

    /**
     * Create Laravel theme configuration
     */
    protected function createLaravelThemeConfig(array $moodleConfig): void
    {
        $laravelConfig = [
            'name' => $moodleConfig['name'],
            'description' => 'Converted from Moodle theme',
            'version' => '1.0.0',
            'author' => 'Theme Converter',
            'moodle_original' => true,
            'moodle_version' => $moodleConfig,
            'layouts' => $this->convertLayoutsConfig($moodleConfig['layouts']),
            'regions' => $this->extractRegions(),
            'assets' => [
                'css' => $this->getConvertedCssFiles(),
                'js' => $this->getConvertedJsFiles(),
            ],
            'features' => [
                'responsive' => true,
                'dark_mode' => false,
                'rtl_support' => false,
            ],
        ];

        $configFile = $this->outputPath . '/theme.json';
        File::put($configFile, json_encode($laravelConfig, JSON_PRETTY_PRINT));
        
        $this->convertedFiles[] = $configFile;
    }

    /**
     * Convert layouts configuration
     */
    protected function convertLayoutsConfig(array $layouts): array
    {
        $converted = [];
        
        foreach ($layouts as $name => $config) {
            $converted[$name] = [
                'file' => $name . '.blade.php',
                'regions' => $config['regions'] ?? [],
                'options' => $config['options'] ?? [],
            ];
        }
        
        return $converted;
    }

    /**
     * Extract theme regions
     */
    protected function extractRegions(): array
    {
        return [
            'header' => 'Header Region',
            'content' => 'Main Content',
            'sidebar' => 'Sidebar',
            'footer' => 'Footer Region',
        ];
    }

    /**
     * Get converted CSS files list
     */
    protected function getConvertedCssFiles(): array
    {
        return array_filter($this->convertedFiles, function($file) {
            return Str::endsWith($file, '.css');
        });
    }

    /**
     * Get converted JS files list
     */
    protected function getConvertedJsFiles(): array
    {
        return array_filter($this->convertedFiles, function($file) {
            return Str::endsWith($file, ['.js', '.ts']);
        });
    }

    /**
     * Generate asset manifest
     */
    protected function generateAssetManifest(): void
    {
        $manifest = [
            'theme_name' => basename($this->moodleThemePath),
            'converted_at' => now()->toISOString(),
            'files' => $this->convertedFiles,
            'conversion_log' => $this->conversionLog,
            'errors' => $this->errors,
        ];

        $manifestFile = $this->outputPath . '/conversion_manifest.json';
        File::put($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT));
    }

    /**
     * Create default layouts if none exist
     */
    protected function createDefaultLayouts(): void
    {
        $outputLayoutPath = $this->outputPath . '/layouts';
        File::ensureDirectoryExists($outputLayoutPath);

        $defaultLayout = "<!DOCTYPE html>
<html lang=\"{{ app()->getLocale() }}\">
<head>
    <meta charset=\"utf-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">
    <title>@yield('title', 'LMS Platform')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div id=\"app\">
        @yield('content')
    </div>
</body>
</html>";

        File::put($outputLayoutPath . '/app.blade.php', $defaultLayout);
        $this->convertedFiles[] = $outputLayoutPath . '/app.blade.php';
    }

    /**
     * Log conversion activity
     */
    protected function log(string $message): void
    {
        $this->conversionLog[] = '[' . now()->format('H:i:s') . '] ' . $message;
    }

    /**
     * Log conversion error
     */
    protected function error(string $message): void
    {
        $this->errors[] = '[' . now()->format('H:i:s') . '] ERROR: ' . $message;
        $this->log('ERROR: ' . $message);
    }

    /**
     * Get conversion results
     */
    public function getConversionResults(): array
    {
        return [
            'converted_files' => $this->convertedFiles,
            'log' => $this->conversionLog,
            'errors' => $this->errors,
        ];
    }
}
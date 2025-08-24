<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Core\Theme\ThemeConverter;
use Illuminate\Support\Facades\File;

class ConvertMoodleTheme extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'moodle:convert-theme 
                          {theme-path : Path to the Moodle theme directory}
                          {--output= : Output directory (defaults to resources/themes/[theme-name])}
                          {--force : Overwrite existing output directory}
                          {--preview : Generate preview images}
                          {--validate : Validate theme before conversion}';

    /**
     * The console command description.
     */
    protected $description = 'Convert a Moodle theme to Laravel Blade and React/Vue compatible format';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $themePath = $this->argument('theme-path');
        
        if (!File::exists($themePath)) {
            $this->error("Theme directory does not exist: {$themePath}");
            return Command::FAILURE;
        }

        if (!File::exists($themePath . '/config.php')) {
            $this->error("Invalid Moodle theme: config.php not found in {$themePath}");
            return Command::FAILURE;
        }

        $themeName = basename($themePath);
        $outputPath = $this->option('output') ?? resource_path("themes/{$themeName}");

        if (File::exists($outputPath) && !$this->option('force')) {
            if (!$this->confirm("Output directory {$outputPath} already exists. Overwrite?")) {
                $this->info('Conversion cancelled.');
                return Command::SUCCESS;
            }
        }

        $this->info("Converting Moodle theme: {$themeName}");
        $this->info("Source: {$themePath}");
        $this->info("Output: {$outputPath}");

        // Validate theme if requested
        if ($this->option('validate')) {
            $this->info('Validating theme...');
            if (!$this->validateTheme($themePath)) {
                return Command::FAILURE;
            }
            $this->info('✓ Theme validation passed');
        }

        // Ensure output directory
        if (!File::exists($outputPath)) {
            File::makeDirectory($outputPath, 0755, true);
        }

        // Create progress bar
        $this->info('Starting conversion...');
        $progressBar = $this->output->createProgressBar(6);
        
        try {
            $converter = new ThemeConverter($themePath, $outputPath);
            
            $progressBar->setMessage('Parsing theme configuration...');
            $progressBar->advance();
            
            $progressBar->setMessage('Converting templates...');
            $progressBar->advance();
            
            $progressBar->setMessage('Converting styles...');
            $progressBar->advance();
            
            $progressBar->setMessage('Converting JavaScript...');
            $progressBar->advance();
            
            $progressBar->setMessage('Generating React components...');
            $progressBar->advance();
            
            $progressBar->setMessage('Finalizing...');
            $result = $converter->convertTheme();
            $progressBar->finish();

            $this->newLine(2);

            if ($result['success']) {
                $this->info('✓ Theme conversion completed successfully!');
                
                // Show conversion summary
                $this->showConversionSummary($result);
                
                // Generate preview if requested
                if ($this->option('preview')) {
                    $this->generatePreview($outputPath, $themeName);
                }
                
                // Show next steps
                $this->showNextSteps($outputPath, $themeName);
                
                return Command::SUCCESS;
            } else {
                $this->error('✗ Theme conversion failed: ' . $result['error']);
                $this->showErrors($result['errors']);
                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $progressBar->finish();
            $this->newLine();
            $this->error('Conversion failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Validate Moodle theme
     */
    protected function validateTheme(string $themePath): bool
    {
        $requiredFiles = [
            'config.php' => 'Theme configuration',
            'version.php' => 'Theme version information',
        ];

        $warnings = [];
        $errors = [];

        foreach ($requiredFiles as $file => $description) {
            if (!File::exists($themePath . '/' . $file)) {
                if ($file === 'config.php') {
                    $errors[] = "Missing required file: {$file} ({$description})";
                } else {
                    $warnings[] = "Missing recommended file: {$file} ({$description})";
                }
            }
        }

        $recommendedDirs = [
            'templates' => 'Mustache templates',
            'scss' => 'SCSS stylesheets',
            'layout' => 'Layout files',
            'lang' => 'Language files',
        ];

        foreach ($recommendedDirs as $dir => $description) {
            if (!File::exists($themePath . '/' . $dir)) {
                $warnings[] = "Missing recommended directory: {$dir} ({$description})";
            }
        }

        // Show warnings
        if (!empty($warnings)) {
            $this->warn('Validation warnings:');
            foreach ($warnings as $warning) {
                $this->line('  • ' . $warning);
            }
        }

        // Show errors
        if (!empty($errors)) {
            $this->error('Validation errors:');
            foreach ($errors as $error) {
                $this->line('  • ' . $error);
            }
            return false;
        }

        return true;
    }

    /**
     * Show conversion summary
     */
    protected function showConversionSummary(array $result): void
    {
        $this->newLine();
        $this->info('Conversion Summary:');
        $this->line('─────────────────');
        
        $fileTypes = [
            'blade.php' => 'Blade Templates',
            'css' => 'CSS Files',
            'js' => 'JavaScript Files',
            'tsx' => 'React Components',
            'json' => 'Configuration Files',
        ];

        foreach ($fileTypes as $extension => $label) {
            $count = count(array_filter($result['converted_files'], function($file) use ($extension) {
                return str_ends_with($file, '.' . $extension);
            }));
            
            if ($count > 0) {
                $this->line("• {$label}: {$count} files");
            }
        }

        $this->newLine();
        $this->line('Total files converted: ' . count($result['converted_files']));

        if (!empty($result['errors'])) {
            $this->newLine();
            $this->warn('Conversion completed with ' . count($result['errors']) . ' warnings/errors.');
        }
    }

    /**
     * Show conversion errors
     */
    protected function showErrors(array $errors): void
    {
        if (empty($errors)) {
            return;
        }

        $this->newLine();
        $this->error('Conversion Errors:');
        foreach ($errors as $error) {
            $this->line('  • ' . $error);
        }
    }

    /**
     * Generate preview images
     */
    protected function generatePreview(string $outputPath, string $themeName): void
    {
        $this->info('Generating theme preview...');
        
        // In a real implementation, this would:
        // 1. Start a temporary Laravel server
        // 2. Take screenshots of key pages
        // 3. Generate a preview gallery
        
        $previewPath = $outputPath . '/preview';
        File::ensureDirectoryExists($previewPath);
        
        // Create a simple preview HTML file
        $previewHtml = "<!DOCTYPE html>
<html>
<head>
    <title>{$themeName} - Theme Preview</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1200px; margin: 0 auto; padding: 20px; }
        .preview-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .preview-item { border: 1px solid #ddd; border-radius: 8px; overflow: hidden; }
        .preview-title { background: #f5f5f5; padding: 10px; font-weight: bold; }
    </style>
</head>
<body>
    <h1>{$themeName} Theme Preview</h1>
    <p>This theme has been converted from Moodle to Laravel Blade and React components.</p>
    
    <div class='preview-grid'>
        <div class='preview-item'>
            <div class='preview-title'>Course Page</div>
            <div style='height: 200px; background: #f0f0f0; display: flex; align-items: center; justify-content: center;'>
                Screenshot would appear here
            </div>
        </div>
        <div class='preview-item'>
            <div class='preview-title'>Dashboard</div>
            <div style='height: 200px; background: #f0f0f0; display: flex; align-items: center; justify-content: center;'>
                Screenshot would appear here
            </div>
        </div>
    </div>
    
    <h2>Converted Files</h2>
    <ul>
        <li>Blade templates in templates/</li>
        <li>React components in react/components/</li>
        <li>Converted CSS in styles/</li>
        <li>Theme configuration in theme.json</li>
    </ul>
</body>
</html>";
        
        File::put($previewPath . '/index.html', $previewHtml);
        $this->info("Preview generated: {$previewPath}/index.html");
    }

    /**
     * Show next steps
     */
    protected function showNextSteps(string $outputPath, string $themeName): void
    {
        $this->newLine();
        $this->info('Next Steps:');
        $this->line('──────────');
        $this->line('1. Review converted files in: ' . $outputPath);
        $this->line('2. Install dependencies: npm install');
        $this->line('3. Compile assets: npm run build');
        $this->line('4. Test the theme in your application');
        $this->line('5. Customize styles and components as needed');
        
        $this->newLine();
        $this->info('Theme Registration:');
        $this->line("Add to your theme configuration:");
        $this->line("'{$themeName}' => [");
        $this->line("    'name' => '{$themeName}',");
        $this->line("    'path' => 'themes/{$themeName}',");
        $this->line("    'converted_from_moodle' => true,");
        $this->line("],");
        
        $this->newLine();
        $this->info('✓ Theme conversion completed!');
    }
}
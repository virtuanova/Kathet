<?php

namespace MoodlePlugin\Block\Navigation;

use App\Core\Plugin\Contracts\BlockInterface;
use Illuminate\Support\Facades\DB;

/**
 * Navigation Block
 */
class NavigationBlock implements BlockInterface
{
    protected array $config = [];
    
    public function getName(): string
    {
        return 'navigation';
    }
    
    public function getType(): string
    {
        return 'block';
    }
    
    public function getVersion(): string
    {
        return '2024012400';
    }
    
    public function getTitle(): string
    {
        return 'Navigation';
    }
    
    public function getDescription(): string
    {
        return 'Provides navigation links for the site and courses';
    }
    
    public function getContent(array $config = []): array
    {
        $context = $config['context'] ?? [];
        $courseId = $context['course_id'] ?? null;
        
        $navigation = [];
        
        // Site navigation
        $navigation['site'] = [
            'Dashboard' => route('dashboard'),
            'Courses' => route('courses.index'),
            'Users' => route('users.index'),
        ];
        
        // Course navigation if in course context
        if ($courseId) {
            $course = DB::table('mdl_course')->where('id', $courseId)->first();
            if ($course) {
                $navigation['course'] = [
                    'Course Home' => route('courses.show', $courseId),
                    'Participants' => route('courses.participants', $courseId),
                    'Grades' => route('courses.grades', $courseId),
                    'Files' => route('courses.files', $courseId),
                ];
                
                // Get course sections
                $sections = DB::table('mdl_course_sections')
                    ->where('course', $courseId)
                    ->where('visible', 1)
                    ->orderBy('section')
                    ->get();
                
                $navigation['sections'] = [];
                foreach ($sections as $section) {
                    if ($section->section > 0) { // Skip general section
                        $sectionName = $section->name ?: "Topic {$section->section}";
                        $navigation['sections'][$sectionName] = route('courses.section', [$courseId, $section->id]);
                    }
                }
            }
        }
        
        return $navigation;
    }
    
    public function getHtml(array $config = []): string
    {
        $content = $this->getContent($config);
        
        $html = '<div class="navigation-block">';
        
        // Site navigation
        if (!empty($content['site'])) {
            $html .= '<div class="nav-section">';
            $html .= '<h4 class="nav-heading">Site</h4>';
            $html .= '<ul class="nav-list">';
            
            foreach ($content['site'] as $label => $url) {
                $html .= "<li><a href=\"{$url}\">{$label}</a></li>";
            }
            
            $html .= '</ul>';
            $html .= '</div>';
        }
        
        // Course navigation
        if (!empty($content['course'])) {
            $html .= '<div class="nav-section">';
            $html .= '<h4 class="nav-heading">Course</h4>';
            $html .= '<ul class="nav-list">';
            
            foreach ($content['course'] as $label => $url) {
                $html .= "<li><a href=\"{$url}\">{$label}</a></li>";
            }
            
            $html .= '</ul>';
            $html .= '</div>';
        }
        
        // Course sections
        if (!empty($content['sections'])) {
            $html .= '<div class="nav-section">';
            $html .= '<h4 class="nav-heading">Course Content</h4>';
            $html .= '<ul class="nav-list">';
            
            foreach ($content['sections'] as $label => $url) {
                $html .= "<li><a href=\"{$url}\">{$label}</a></li>";
            }
            
            $html .= '</ul>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    public function shouldShow(string $pageType, array $context = []): bool
    {
        // Navigation block shows on most pages
        $hiddenPages = ['login', 'register'];
        return !in_array($pageType, $hiddenPages);
    }
    
    public function getSupportedPageTypes(): array
    {
        return ['*']; // All page types
    }
    
    public function getSupportedRegions(): array
    {
        return ['side-pre', 'side-post', 'content'];
    }
    
    public function hasConfig(): bool
    {
        return true;
    }
    
    public function getConfigSchema(): array
    {
        return [
            'show_site_nav' => [
                'type' => 'checkbox',
                'label' => 'Show site navigation',
                'default' => true,
            ],
            'show_course_nav' => [
                'type' => 'checkbox', 
                'label' => 'Show course navigation',
                'default' => true,
            ],
            'show_sections' => [
                'type' => 'checkbox',
                'label' => 'Show course sections',
                'default' => true,
            ],
            'max_sections' => [
                'type' => 'number',
                'label' => 'Maximum sections to show',
                'default' => 10,
                'min' => 1,
                'max' => 50,
            ],
        ];
    }
    
    public function supportsMultipleInstances(): bool
    {
        return false;
    }
    
    public function getReactComponent(): ?string
    {
        return 'NavigationBlock';
    }
    
    public function getApiData(array $config = []): array
    {
        return [
            'navigation' => $this->getContent($config),
            'config' => array_merge([
                'show_site_nav' => true,
                'show_course_nav' => true,
                'show_sections' => true,
                'max_sections' => 10,
            ], $config),
        ];
    }
    
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }
    
    public function init(): void
    {
        // Initialize navigation block
    }
    
    public function install(): bool
    {
        return true;
    }
    
    public function uninstall(): bool
    {
        return true;
    }
    
    public function upgrade(?string $oldVersion = null): bool
    {
        return true;
    }
    
    public function getDependencies(): array
    {
        return [];
    }
    
    public function isCompatible(): bool
    {
        return true;
    }
    
    public function getSettings(): array
    {
        return $this->config;
    }
    
    public function updateSettings(array $settings): bool
    {
        $this->config = array_merge($this->config, $settings);
        return true;
    }
    
    public function getConfigForm(): ?string
    {
        return '<form class="navigation-config-form">Navigation config form</form>';
    }
    
    public function processConfig(array $data): bool
    {
        return true;
    }
    
    public function getConfig(): array
    {
        return $this->config;
    }
    
    public function isConfigurablePerInstance(): bool
    {
        return true;
    }
    
    public function canDock(): bool
    {
        return true;
    }
    
    public function getDefaultRegion(): string
    {
        return 'side-pre';
    }
    
    public function getDefaultWeight(): int
    {
        return 0;
    }
    
    public function handleAjax(string $action, array $params): array
    {
        return ['success' => true, 'action' => $action];
    }
    
    public function getCssFiles(): array
    {
        return ['navigation.css'];
    }
    
    public function getJsFiles(): array
    {
        return ['navigation.js'];
    }
    
    public function getInstanceData(int $instanceId): array
    {
        return ['instance_id' => $instanceId];
    }
    
    public function createInstance(array $data): int
    {
        return 1; // Mock implementation
    }
    
    public function updateInstance(int $instanceId, array $data): bool
    {
        return true;
    }
    
    public function deleteInstance(int $instanceId): bool
    {
        return true;
    }
    
    public function getRequiredCapabilities(): array
    {
        return ['block/navigation:view'];
    }
    
    public function checkPermissions(int $userId, string $action): bool
    {
        return true; // Mock implementation
    }
    
    public function getCacheSettings(): array
    {
        return ['ttl' => 300, 'key' => 'navigation_block'];
    }
    
    public function getAccessibilityInfo(): array
    {
        return [
            'role' => 'navigation',
            'label' => 'Site navigation',
            'description' => 'Navigation links for the site and courses',
        ];
    }
}
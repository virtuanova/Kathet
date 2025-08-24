<?php

namespace Tests\Accessibility;

use DOMDocument;
use DOMXPath;
use DOMElement;

/**
 * WCAG 2.0/2.1 Accessibility Auditor
 * 
 * Automated testing tool for accessibility compliance
 * Similar to Moodle's accessibility checking but more comprehensive
 */
class AccessibilityAuditor
{
    protected array $violations = [];
    protected array $warnings = [];
    protected array $recommendations = [];
    protected int $totalElements = 0;
    protected array $testedElements = [];

    protected const WCAG_LEVELS = ['A', 'AA', 'AAA'];
    protected const VIOLATION_TYPES = [
        'MISSING_ALT_TEXT' => 'Images must have alternative text',
        'MISSING_LABEL' => 'Form elements must have labels',
        'INSUFFICIENT_CONTRAST' => 'Text must have sufficient color contrast',
        'MISSING_HEADING_STRUCTURE' => 'Headings must follow logical structure',
        'MISSING_LANG_ATTRIBUTE' => 'Page must have language attribute',
        'INVALID_HTML' => 'HTML must be valid',
        'KEYBOARD_INACCESSIBLE' => 'Elements must be keyboard accessible',
        'MISSING_ARIA_LABEL' => 'Interactive elements need accessible names',
        'INVALID_ARIA' => 'ARIA attributes must be valid',
        'FOCUS_ORDER' => 'Focus order must be logical',
        'MISSING_SKIP_LINKS' => 'Page should have skip links',
        'INSUFFICIENT_TARGET_SIZE' => 'Touch targets must be at least 44px',
        'MISSING_ERROR_IDENTIFICATION' => 'Errors must be clearly identified',
        'MISSING_INSTRUCTIONS' => 'Complex forms need instructions',
        'MOVING_CONTENT' => 'Moving content must be controllable',
        'FLASHING_CONTENT' => 'Flashing content can cause seizures',
        'TIMEOUT_WARNING' => 'Timeouts must warn users',
        'CONTEXT_CHANGE' => 'Unexpected context changes are problematic',
    ];

    public function auditHtml(string $html, string $wcagLevel = 'AA'): array
    {
        $this->resetAudit();
        
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // Count total elements for coverage statistics
        $this->totalElements = $xpath->query('//*')->length;
        
        // Run all accessibility checks
        $this->checkImages($xpath);
        $this->checkFormElements($xpath);
        $this->checkHeadingStructure($xpath);
        $this->checkLanguageAttributes($dom);
        $this->checkKeyboardAccessibility($xpath);
        $this->checkAriaAttributes($xpath);
        $this->checkSkipLinks($xpath);
        $this->checkTouchTargets($xpath);
        $this->checkErrorHandling($xpath);
        $this->checkMovingContent($xpath);
        $this->checkColorContrast($xpath);
        $this->checkFocusManagement($xpath);
        $this->checkTableAccessibility($xpath);
        $this->checkListStructure($xpath);
        $this->checkMultimedia($xpath);
        
        if ($wcagLevel === 'AAA') {
            $this->checkAAACompliance($xpath);
        }
        
        return $this->generateReport();
    }

    public function auditUrl(string $url, string $wcagLevel = 'AA'): array
    {
        $html = file_get_contents($url);
        if ($html === false) {
            throw new \Exception("Could not fetch content from URL: $url");
        }
        
        return $this->auditHtml($html, $wcagLevel);
    }

    public function auditFile(string $filePath, string $wcagLevel = 'AA'): array
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found: $filePath");
        }
        
        $html = file_get_contents($filePath);
        return $this->auditHtml($html, $wcagLevel);
    }

    protected function resetAudit(): void
    {
        $this->violations = [];
        $this->warnings = [];
        $this->recommendations = [];
        $this->totalElements = 0;
        $this->testedElements = [];
    }

    protected function checkImages(DOMXPath $xpath): void
    {
        $images = $xpath->query('//img');
        
        foreach ($images as $img) {
            $this->testedElements[] = $this->getElementSignature($img);
            
            $alt = $img->getAttribute('alt');
            $src = $img->getAttribute('src');
            
            // Missing alt attribute
            if (!$img->hasAttribute('alt')) {
                $this->addViolation(
                    'MISSING_ALT_TEXT',
                    $img,
                    'Image missing alt attribute',
                    'A'
                );
            }
            // Decorative images should have empty alt
            elseif ($alt === '' && !$this->isDecorativeImage($img)) {
                $this->addWarning(
                    'MISSING_ALT_TEXT',
                    $img,
                    'Image has empty alt text - ensure it is decorative'
                );
            }
            // Alt text should not be redundant
            elseif ($this->isRedundantAltText($alt, $img)) {
                $this->addRecommendation(
                    'REDUNDANT_ALT_TEXT',
                    $img,
                    'Alt text may be redundant with surrounding text'
                );
            }
            
            // Complex images need long descriptions
            if ($this->isComplexImage($img) && !$this->hasLongDescription($img)) {
                $this->addViolation(
                    'MISSING_LONG_DESCRIPTION',
                    $img,
                    'Complex image needs long description',
                    'A'
                );
            }
        }
    }

    protected function checkFormElements(DOMXPath $xpath): void
    {
        $formElements = $xpath->query('//input[@type!="hidden"] | //select | //textarea');
        
        foreach ($formElements as $element) {
            $this->testedElements[] = $this->getElementSignature($element);
            
            $id = $element->getAttribute('id');
            $name = $element->getAttribute('name');
            $type = $element->getAttribute('type');
            $required = $element->hasAttribute('required');
            
            // Check for labels
            if (!$this->hasAssociatedLabel($element, $xpath)) {
                $this->addViolation(
                    'MISSING_LABEL',
                    $element,
                    'Form element missing associated label',
                    'A'
                );
            }
            
            // Check for fieldset/legend in radio/checkbox groups
            if (in_array($type, ['radio', 'checkbox']) && !$this->hasFieldsetLegend($element, $xpath)) {
                $this->addViolation(
                    'MISSING_FIELDSET',
                    $element,
                    'Radio/checkbox group needs fieldset with legend',
                    'A'
                );
            }
            
            // Required field indication
            if ($required && !$this->hasRequiredIndication($element)) {
                $this->addWarning(
                    'MISSING_REQUIRED_INDICATION',
                    $element,
                    'Required field should be clearly indicated'
                );
            }
            
            // Error handling
            if ($this->hasErrorState($element) && !$this->hasErrorMessage($element, $xpath)) {
                $this->addViolation(
                    'MISSING_ERROR_IDENTIFICATION',
                    $element,
                    'Form errors must be clearly identified',
                    'A'
                );
            }
        }
        
        // Check for form instructions
        $forms = $xpath->query('//form');
        foreach ($forms as $form) {
            if ($this->isComplexForm($form) && !$this->hasInstructions($form)) {
                $this->addRecommendation(
                    'MISSING_INSTRUCTIONS',
                    $form,
                    'Complex forms should provide instructions'
                );
            }
        }
    }

    protected function checkHeadingStructure(DOMXPath $xpath): void
    {
        $headings = $xpath->query('//h1 | //h2 | //h3 | //h4 | //h5 | //h6');
        $headingLevels = [];
        
        foreach ($headings as $heading) {
            $level = (int) substr($heading->nodeName, 1);
            $headingLevels[] = $level;
            $this->testedElements[] = $this->getElementSignature($heading);
        }
        
        if (empty($headingLevels)) {
            $this->addWarning(
                'MISSING_HEADINGS',
                null,
                'Page should have heading structure for navigation'
            );
            return;
        }
        
        // Check if page starts with h1
        if ($headingLevels[0] !== 1) {
            $this->addViolation(
                'MISSING_H1',
                null,
                'Page should start with h1 heading',
                'A'
            );
        }
        
        // Check for skipped heading levels
        for ($i = 1; $i < count($headingLevels); $i++) {
            $current = $headingLevels[$i];
            $previous = $headingLevels[$i - 1];
            
            if ($current > $previous + 1) {
                $this->addViolation(
                    'MISSING_HEADING_STRUCTURE',
                    null,
                    "Heading level skipped from h{$previous} to h{$current}",
                    'A'
                );
            }
        }
        
        // Check for multiple h1s
        $h1Count = count(array_filter($headingLevels, fn($level) => $level === 1));
        if ($h1Count > 1) {
            $this->addWarning(
                'MULTIPLE_H1',
                null,
                'Page has multiple h1 headings - consider using h2+'
            );
        }
    }

    protected function checkLanguageAttributes(DOMDocument $dom): void
    {
        $html = $dom->documentElement;
        
        if (!$html || !$html->hasAttribute('lang')) {
            $this->addViolation(
                'MISSING_LANG_ATTRIBUTE',
                null,
                'HTML element must have lang attribute',
                'A'
            );
        }
        
        // Check for language changes
        $xpath = new DOMXPath($dom);
        $elementsWithLang = $xpath->query('//*[@lang]');
        
        foreach ($elementsWithLang as $element) {
            $lang = $element->getAttribute('lang');
            if (!$this->isValidLanguageCode($lang)) {
                $this->addViolation(
                    'INVALID_LANG_CODE',
                    $element,
                    "Invalid language code: $lang",
                    'A'
                );
            }
        }
    }

    protected function checkKeyboardAccessibility(DOMXPath $xpath): void
    {
        $interactiveElements = $xpath->query('//a | //button | //input | //select | //textarea | //*[@onclick] | //*[@tabindex]');
        
        foreach ($interactiveElements as $element) {
            $this->testedElements[] = $this->getElementSignature($element);
            
            $tagName = strtolower($element->nodeName);
            $tabindex = $element->getAttribute('tabindex');
            $onclick = $element->hasAttribute('onclick');
            $href = $element->getAttribute('href');
            
            // Elements with onclick should be keyboard accessible
            if ($onclick && !$this->isKeyboardAccessible($element)) {
                $this->addViolation(
                    'KEYBOARD_INACCESSIBLE',
                    $element,
                    'Interactive element not keyboard accessible',
                    'A'
                );
            }
            
            // Links without href should not be in tab order
            if ($tagName === 'a' && !$href && $tabindex !== '-1') {
                $this->addViolation(
                    'INVALID_LINK',
                    $element,
                    'Links without href should have tabindex="-1"',
                    'A'
                );
            }
            
            // Positive tabindex is problematic
            if ($tabindex && (int)$tabindex > 0) {
                $this->addWarning(
                    'POSITIVE_TABINDEX',
                    $element,
                    'Positive tabindex values can disrupt natural tab order'
                );
            }
        }
    }

    protected function checkAriaAttributes(DOMXPath $xpath): void
    {
        $elementsWithAria = $xpath->query('//*[starts-with(name(@*), "aria-")]');
        
        foreach ($elementsWithAria as $element) {
            $this->testedElements[] = $this->getElementSignature($element);
            
            foreach ($element->attributes as $attr) {
                if (strpos($attr->name, 'aria-') === 0) {
                    $ariaName = $attr->name;
                    $ariaValue = $attr->value;
                    
                    // Check for valid ARIA attributes
                    if (!$this->isValidAriaAttribute($ariaName)) {
                        $this->addViolation(
                            'INVALID_ARIA',
                            $element,
                            "Invalid ARIA attribute: $ariaName",
                            'A'
                        );
                    }
                    
                    // Check for valid ARIA values
                    if (!$this->isValidAriaValue($ariaName, $ariaValue)) {
                        $this->addViolation(
                            'INVALID_ARIA',
                            $element,
                            "Invalid ARIA value '$ariaValue' for $ariaName",
                            'A'
                        );
                    }
                }
            }
            
            // Check for missing accessible names
            if ($this->needsAccessibleName($element) && !$this->hasAccessibleName($element, $xpath)) {
                $this->addViolation(
                    'MISSING_ARIA_LABEL',
                    $element,
                    'Interactive element needs accessible name',
                    'A'
                );
            }
        }
        
        // Check for ARIA landmarks
        $landmarks = $xpath->query('//*[@role="main" or @role="navigation" or @role="banner" or @role="contentinfo"]');
        if ($landmarks->length === 0) {
            $main = $xpath->query('//main');
            $nav = $xpath->query('//nav');
            $header = $xpath->query('//header');
            $footer = $xpath->query('//footer');
            
            if ($main->length === 0) {
                $this->addRecommendation(
                    'MISSING_MAIN_LANDMARK',
                    null,
                    'Page should have main landmark or main element'
                );
            }
        }
    }

    protected function checkSkipLinks(DOMXPath $xpath): void
    {
        $skipLinks = $xpath->query('//a[contains(@class, "skip") or contains(@href, "#main") or contains(@href, "#content")]');
        
        if ($skipLinks->length === 0) {
            $this->addRecommendation(
                'MISSING_SKIP_LINKS',
                null,
                'Page should provide skip links for keyboard users'
            );
        } else {
            foreach ($skipLinks as $link) {
                $href = $link->getAttribute('href');
                $target = substr($href, 1); // Remove #
                
                if ($target) {
                    $targetElement = $xpath->query("//*[@id='$target']")->item(0);
                    if (!$targetElement) {
                        $this->addViolation(
                            'BROKEN_SKIP_LINK',
                            $link,
                            "Skip link target '$target' not found",
                            'A'
                        );
                    }
                }
            }
        }
    }

    protected function checkTouchTargets(DOMXPath $xpath): void
    {
        $touchTargets = $xpath->query('//a | //button | //input[@type="button" or @type="submit"] | //*[@onclick]');
        
        foreach ($touchTargets as $element) {
            $this->testedElements[] = $this->getElementSignature($element);
            
            // This would need CSS parsing to check actual dimensions
            // For now, we check for obvious issues
            $style = $element->getAttribute('style');
            if ($this->hasSmallerTouchTarget($style)) {
                $this->addViolation(
                    'INSUFFICIENT_TARGET_SIZE',
                    $element,
                    'Touch targets should be at least 44x44 pixels',
                    'AA'
                );
            }
        }
    }

    protected function checkErrorHandling(DOMXPath $xpath): void
    {
        $errorElements = $xpath->query('//*[contains(@class, "error") or contains(@class, "invalid") or @aria-invalid="true"]');
        
        foreach ($errorElements as $element) {
            $this->testedElements[] = $this->getElementSignature($element);
            
            if (!$this->hasErrorMessage($element, $xpath)) {
                $this->addViolation(
                    'MISSING_ERROR_IDENTIFICATION',
                    $element,
                    'Error state must be clearly explained',
                    'A'
                );
            }
        }
    }

    protected function checkMovingContent(DOMXPath $xpath): void
    {
        $movingElements = $xpath->query('//*[contains(@class, "carousel") or contains(@class, "slider") or contains(@class, "marquee")]');
        
        foreach ($movingElements as $element) {
            $this->testedElements[] = $this->getElementSignature($element);
            
            if (!$this->hasPlayPauseControls($element)) {
                $this->addViolation(
                    'MOVING_CONTENT',
                    $element,
                    'Moving content must have pause controls',
                    'A'
                );
            }
        }
        
        // Check for auto-refresh
        $metaRefresh = $xpath->query('//meta[@http-equiv="refresh"]');
        if ($metaRefresh->length > 0) {
            $this->addViolation(
                'AUTO_REFRESH',
                $metaRefresh->item(0),
                'Automatic page refresh should be avoidable',
                'AA'
            );
        }
    }

    protected function checkColorContrast(DOMXPath $xpath): void
    {
        // This is a simplified check - full color contrast checking requires CSS parsing
        $textElements = $xpath->query('//p | //h1 | //h2 | //h3 | //h4 | //h5 | //h6 | //span | //div[text()]');
        
        foreach ($textElements as $element) {
            $style = $element->getAttribute('style');
            if ($this->hasLowContrast($style)) {
                $this->addViolation(
                    'INSUFFICIENT_CONTRAST',
                    $element,
                    'Text color contrast may be insufficient',
                    'AA'
                );
            }
        }
    }

    protected function checkFocusManagement(DOMXPath $xpath): void
    {
        // Check for focus traps in modals
        $modals = $xpath->query('//*[contains(@class, "modal") or @role="dialog"]');
        
        foreach ($modals as $modal) {
            if (!$this->hasFocusTrap($modal)) {
                $this->addRecommendation(
                    'MISSING_FOCUS_TRAP',
                    $modal,
                    'Modal dialogs should trap focus'
                );
            }
        }
    }

    protected function checkTableAccessibility(DOMXPath $xpath): void
    {
        $tables = $xpath->query('//table');
        
        foreach ($tables as $table) {
            $this->testedElements[] = $this->getElementSignature($table);
            
            $headers = $xpath->query('.//th', $table);
            $caption = $xpath->query('.//caption', $table);
            
            if ($headers->length === 0) {
                $this->addViolation(
                    'MISSING_TABLE_HEADERS',
                    $table,
                    'Data tables must have header cells',
                    'A'
                );
            }
            
            if ($this->isComplexTable($table) && $caption->length === 0) {
                $this->addViolation(
                    'MISSING_TABLE_CAPTION',
                    $table,
                    'Complex tables should have captions',
                    'A'
                );
            }
            
            // Check for proper header associations
            foreach ($headers as $header) {
                if ($this->isComplexTable($table) && !$header->hasAttribute('scope')) {
                    $this->addWarning(
                        'MISSING_HEADER_SCOPE',
                        $header,
                        'Complex table headers should have scope attribute'
                    );
                }
            }
        }
    }

    protected function checkListStructure(DOMXPath $xpath): void
    {
        $lists = $xpath->query('//ul | //ol');
        
        foreach ($lists as $list) {
            $listItems = $xpath->query('./li', $list);
            
            if ($listItems->length === 0) {
                $this->addViolation(
                    'EMPTY_LIST',
                    $list,
                    'Lists must contain list items',
                    'A'
                );
            }
        }
        
        // Check for orphaned list items
        $orphanedItems = $xpath->query('//li[not(parent::ul) and not(parent::ol)]');
        foreach ($orphanedItems as $item) {
            $this->addViolation(
                'ORPHANED_LIST_ITEM',
                $item,
                'List items must be inside ul or ol elements',
                'A'
            );
        }
    }

    protected function checkMultimedia(DOMXPath $xpath): void
    {
        $videos = $xpath->query('//video');
        $audios = $xpath->query('//audio');
        
        foreach ($videos as $video) {
            $this->testedElements[] = $this->getElementSignature($video);
            
            if (!$this->hasVideoControls($video)) {
                $this->addViolation(
                    'MISSING_VIDEO_CONTROLS',
                    $video,
                    'Video must have accessible controls',
                    'A'
                );
            }
            
            if (!$this->hasVideoTranscript($video)) {
                $this->addRecommendation(
                    'MISSING_VIDEO_TRANSCRIPT',
                    $video,
                    'Video should have transcript or captions'
                );
            }
        }
        
        foreach ($audios as $audio) {
            $this->testedElements[] = $this->getElementSignature($audio);
            
            if (!$this->hasAudioControls($audio)) {
                $this->addViolation(
                    'MISSING_AUDIO_CONTROLS',
                    $audio,
                    'Audio must have accessible controls',
                    'A'
                );
            }
        }
    }

    protected function checkAAACompliance(DOMXPath $xpath): void
    {
        // Additional AAA level checks
        $this->checkContextualHelp($xpath);
        $this->checkErrorPrevention($xpath);
        $this->checkTimingAdjustments($xpath);
    }

    protected function checkContextualHelp(DOMXPath $xpath): void
    {
        $complexForms = $xpath->query('//form[count(.//input | .//select | .//textarea) > 3]');
        
        foreach ($complexForms as $form) {
            if (!$this->hasContextualHelp($form)) {
                $this->addRecommendation(
                    'MISSING_CONTEXTUAL_HELP',
                    $form,
                    'Complex forms should provide contextual help (AAA)'
                );
            }
        }
    }

    protected function checkErrorPrevention(DOMXPath $xpath): void
    {
        $submitButtons = $xpath->query('//input[@type="submit"] | //button[@type="submit"]');
        
        foreach ($submitButtons as $button) {
            $form = $this->getParentForm($button);
            if ($form && $this->isImportantForm($form) && !$this->hasConfirmationStep($form)) {
                $this->addRecommendation(
                    'MISSING_ERROR_PREVENTION',
                    $form,
                    'Important forms should have confirmation steps (AAA)'
                );
            }
        }
    }

    protected function checkTimingAdjustments(DOMXPath $xpath): void
    {
        $timedElements = $xpath->query('//*[@data-timeout] | //*[contains(@class, "timeout")]');
        
        foreach ($timedElements as $element) {
            if (!$this->hasTimingAdjustments($element)) {
                $this->addRecommendation(
                    'MISSING_TIMING_ADJUSTMENTS',
                    $element,
                    'Timed content should allow timing adjustments (AAA)'
                );
            }
        }
    }

    // Helper methods
    protected function addViolation(string $type, ?DOMElement $element, string $message, string $wcagLevel = 'AA'): void
    {
        $this->violations[] = [
            'type' => $type,
            'message' => $message,
            'wcag_level' => $wcagLevel,
            'element' => $element ? $this->getElementSignature($element) : null,
            'line' => $element ? $element->getLineNo() : null,
        ];
    }

    protected function addWarning(string $type, ?DOMElement $element, string $message): void
    {
        $this->warnings[] = [
            'type' => $type,
            'message' => $message,
            'element' => $element ? $this->getElementSignature($element) : null,
            'line' => $element ? $element->getLineNo() : null,
        ];
    }

    protected function addRecommendation(string $type, ?DOMElement $element, string $message): void
    {
        $this->recommendations[] = [
            'type' => $type,
            'message' => $message,
            'element' => $element ? $this->getElementSignature($element) : null,
            'line' => $element ? $element->getLineNo() : null,
        ];
    }

    protected function getElementSignature(DOMElement $element): string
    {
        $signature = strtolower($element->nodeName);
        
        if ($element->hasAttribute('id')) {
            $signature .= '#' . $element->getAttribute('id');
        }
        
        if ($element->hasAttribute('class')) {
            $signature .= '.' . str_replace(' ', '.', $element->getAttribute('class'));
        }
        
        return $signature;
    }

    protected function generateReport(): array
    {
        $totalIssues = count($this->violations) + count($this->warnings);
        $coverage = $this->totalElements > 0 ? (count($this->testedElements) / $this->totalElements) * 100 : 0;
        
        $violationsByLevel = [];
        foreach ($this->violations as $violation) {
            $level = $violation['wcag_level'];
            if (!isset($violationsByLevel[$level])) {
                $violationsByLevel[$level] = 0;
            }
            $violationsByLevel[$level]++;
        }
        
        return [
            'summary' => [
                'total_elements' => $this->totalElements,
                'tested_elements' => count($this->testedElements),
                'coverage_percentage' => round($coverage, 2),
                'total_issues' => $totalIssues,
                'violations' => count($this->violations),
                'warnings' => count($this->warnings),
                'recommendations' => count($this->recommendations),
                'violations_by_level' => $violationsByLevel,
            ],
            'violations' => $this->violations,
            'warnings' => $this->warnings,
            'recommendations' => $this->recommendations,
            'tested_elements' => array_unique($this->testedElements),
            'generated_at' => date('c'),
        ];
    }

    // Utility methods for specific checks
    protected function isDecorativeImage(DOMElement $img): bool
    {
        $class = $img->getAttribute('class');
        return strpos($class, 'decorative') !== false || 
               strpos($class, 'icon') !== false;
    }

    protected function isRedundantAltText(string $alt, DOMElement $img): bool
    {
        // Check if alt text duplicates surrounding text
        $parent = $img->parentNode;
        if ($parent) {
            $text = trim($parent->textContent);
            return strpos($text, $alt) !== false;
        }
        return false;
    }

    protected function isComplexImage(DOMElement $img): bool
    {
        // Detect charts, graphs, etc.
        $src = $img->getAttribute('src');
        $alt = $img->getAttribute('alt');
        
        return strpos($src, 'chart') !== false || 
               strpos($src, 'graph') !== false ||
               strpos($alt, 'chart') !== false ||
               strpos($alt, 'graph') !== false;
    }

    protected function hasLongDescription(DOMElement $img): bool
    {
        return $img->hasAttribute('longdesc') || 
               $img->hasAttribute('aria-describedby');
    }

    protected function hasAssociatedLabel(DOMElement $element, DOMXPath $xpath): bool
    {
        $id = $element->getAttribute('id');
        
        // Check for explicit label
        if ($id) {
            $labels = $xpath->query("//label[@for='$id']");
            if ($labels->length > 0) return true;
        }
        
        // Check for implicit label (element inside label)
        $parent = $element->parentNode;
        while ($parent) {
            if (strtolower($parent->nodeName) === 'label') {
                return true;
            }
            $parent = $parent->parentNode;
        }
        
        // Check for aria-label or aria-labelledby
        return $element->hasAttribute('aria-label') || 
               $element->hasAttribute('aria-labelledby');
    }

    protected function hasFieldsetLegend(DOMElement $element, DOMXPath $xpath): bool
    {
        $parent = $element->parentNode;
        while ($parent) {
            if (strtolower($parent->nodeName) === 'fieldset') {
                $legends = $xpath->query('.//legend', $parent);
                return $legends->length > 0;
            }
            $parent = $parent->parentNode;
        }
        return false;
    }

    protected function hasRequiredIndication(DOMElement $element): bool
    {
        return $element->hasAttribute('aria-required') ||
               strpos($element->getAttribute('class'), 'required') !== false;
    }

    protected function hasErrorState(DOMElement $element): bool
    {
        return $element->hasAttribute('aria-invalid') ||
               strpos($element->getAttribute('class'), 'error') !== false ||
               strpos($element->getAttribute('class'), 'invalid') !== false;
    }

    protected function hasErrorMessage(DOMElement $element, DOMXPath $xpath): bool
    {
        if ($element->hasAttribute('aria-describedby')) {
            $describedBy = $element->getAttribute('aria-describedby');
            $errorElement = $xpath->query("//*[@id='$describedBy']")->item(0);
            return $errorElement && strpos($errorElement->getAttribute('class'), 'error') !== false;
        }
        return false;
    }

    protected function isComplexForm(DOMElement $form): bool
    {
        $xpath = new DOMXPath($form->ownerDocument);
        $inputs = $xpath->query('.//input | .//select | .//textarea', $form);
        return $inputs->length > 5;
    }

    protected function hasInstructions(DOMElement $form): bool
    {
        $xpath = new DOMXPath($form->ownerDocument);
        $instructions = $xpath->query('.//*[contains(@class, "instructions") or contains(@class, "help")]', $form);
        return $instructions->length > 0;
    }

    protected function isValidLanguageCode(string $lang): bool
    {
        // Simplified check for common language codes
        return preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $lang);
    }

    protected function isKeyboardAccessible(DOMElement $element): bool
    {
        $tagName = strtolower($element->nodeName);
        $tabindex = $element->getAttribute('tabindex');
        
        // Native interactive elements are keyboard accessible
        if (in_array($tagName, ['a', 'button', 'input', 'select', 'textarea'])) {
            return true;
        }
        
        // Elements with positive or zero tabindex are accessible
        if ($tabindex !== '' && (int)$tabindex >= 0) {
            return true;
        }
        
        // Elements with role that makes them interactive
        $role = $element->getAttribute('role');
        return in_array($role, ['button', 'link', 'menuitem', 'tab']);
    }

    protected function isValidAriaAttribute(string $ariaName): bool
    {
        $validAria = [
            'aria-activedescendant', 'aria-atomic', 'aria-autocomplete',
            'aria-busy', 'aria-checked', 'aria-controls', 'aria-describedby',
            'aria-disabled', 'aria-dropeffect', 'aria-expanded', 'aria-flowto',
            'aria-grabbed', 'aria-haspopup', 'aria-hidden', 'aria-invalid',
            'aria-label', 'aria-labelledby', 'aria-level', 'aria-live',
            'aria-multiline', 'aria-multiselectable', 'aria-orientation',
            'aria-owns', 'aria-posinset', 'aria-pressed', 'aria-readonly',
            'aria-relevant', 'aria-required', 'aria-selected', 'aria-setsize',
            'aria-sort', 'aria-valuemax', 'aria-valuemin', 'aria-valuenow',
            'aria-valuetext', 'role'
        ];
        
        return in_array($ariaName, $validAria);
    }

    protected function isValidAriaValue(string $ariaName, string $value): bool
    {
        $booleanAria = ['aria-atomic', 'aria-busy', 'aria-checked', 'aria-disabled', 
                       'aria-expanded', 'aria-grabbed', 'aria-hidden', 'aria-invalid',
                       'aria-multiline', 'aria-multiselectable', 'aria-pressed',
                       'aria-readonly', 'aria-required', 'aria-selected'];
        
        if (in_array($ariaName, $booleanAria)) {
            return in_array($value, ['true', 'false']);
        }
        
        return true; // Simplified validation
    }

    protected function needsAccessibleName(DOMElement $element): bool
    {
        $role = $element->getAttribute('role');
        $tagName = strtolower($element->nodeName);
        
        return in_array($role, ['button', 'link', 'menuitem', 'tab']) ||
               in_array($tagName, ['button', 'a', 'input']);
    }

    protected function hasAccessibleName(DOMElement $element, DOMXPath $xpath): bool
    {
        return $element->hasAttribute('aria-label') ||
               $element->hasAttribute('aria-labelledby') ||
               trim($element->textContent) !== '' ||
               $this->hasAssociatedLabel($element, $xpath);
    }

    protected function hasSmallerTouchTarget(string $style): bool
    {
        // Simplified check - would need CSS parser for accurate measurement
        return strpos($style, 'width') !== false && 
               preg_match('/width:\s*([0-9]+)px/', $style, $matches) &&
               (int)$matches[1] < 44;
    }

    protected function hasPlayPauseControls(DOMElement $element): bool
    {
        $xpath = new DOMXPath($element->ownerDocument);
        $controls = $xpath->query('.//*[contains(@class, "play") or contains(@class, "pause")]', $element);
        return $controls->length > 0;
    }

    protected function hasLowContrast(string $style): bool
    {
        // Simplified check - full contrast checking requires complex color calculations
        return strpos($style, 'color: #ccc') !== false ||
               strpos($style, 'color: #ddd') !== false;
    }

    protected function hasFocusTrap(DOMElement $modal): bool
    {
        // Check for focus trap indicators
        $class = $modal->getAttribute('class');
        return strpos($class, 'focus-trap') !== false;
    }

    protected function isComplexTable(DOMElement $table): bool
    {
        $xpath = new DOMXPath($table->ownerDocument);
        $rows = $xpath->query('.//tr', $table);
        $cols = $xpath->query('.//td | .//th', $table);
        
        return $rows->length > 5 || $cols->length > 20;
    }

    protected function hasVideoControls(DOMElement $video): bool
    {
        return $video->hasAttribute('controls');
    }

    protected function hasVideoTranscript(DOMElement $video): bool
    {
        // Check for transcript links or captions
        $xpath = new DOMXPath($video->ownerDocument);
        $transcript = $xpath->query('.//*[contains(@class, "transcript") or contains(text(), "transcript")]', $video->parentNode);
        $captions = $xpath->query('.//track[@kind="captions"]', $video);
        
        return $transcript->length > 0 || $captions->length > 0;
    }

    protected function hasAudioControls(DOMElement $audio): bool
    {
        return $audio->hasAttribute('controls');
    }

    protected function hasContextualHelp(DOMElement $form): bool
    {
        $xpath = new DOMXPath($form->ownerDocument);
        $help = $xpath->query('.//*[contains(@class, "help") or contains(@class, "hint")]', $form);
        return $help->length > 0;
    }

    protected function getParentForm(DOMElement $element): ?DOMElement
    {
        $parent = $element->parentNode;
        while ($parent) {
            if (strtolower($parent->nodeName) === 'form') {
                return $parent;
            }
            $parent = $parent->parentNode;
        }
        return null;
    }

    protected function isImportantForm(DOMElement $form): bool
    {
        // Check for payment, deletion, or other critical actions
        $action = $form->getAttribute('action');
        $class = $form->getAttribute('class');
        
        return strpos($action, 'delete') !== false ||
               strpos($action, 'payment') !== false ||
               strpos($class, 'critical') !== false;
    }

    protected function hasConfirmationStep(DOMElement $form): bool
    {
        $xpath = new DOMXPath($form->ownerDocument);
        $confirm = $xpath->query('.//*[contains(@class, "confirm") or contains(@name, "confirm")]', $form);
        return $confirm->length > 0;
    }

    protected function hasTimingAdjustments(DOMElement $element): bool
    {
        $xpath = new DOMXPath($element->ownerDocument);
        $adjustments = $xpath->query('.//*[contains(@class, "extend") or contains(@class, "adjust")]', $element->parentNode);
        return $adjustments->length > 0;
    }
}
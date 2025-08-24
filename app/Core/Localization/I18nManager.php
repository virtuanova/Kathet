<?php

namespace App\Core\Localization;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

/**
 * Internationalization Manager
 * 
 * Manages multi-language support similar to Moodle's language system:
 * - Language pack loading and caching
 * - String translation with context
 * - Pluralization support
 * - RTL language support
 * - Plugin language strings
 */
class I18nManager
{
    protected string $defaultLocale = 'en';
    protected string $currentLocale;
    protected array $loadedLanguages = [];
    protected array $translations = [];
    protected array $pluginTranslations = [];

    public function __construct()
    {
        $this->currentLocale = $this->defaultLocale;
    }

    /**
     * Set current locale
     */
    public function setLocale(string $locale): void
    {
        if ($this->isValidLocale($locale)) {
            $this->currentLocale = $locale;
            App::setLocale($locale);
            
            // Load language pack if not already loaded
            if (!in_array($locale, $this->loadedLanguages)) {
                $this->loadLanguagePack($locale);
            }
        }
    }

    /**
     * Get current locale
     */
    public function getLocale(): string
    {
        return $this->currentLocale;
    }

    /**
     * Get available locales
     */
    public function getAvailableLocales(): Collection
    {
        $locales = collect();
        $langPath = resource_path('lang');
        
        if (File::exists($langPath)) {
            $directories = File::directories($langPath);
            foreach ($directories as $directory) {
                $locale = basename($directory);
                if ($this->isValidLocale($locale)) {
                    $info = $this->getLanguageInfo($locale);
                    $locales->put($locale, $info);
                }
            }
        }
        
        return $locales;
    }

    /**
     * Translate string with parameters
     */
    public function translate(string $key, array $parameters = [], ?string $locale = null): string
    {
        $locale = $locale ?: $this->currentLocale;
        
        // Load locale if not loaded
        if (!in_array($locale, $this->loadedLanguages)) {
            $this->loadLanguagePack($locale);
        }
        
        // Get translation
        $translation = $this->getTranslation($key, $locale);
        
        // Replace parameters
        if (!empty($parameters)) {
            $translation = $this->replaceParameters($translation, $parameters);
        }
        
        return $translation;
    }

    /**
     * Translate with pluralization
     */
    public function translatePlural(string $key, int $count, array $parameters = [], ?string $locale = null): string
    {
        $locale = $locale ?: $this->currentLocale;
        
        $pluralKey = $this->getPluralKey($key, $count, $locale);
        $parameters['count'] = $count;
        
        return $this->translate($pluralKey, $parameters, $locale);
    }

    /**
     * Check if text direction is RTL for locale
     */
    public function isRtl(?string $locale = null): bool
    {
        $locale = $locale ?: $this->currentLocale;
        
        $rtlLanguages = [
            'ar', 'he', 'fa', 'ur', 'ku', 'dv', 'yi',
            'ar-dz', 'ar-bh', 'ar-eg', 'ar-iq', 'ar-jo', 
            'ar-kw', 'ar-lb', 'ar-ly', 'ar-ma', 'ar-om', 
            'ar-qa', 'ar-sa', 'ar-sy', 'ar-tn', 'ar-ae', 'ar-ye'
        ];
        
        return in_array($locale, $rtlLanguages);
    }

    /**
     * Get language direction class
     */
    public function getDirectionClass(?string $locale = null): string
    {
        return $this->isRtl($locale) ? 'rtl' : 'ltr';
    }

    /**
     * Load plugin language strings
     */
    public function loadPluginLanguage(string $pluginType, string $pluginName, ?string $locale = null): void
    {
        $locale = $locale ?: $this->currentLocale;
        $cacheKey = "plugin_lang_{$pluginType}_{$pluginName}_{$locale}";
        
        $translations = Cache::remember($cacheKey, 3600, function() use ($pluginType, $pluginName, $locale) {
            $langFile = base_path("plugins/{$pluginType}/{$pluginName}/lang/{$locale}.php");
            
            if (File::exists($langFile)) {
                return include $langFile;
            }
            
            // Fallback to English
            $fallbackFile = base_path("plugins/{$pluginType}/{$pluginName}/lang/en.php");
            if (File::exists($fallbackFile)) {
                return include $fallbackFile;
            }
            
            return [];
        });
        
        $pluginKey = "{$pluginType}_{$pluginName}";
        $this->pluginTranslations[$locale][$pluginKey] = $translations;
    }

    /**
     * Translate plugin string
     */
    public function translatePlugin(string $pluginType, string $pluginName, string $key, array $parameters = [], ?string $locale = null): string
    {
        $locale = $locale ?: $this->currentLocale;
        $pluginKey = "{$pluginType}_{$pluginName}";
        
        // Load plugin language if not loaded
        if (!isset($this->pluginTranslations[$locale][$pluginKey])) {
            $this->loadPluginLanguage($pluginType, $pluginName, $locale);
        }
        
        $translation = $this->pluginTranslations[$locale][$pluginKey][$key] ?? $key;
        
        if (!empty($parameters)) {
            $translation = $this->replaceParameters($translation, $parameters);
        }
        
        return $translation;
    }

    /**
     * Format date according to locale
     */
    public function formatDate(\DateTime $date, string $format = 'medium', ?string $locale = null): string
    {
        $locale = $locale ?: $this->currentLocale;
        
        $formats = $this->getDateFormats($locale);
        $formatString = $formats[$format] ?? $formats['medium'];
        
        // Handle RTL markers
        if ($this->isRtl($locale)) {
            $formatString = "\u{202D}" . $formatString . "\u{202C}"; // LTR override for dates
        }
        
        return $date->format($formatString);
    }

    /**
     * Format number according to locale
     */
    public function formatNumber(float $number, int $decimals = 2, ?string $locale = null): string
    {
        $locale = $locale ?: $this->currentLocale;
        
        $localeData = $this->getLocaleData($locale);
        
        return number_format(
            $number,
            $decimals,
            $localeData['decimal_separator'],
            $localeData['thousands_separator']
        );
    }

    /**
     * Get language pack information
     */
    public function getLanguageInfo(string $locale): array
    {
        $infoFile = resource_path("lang/{$locale}/info.php");
        
        if (File::exists($infoFile)) {
            return include $infoFile;
        }
        
        return [
            'name' => $locale,
            'nativename' => $locale,
            'direction' => $this->isRtl($locale) ? 'rtl' : 'ltr',
            'completion' => 100,
        ];
    }

    /**
     * Export language strings for JavaScript
     */
    public function exportForJavaScript(array $keys = [], ?string $locale = null): array
    {
        $locale = $locale ?: $this->currentLocale;
        
        if (empty($keys)) {
            // Export common UI strings
            $keys = [
                'common.yes', 'common.no', 'common.cancel', 'common.save',
                'common.delete', 'common.edit', 'common.loading', 'common.error',
                'common.success', 'common.warning', 'common.info',
                'form.required', 'form.invalid', 'form.submit',
                'navigation.dashboard', 'navigation.courses', 'navigation.users'
            ];
        }
        
        $translations = [];
        foreach ($keys as $key) {
            $translations[$key] = $this->translate($key, [], $locale);
        }
        
        return [
            'locale' => $locale,
            'direction' => $this->getDirectionClass($locale),
            'translations' => $translations,
        ];
    }

    /**
     * Create language pack from Moodle language files
     */
    public function importMoodleLanguagePack(string $moodleLangPath, string $locale): bool
    {
        $outputPath = resource_path("lang/{$locale}");
        File::ensureDirectoryExists($outputPath);
        
        $moodleFiles = [
            'moodle.php' => 'common.php',
            'admin.php' => 'admin.php',
            'course.php' => 'course.php',
            'user.php' => 'user.php',
            'quiz.php' => 'quiz.php',
            'block.php' => 'block.php',
        ];
        
        foreach ($moodleFiles as $moodleFile => $ourFile) {
            $sourcePath = "{$moodleLangPath}/{$moodleFile}";
            $targetPath = "{$outputPath}/{$ourFile}";
            
            if (File::exists($sourcePath)) {
                $moodleStrings = include $sourcePath;
                $convertedStrings = $this->convertMoodleStrings($moodleStrings);
                
                $content = "<?php\n\nreturn " . var_export($convertedStrings, true) . ";\n";
                File::put($targetPath, $content);
            }
        }
        
        // Create language info file
        $this->createLanguageInfo($locale, $outputPath);
        
        return true;
    }

    /**
     * Check if locale is valid
     */
    protected function isValidLocale(string $locale): bool
    {
        return preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $locale) === 1;
    }

    /**
     * Load language pack from files
     */
    protected function loadLanguagePack(string $locale): void
    {
        $cacheKey = "lang_pack_{$locale}";
        
        $translations = Cache::remember($cacheKey, 3600, function() use ($locale) {
            $langPath = resource_path("lang/{$locale}");
            $translations = [];
            
            if (File::exists($langPath)) {
                $files = File::files($langPath);
                foreach ($files as $file) {
                    if ($file->getExtension() === 'php') {
                        $category = $file->getFilenameWithoutExtension();
                        $strings = include $file->getPathname();
                        
                        foreach ($strings as $key => $value) {
                            $translations["{$category}.{$key}"] = $value;
                        }
                    }
                }
            }
            
            return $translations;
        });
        
        $this->translations[$locale] = $translations;
        $this->loadedLanguages[] = $locale;
    }

    /**
     * Get translation for key
     */
    protected function getTranslation(string $key, string $locale): string
    {
        // Check locale-specific translations
        if (isset($this->translations[$locale][$key])) {
            return $this->translations[$locale][$key];
        }
        
        // Fallback to default locale
        if ($locale !== $this->defaultLocale && isset($this->translations[$this->defaultLocale][$key])) {
            return $this->translations[$this->defaultLocale][$key];
        }
        
        // Return key if no translation found
        return $key;
    }

    /**
     * Replace parameters in translation
     */
    protected function replaceParameters(string $translation, array $parameters): string
    {
        foreach ($parameters as $key => $value) {
            $translation = str_replace(":{$key}", $value, $translation);
            $translation = str_replace("{{$key}}", $value, $translation);
        }
        
        return $translation;
    }

    /**
     * Get plural key based on count and locale rules
     */
    protected function getPluralKey(string $key, int $count, string $locale): string
    {
        $pluralRules = $this->getPluralRules($locale);
        $form = $pluralRules($count);
        
        $forms = ['zero', 'one', 'two', 'few', 'many', 'other'];
        $suffix = $forms[$form] ?? 'other';
        
        return $key . '_' . $suffix;
    }

    /**
     * Get plural rules for locale
     */
    protected function getPluralRules(string $locale): \Closure
    {
        // Simplified plural rules - in production, use ICU data
        return match(substr($locale, 0, 2)) {
            'ar' => function($n) { return $n == 0 ? 0 : ($n == 1 ? 1 : ($n == 2 ? 2 : ($n % 100 >= 3 && $n % 100 <= 10 ? 3 : ($n % 100 >= 11 ? 4 : 5)))); },
            'ru', 'uk' => function($n) { return $n % 10 == 1 && $n % 100 != 11 ? 1 : ($n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 10 || $n % 100 >= 20) ? 3 : 5); },
            'pl' => function($n) { return $n == 1 ? 1 : ($n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 10 || $n % 100 >= 20) ? 3 : 5); },
            default => function($n) { return $n == 1 ? 1 : 5; }, // English-like rules
        };
    }

    /**
     * Get date formats for locale
     */
    protected function getDateFormats(string $locale): array
    {
        return match(substr($locale, 0, 2)) {
            'de' => [
                'short' => 'd.m.Y',
                'medium' => 'd. M Y',
                'long' => 'd. F Y',
                'full' => 'l, d. F Y',
            ],
            'fr' => [
                'short' => 'd/m/Y',
                'medium' => 'd MMM Y',
                'long' => 'd MMMM Y',
                'full' => 'EEEE d MMMM Y',
            ],
            default => [
                'short' => 'n/j/Y',
                'medium' => 'M j, Y',
                'long' => 'F j, Y',
                'full' => 'l, F j, Y',
            ],
        };
    }

    /**
     * Get locale data for number formatting
     */
    protected function getLocaleData(string $locale): array
    {
        return match(substr($locale, 0, 2)) {
            'de' => [
                'decimal_separator' => ',',
                'thousands_separator' => '.',
            ],
            'fr' => [
                'decimal_separator' => ',',
                'thousands_separator' => ' ',
            ],
            default => [
                'decimal_separator' => '.',
                'thousands_separator' => ',',
            ],
        };
    }

    /**
     * Convert Moodle language strings to our format
     */
    protected function convertMoodleStrings(array $moodleStrings): array
    {
        $converted = [];
        
        foreach ($moodleStrings as $key => $value) {
            // Convert Moodle placeholders to our format
            $value = preg_replace('/\{(\$a(?:\->[a-zA-Z0-9_]+)?)\}/', ':$1', $value);
            $value = preg_replace('/\{(\$[a-zA-Z0-9_]+)\}/', ':$1', $value);
            
            $converted[$key] = $value;
        }
        
        return $converted;
    }

    /**
     * Create language info file
     */
    protected function createLanguageInfo(string $locale, string $outputPath): void
    {
        $info = [
            'name' => $locale,
            'nativename' => $locale,
            'direction' => $this->isRtl($locale) ? 'rtl' : 'ltr',
            'completion' => 100,
            'parent' => $locale !== 'en' ? 'en' : null,
        ];
        
        $content = "<?php\n\nreturn " . var_export($info, true) . ";\n";
        File::put("{$outputPath}/info.php", $content);
    }
}
<?php

namespace App\Core\Module;

use App\Core\Plugin\Contracts\ModuleInterface;
use App\Core\Plugin\PluginManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Collection;

/**
 * Loads and manages Moodle activity modules (mods)
 */
class ModuleLoader
{
    protected PluginManager $pluginManager;
    protected Collection $loadedModules;
    protected array $moduleRoutes = [];
    protected array $moduleCapabilities = [];

    public function __construct(PluginManager $pluginManager)
    {
        $this->pluginManager = $pluginManager;
        $this->loadedModules = collect();
    }

    /**
     * Load all enabled modules
     */
    public function loadModules(): void
    {
        $enabledModules = $this->getEnabledModules();

        foreach ($enabledModules as $module) {
            try {
                $this->loadModule($module['name']);
            } catch (\Exception $e) {
                \Log::error("Failed to load module {$module['name']}: " . $e->getMessage());
            }
        }

        // Register module routes
        $this->registerModuleRoutes();
    }

    /**
     * Load a specific module
     */
    public function loadModule(string $moduleName): ModuleInterface
    {
        if ($this->loadedModules->has($moduleName)) {
            return $this->loadedModules->get($moduleName);
        }

        $module = $this->pluginManager->loadPlugin('mod', $moduleName);

        if (!$module instanceof ModuleInterface) {
            throw new \Exception("Module {$moduleName} does not implement ModuleInterface");
        }

        // Register module in course_modules table
        $this->registerModuleInDatabase($moduleName, $module);

        // Cache capabilities
        $this->moduleCapabilities[$moduleName] = $module->getCapabilities();

        $this->loadedModules->put($moduleName, $module);

        return $module;
    }

    /**
     * Get a loaded module
     */
    public function getModule(string $moduleName): ?ModuleInterface
    {
        return $this->loadedModules->get($moduleName);
    }

    /**
     * Create a module instance in a course
     */
    public function createModuleInstance(string $moduleName, int $courseId, int $sectionId, array $data): array
    {
        $module = $this->getModule($moduleName);
        
        if (!$module) {
            throw new \Exception("Module {$moduleName} not loaded");
        }

        DB::beginTransaction();

        try {
            // Create module instance
            $instanceId = $module->addInstance($data);

            // Get module ID from modules table
            $moduleRecord = DB::table('mdl_modules')->where('name', $moduleName)->first();
            
            if (!$moduleRecord) {
                throw new \Exception("Module {$moduleName} not found in modules table");
            }

            // Create course module record
            $courseModuleId = DB::table('mdl_course_modules')->insertGetId([
                'course' => $courseId,
                'module' => $moduleRecord->id,
                'instance' => $instanceId,
                'section' => $sectionId,
                'idnumber' => $data['idnumber'] ?? '',
                'added' => time(),
                'score' => 0,
                'indent' => $data['indent'] ?? 0,
                'visible' => $data['visible'] ?? 1,
                'visibleoncoursepage' => $data['visibleoncoursepage'] ?? 1,
                'visibleold' => $data['visible'] ?? 1,
                'groupmode' => $data['groupmode'] ?? 0,
                'groupingid' => $data['groupingid'] ?? 0,
                'completion' => $data['completion'] ?? 0,
                'completiongradeitemnumber' => $data['completiongradeitemnumber'] ?? null,
                'completionview' => $data['completionview'] ?? 0,
                'completionexpected' => $data['completionexpected'] ?? 0,
                'showdescription' => $data['showdescription'] ?? 0,
                'availability' => $data['availability'] ?? null,
            ]);

            // Update course section sequence
            $this->updateSectionSequence($sectionId, $courseModuleId);

            // Create grade item if module supports grading
            if ($module->getSupportedFeatures()['grade'] ?? false) {
                $this->createGradeItem($courseId, $courseModuleId, $moduleName, $instanceId, $data);
            }

            DB::commit();

            return [
                'instance_id' => $instanceId,
                'course_module_id' => $courseModuleId,
                'module' => $moduleName,
            ];

        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Update module instance
     */
    public function updateModuleInstance(int $courseModuleId, array $data): bool
    {
        $courseModule = $this->getCourseModuleRecord($courseModuleId);
        
        if (!$courseModule) {
            throw new \Exception("Course module {$courseModuleId} not found");
        }

        $module = $this->getModuleByInstanceId($courseModule->module, $courseModule->instance);

        if (!$module) {
            throw new \Exception("Module not found for course module {$courseModuleId}");
        }

        DB::beginTransaction();

        try {
            // Update module instance data
            $module->updateInstance($courseModule->instance, $data);

            // Update course module record
            DB::table('mdl_course_modules')
                ->where('id', $courseModuleId)
                ->update([
                    'idnumber' => $data['idnumber'] ?? $courseModule->idnumber,
                    'visible' => $data['visible'] ?? $courseModule->visible,
                    'visibleoncoursepage' => $data['visibleoncoursepage'] ?? $courseModule->visibleoncoursepage,
                    'groupmode' => $data['groupmode'] ?? $courseModule->groupmode,
                    'groupingid' => $data['groupingid'] ?? $courseModule->groupingid,
                    'completion' => $data['completion'] ?? $courseModule->completion,
                    'completionview' => $data['completionview'] ?? $courseModule->completionview,
                    'completionexpected' => $data['completionexpected'] ?? $courseModule->completionexpected,
                    'showdescription' => $data['showdescription'] ?? $courseModule->showdescription,
                    'availability' => $data['availability'] ?? $courseModule->availability,
                ]);

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Delete module instance
     */
    public function deleteModuleInstance(int $courseModuleId): bool
    {
        $courseModule = $this->getCourseModuleRecord($courseModuleId);
        
        if (!$courseModule) {
            return false;
        }

        $module = $this->getModuleByInstanceId($courseModule->module, $courseModule->instance);

        DB::beginTransaction();

        try {
            // Delete from module-specific table
            if ($module) {
                $module->deleteInstance($courseModule->instance);
            }

            // Delete grades
            DB::table('mdl_grade_items')
                ->where('courseid', $courseModule->course)
                ->where('itemmodule', $this->getModuleNameById($courseModule->module))
                ->where('iteminstance', $courseModule->instance)
                ->delete();

            // Remove from section sequence
            $this->removeFromSectionSequence($courseModule->section, $courseModuleId);

            // Delete course module
            DB::table('mdl_course_modules')->where('id', $courseModuleId)->delete();

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollback();
            \Log::error("Failed to delete module instance {$courseModuleId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get module view for display
     */
    public function getModuleView(int $courseModuleId, int $userId): string
    {
        $courseModule = $this->getCourseModuleRecord($courseModuleId);
        
        if (!$courseModule) {
            throw new \Exception("Course module {$courseModuleId} not found");
        }

        $module = $this->getModuleByInstanceId($courseModule->module, $courseModule->instance);

        if (!$module) {
            throw new \Exception("Module not found");
        }

        return $module->getView($courseModule->instance, $userId);
    }

    /**
     * Handle AJAX requests for modules
     */
    public function handleModuleAjax(string $moduleName, string $action, array $params): array
    {
        $module = $this->getModule($moduleName);

        if (!$module) {
            throw new \Exception("Module {$moduleName} not found");
        }

        return $module->handleAjax($action, $params);
    }

    /**
     * Get all available modules
     */
    public function getAvailableModules(): Collection
    {
        return $this->pluginManager->getAvailablePlugins()
            ->where('type', 'mod')
            ->map(function($plugin) {
                $module = $this->loadModule($plugin['name']);
                return [
                    'name' => $plugin['name'],
                    'info' => $plugin['info'],
                    'icon' => $module->getIcon(),
                    'description' => $module->getDescription(),
                    'features' => $module->getSupportedFeatures(),
                    'capabilities' => $module->getCapabilities(),
                ];
            });
    }

    /**
     * Get modules for a specific course
     */
    public function getCourseModules(int $courseId): Collection
    {
        return collect(DB::table('mdl_course_modules as cm')
            ->join('mdl_modules as m', 'cm.module', '=', 'm.id')
            ->join('mdl_course_sections as s', 'cm.section', '=', 's.id')
            ->where('cm.course', $courseId)
            ->where('cm.visible', 1)
            ->orderBy('s.section')
            ->orderBy('cm.id')
            ->select([
                'cm.id as course_module_id',
                'cm.instance',
                'cm.visible',
                'cm.completion',
                'm.name as module_name',
                's.section as section_number',
                's.name as section_name'
            ])
            ->get()
            ->groupBy('section_number'));
    }

    /**
     * Register module routes
     */
    protected function registerModuleRoutes(): void
    {
        foreach ($this->loadedModules as $moduleName => $module) {
            try {
                // Create module-specific routes
                Route::prefix("mod/{$moduleName}")
                    ->name("mod.{$moduleName}.")
                    ->group(function() use ($moduleName, $module) {
                        // View route
                        Route::get('view/{id}', function($id) use ($moduleName, $module) {
                            $userId = auth()->id() ?? 0;
                            return $this->getModuleView($id, $userId);
                        })->name('view');

                        // Edit route  
                        Route::get('edit/{id}', function($id) use ($module) {
                            return $module->getEditForm();
                        })->name('edit');

                        // AJAX route
                        Route::post('ajax/{action}', function($action, Request $request) use ($moduleName) {
                            return $this->handleModuleAjax($moduleName, $action, $request->all());
                        })->name('ajax');

                        // Module-specific routes
                        if (method_exists($module, 'registerRoutes')) {
                            $module->registerRoutes();
                        }
                    });

            } catch (\Exception $e) {
                \Log::error("Failed to register routes for module {$moduleName}: " . $e->getMessage());
            }
        }
    }

    /**
     * Register module in modules table
     */
    protected function registerModuleInDatabase(string $moduleName, ModuleInterface $module): void
    {
        DB::table('mdl_modules')->updateOrInsert(
            ['name' => $moduleName],
            [
                'cron' => 0,
                'lastcron' => 0,
                'search' => '',
                'visible' => 1,
            ]
        );
    }

    /**
     * Update section sequence when adding module
     */
    protected function updateSectionSequence(int $sectionId, int $courseModuleId): void
    {
        $section = DB::table('mdl_course_sections')->where('id', $sectionId)->first();
        
        if ($section) {
            $sequence = $section->sequence ? explode(',', $section->sequence) : [];
            $sequence[] = $courseModuleId;
            
            DB::table('mdl_course_sections')
                ->where('id', $sectionId)
                ->update(['sequence' => implode(',', $sequence)]);
        }
    }

    /**
     * Remove module from section sequence
     */
    protected function removeFromSectionSequence(int $sectionId, int $courseModuleId): void
    {
        $section = DB::table('mdl_course_sections')->where('id', $sectionId)->first();
        
        if ($section && $section->sequence) {
            $sequence = explode(',', $section->sequence);
            $sequence = array_filter($sequence, function($id) use ($courseModuleId) {
                return $id != $courseModuleId;
            });
            
            DB::table('mdl_course_sections')
                ->where('id', $sectionId)
                ->update(['sequence' => implode(',', $sequence)]);
        }
    }

    /**
     * Create grade item for gradeable modules
     */
    protected function createGradeItem(int $courseId, int $courseModuleId, string $moduleName, int $instanceId, array $data): void
    {
        $module = $this->getModule($moduleName);
        $gradingInfo = $module->getGradingInfo($instanceId);

        DB::table('mdl_grade_items')->insert([
            'courseid' => $courseId,
            'categoryid' => null,
            'itemname' => $data['name'] ?? '',
            'itemtype' => 'mod',
            'itemmodule' => $moduleName,
            'iteminstance' => $instanceId,
            'itemnumber' => 0,
            'iteminfo' => null,
            'idnumber' => $data['idnumber'] ?? '',
            'calculation' => null,
            'gradetype' => $gradingInfo['grade_type'] ?? 1,
            'grademax' => $gradingInfo['grade_max'] ?? 100,
            'grademin' => $gradingInfo['grade_min'] ?? 0,
            'scaleid' => $gradingInfo['scale_id'] ?? null,
            'outcomeid' => null,
            'gradepass' => $gradingInfo['grade_pass'] ?? 0,
            'multfactor' => 1.0,
            'plusfactor' => 0,
            'aggregationcoef' => 0,
            'aggregationcoef2' => 0,
            'sortorder' => 0,
            'display' => 0,
            'decimals' => null,
            'hidden' => 0,
            'locked' => 0,
            'locktime' => 0,
            'needsupdate' => 0,
            'weightoverride' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Get enabled modules from database
     */
    protected function getEnabledModules(): Collection
    {
        return collect(DB::table('mdl_modules')
            ->where('visible', 1)
            ->get()
            ->toArray());
    }

    /**
     * Get course module record
     */
    protected function getCourseModuleRecord(int $courseModuleId): ?\stdClass
    {
        return DB::table('mdl_course_modules')->where('id', $courseModuleId)->first();
    }

    /**
     * Get module by instance
     */
    protected function getModuleByInstanceId(int $moduleId, int $instanceId): ?ModuleInterface
    {
        $moduleName = $this->getModuleNameById($moduleId);
        return $moduleName ? $this->getModule($moduleName) : null;
    }

    /**
     * Get module name by ID
     */
    protected function getModuleNameById(int $moduleId): ?string
    {
        return DB::table('mdl_modules')->where('id', $moduleId)->value('name');
    }

    /**
     * Check if user can access module
     */
    public function canAccessModule(int $userId, int $courseModuleId): bool
    {
        $courseModule = $this->getCourseModuleRecord($courseModuleId);
        
        if (!$courseModule || !$courseModule->visible) {
            return false;
        }

        // Check course enrollment
        $isEnrolled = DB::table('mdl_user_enrolments as ue')
            ->join('mdl_enrol as e', 'ue.enrolid', '=', 'e.id')
            ->where('ue.userid', $userId)
            ->where('e.courseid', $courseModule->course)
            ->where('ue.status', 0)
            ->exists();

        return $isEnrolled;
    }

    /**
     * Get module completion status for user
     */
    public function getCompletionStatus(int $userId, int $courseModuleId): array
    {
        $completion = DB::table('mdl_course_modules_completion')
            ->where('userid', $userId)
            ->where('coursemoduleid', $courseModuleId)
            ->first();

        return [
            'completed' => $completion ? (bool)$completion->completionstate : false,
            'viewed' => $completion ? (bool)$completion->viewed : false,
            'timemodified' => $completion ? $completion->timemodified : 0,
        ];
    }

    /**
     * Update module completion
     */
    public function updateCompletion(int $userId, int $courseModuleId, int $completionState): bool
    {
        try {
            DB::table('mdl_course_modules_completion')->updateOrInsert(
                [
                    'userid' => $userId,
                    'coursemoduleid' => $courseModuleId,
                ],
                [
                    'completionstate' => $completionState,
                    'viewed' => 1,
                    'timemodified' => time(),
                ]
            );

            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to update completion: " . $e->getMessage());
            return false;
        }
    }
}
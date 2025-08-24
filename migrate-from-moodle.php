<?php

/**
 * Migration script for existing Moodle installations
 * This script helps migrate from an existing Moodle database to our LMS platform
 */

require_once 'vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;
use Dotenv\Dotenv;

class MoodleMigrationScript
{
    private $moodleConfig;
    private $lmsConfig;
    private $moodleDB;
    private $lmsDB;
    
    public function __construct()
    {
        $this->loadEnvironment();
        $this->setupDatabases();
    }
    
    private function loadEnvironment()
    {
        if (file_exists('.env')) {
            $dotenv = Dotenv::createImmutable(__DIR__);
            $dotenv->load();
        }
    }
    
    private function setupDatabases()
    {
        // LMS Database (destination)
        $this->lmsDB = new DB();
        $this->lmsDB->addConnection([
            'driver' => $_ENV['DB_CONNECTION'] ?? 'mysql',
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'database' => $_ENV['DB_DATABASE'] ?? 'lms_platform',
            'username' => $_ENV['DB_USERNAME'] ?? 'root',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
        ], 'lms');
        
        // Moodle Database (source) - configure separately
        $this->moodleDB = new DB();
        $this->moodleDB->addConnection([
            'driver' => $_ENV['MOODLE_DB_CONNECTION'] ?? 'mysql',
            'host' => $_ENV['MOODLE_DB_HOST'] ?? 'localhost',
            'database' => $_ENV['MOODLE_DB_DATABASE'] ?? 'moodle',
            'username' => $_ENV['MOODLE_DB_USERNAME'] ?? 'root',
            'password' => $_ENV['MOODLE_DB_PASSWORD'] ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => 'mdl_',
        ], 'moodle');
        
        $this->lmsDB->setAsGlobal();
        $this->lmsDB->bootEloquent();
    }
    
    public function migrate()
    {
        echo "Starting Moodle to LMS Platform migration...\n";
        
        try {
            $this->validateMoodleDatabase();
            $this->migrateUsers();
            $this->migrateCategories();
            $this->migrateCourses();
            $this->migrateEnrollments();
            $this->migrateGrades();
            $this->migrateActivities();
            
            echo "Migration completed successfully!\n";
            
        } catch (Exception $e) {
            echo "Migration failed: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
    
    private function validateMoodleDatabase()
    {
        echo "Validating Moodle database...\n";
        
        // Check if essential Moodle tables exist
        $requiredTables = ['mdl_user', 'mdl_course', 'mdl_course_categories'];
        
        foreach ($requiredTables as $table) {
            try {
                $this->moodleDB->connection('moodle')->table($table)->limit(1)->get();
            } catch (Exception $e) {
                throw new Exception("Required Moodle table '{$table}' not found. Please check your Moodle database configuration.");
            }
        }
        
        echo "Moodle database validation passed.\n";
    }
    
    private function migrateUsers()
    {
        echo "Migrating users...\n";
        
        $moodleUsers = $this->moodleDB->connection('moodle')
            ->table('mdl_user')
            ->where('deleted', 0)
            ->where('confirmed', 1)
            ->get();
        
        $migrated = 0;
        
        foreach ($moodleUsers as $user) {
            // Check if user already exists
            $exists = $this->lmsDB->connection('lms')
                ->table('mdl_user')
                ->where('username', $user->username)
                ->orWhere('email', $user->email)
                ->exists();
                
            if (!$exists) {
                $this->lmsDB->connection('lms')->table('mdl_user')->insert([
                    'id' => $user->id,
                    'auth' => $user->auth,
                    'confirmed' => $user->confirmed,
                    'deleted' => $user->deleted,
                    'suspended' => $user->suspended,
                    'mnethostid' => $user->mnethostid,
                    'username' => $user->username,
                    'password' => $user->password,
                    'idnumber' => $user->idnumber,
                    'firstname' => $user->firstname,
                    'lastname' => $user->lastname,
                    'email' => $user->email,
                    'emailstop' => $user->emailstop,
                    'phone1' => $user->phone1,
                    'phone2' => $user->phone2,
                    'institution' => $user->institution,
                    'department' => $user->department,
                    'address' => $user->address,
                    'city' => $user->city,
                    'country' => $user->country,
                    'lang' => $user->lang,
                    'timezone' => $user->timezone,
                    'description' => $user->description,
                    'descriptionformat' => $user->descriptionformat,
                    'firstaccess' => $user->firstaccess,
                    'lastaccess' => $user->lastaccess,
                    'lastlogin' => $user->lastlogin,
                    'currentlogin' => $user->currentlogin,
                    'lastip' => $user->lastip,
                    'timecreated' => $user->timecreated,
                    'timemodified' => $user->timemodified,
                    'mailformat' => $user->mailformat,
                    'maildigest' => $user->maildigest,
                    'maildisplay' => $user->maildisplay,
                ]);
                
                $migrated++;
            }
        }
        
        echo "Migrated {$migrated} users.\n";
    }
    
    private function migrateCategories()
    {
        echo "Migrating course categories...\n";
        
        $categories = $this->moodleDB->connection('moodle')
            ->table('mdl_course_categories')
            ->orderBy('depth')
            ->get();
        
        $migrated = 0;
        
        foreach ($categories as $category) {
            $exists = $this->lmsDB->connection('lms')
                ->table('mdl_course_categories')
                ->where('id', $category->id)
                ->exists();
                
            if (!$exists) {
                $this->lmsDB->connection('lms')->table('mdl_course_categories')->insert([
                    'id' => $category->id,
                    'name' => $category->name,
                    'idnumber' => $category->idnumber,
                    'description' => $category->description,
                    'descriptionformat' => $category->descriptionformat,
                    'parent' => $category->parent,
                    'sortorder' => $category->sortorder,
                    'coursecount' => $category->coursecount,
                    'visible' => $category->visible,
                    'visibleold' => $category->visibleold,
                    'timemodified' => $category->timemodified,
                    'depth' => $category->depth,
                    'path' => $category->path,
                    'theme' => $category->theme,
                ]);
                
                $migrated++;
            }
        }
        
        echo "Migrated {$migrated} categories.\n";
    }
    
    private function migrateCourses()
    {
        echo "Migrating courses...\n";
        
        $courses = $this->moodleDB->connection('moodle')
            ->table('mdl_course')
            ->where('id', '>', 1) // Skip site course
            ->get();
        
        $migrated = 0;
        
        foreach ($courses as $course) {
            $exists = $this->lmsDB->connection('lms')
                ->table('mdl_course')
                ->where('id', $course->id)
                ->exists();
                
            if (!$exists) {
                $this->lmsDB->connection('lms')->table('mdl_course')->insert([
                    'id' => $course->id,
                    'category' => $course->category,
                    'sortorder' => $course->sortorder,
                    'fullname' => $course->fullname,
                    'shortname' => $course->shortname,
                    'idnumber' => $course->idnumber,
                    'summary' => $course->summary,
                    'summaryformat' => $course->summaryformat,
                    'format' => $course->format,
                    'showgrades' => $course->showgrades,
                    'newsitems' => $course->newsitems,
                    'startdate' => $course->startdate,
                    'enddate' => $course->enddate,
                    'marker' => $course->marker,
                    'maxbytes' => $course->maxbytes,
                    'legacyfiles' => $course->legacyfiles,
                    'showreports' => $course->showreports,
                    'visible' => $course->visible,
                    'visibleold' => $course->visibleold,
                    'groupmode' => $course->groupmode,
                    'groupmodeforce' => $course->groupmodeforce,
                    'defaultgroupingid' => $course->defaultgroupingid,
                    'lang' => $course->lang,
                    'theme' => $course->theme,
                    'timecreated' => $course->timecreated,
                    'timemodified' => $course->timemodified,
                    'requested' => $course->requested,
                    'enablecompletion' => $course->enablecompletion,
                    'completionnotify' => $course->completionnotify,
                    'cacherev' => $course->cacherev,
                    'calendartype' => $course->calendartype ?? '',
                    'showactivitydates' => $course->showactivitydates ?? 0,
                    'showcompletionconditions' => $course->showcompletionconditions,
                ]);
                
                $migrated++;
            }
        }
        
        echo "Migrated {$migrated} courses.\n";
    }
    
    private function migrateEnrollments()
    {
        echo "Migrating enrollments...\n";
        
        // First migrate enrol instances
        $enrolInstances = $this->moodleDB->connection('moodle')
            ->table('mdl_enrol')
            ->get();
            
        foreach ($enrolInstances as $instance) {
            $exists = $this->lmsDB->connection('lms')
                ->table('mdl_enrol')
                ->where('id', $instance->id)
                ->exists();
                
            if (!$exists) {
                $this->lmsDB->connection('lms')->table('mdl_enrol')->insert((array) $instance);
            }
        }
        
        // Then migrate user enrollments
        $userEnrolments = $this->moodleDB->connection('moodle')
            ->table('mdl_user_enrolments')
            ->get();
            
        $migrated = 0;
        
        foreach ($userEnrolments as $enrolment) {
            $exists = $this->lmsDB->connection('lms')
                ->table('mdl_user_enrolments')
                ->where('id', $enrolment->id)
                ->exists();
                
            if (!$exists) {
                $this->lmsDB->connection('lms')->table('mdl_user_enrolments')->insert((array) $enrolment);
                $migrated++;
            }
        }
        
        echo "Migrated {$migrated} user enrollments.\n";
    }
    
    private function migrateGrades()
    {
        echo "Migrating grades...\n";
        
        // Migrate grade items
        $gradeItems = $this->moodleDB->connection('moodle')
            ->table('mdl_grade_items')
            ->get();
            
        foreach ($gradeItems as $item) {
            $exists = $this->lmsDB->connection('lms')
                ->table('mdl_grade_items')
                ->where('id', $item->id)
                ->exists();
                
            if (!$exists) {
                $this->lmsDB->connection('lms')->table('mdl_grade_items')->insert((array) $item);
            }
        }
        
        // Migrate grades
        $grades = $this->moodleDB->connection('moodle')
            ->table('mdl_grade_grades')
            ->get();
            
        $migrated = 0;
        
        foreach ($grades as $grade) {
            $exists = $this->lmsDB->connection('lms')
                ->table('mdl_grade_grades')
                ->where('id', $grade->id)
                ->exists();
                
            if (!$exists) {
                $this->lmsDB->connection('lms')->table('mdl_grade_grades')->insert((array) $grade);
                $migrated++;
            }
        }
        
        echo "Migrated {$migrated} grades.\n";
    }
    
    private function migrateActivities()
    {
        echo "Migrating course activities...\n";
        
        // Migrate course modules
        $courseModules = $this->moodleDB->connection('moodle')
            ->table('mdl_course_modules')
            ->get();
            
        $migrated = 0;
        
        foreach ($courseModules as $module) {
            $exists = $this->lmsDB->connection('lms')
                ->table('mdl_course_modules')
                ->where('id', $module->id)
                ->exists();
                
            if (!$exists) {
                $this->lmsDB->connection('lms')->table('mdl_course_modules')->insert((array) $module);
                $migrated++;
            }
        }
        
        echo "Migrated {$migrated} course modules.\n";
        
        // Migrate specific activity types
        $this->migrateAssignments();
        $this->migrateForums();
        $this->migrateQuizzes();
    }
    
    private function migrateAssignments()
    {
        $assignments = $this->moodleDB->connection('moodle')
            ->table('mdl_assign')
            ->get();
            
        foreach ($assignments as $assignment) {
            $exists = $this->lmsDB->connection('lms')
                ->table('mdl_assign')
                ->where('id', $assignment->id)
                ->exists();
                
            if (!$exists) {
                $this->lmsDB->connection('lms')->table('mdl_assign')->insert((array) $assignment);
            }
        }
    }
    
    private function migrateForums()
    {
        $forums = $this->moodleDB->connection('moodle')
            ->table('mdl_forum')
            ->get();
            
        foreach ($forums as $forum) {
            $exists = $this->lmsDB->connection('lms')
                ->table('mdl_forum')
                ->where('id', $forum->id)
                ->exists();
                
            if (!$exists) {
                $this->lmsDB->connection('lms')->table('mdl_forum')->insert((array) $forum);
            }
        }
    }
    
    private function migrateQuizzes()
    {
        $quizzes = $this->moodleDB->connection('moodle')
            ->table('mdl_quiz')
            ->get();
            
        foreach ($quizzes as $quiz) {
            $exists = $this->lmsDB->connection('lms')
                ->table('mdl_quiz')
                ->where('id', $quiz->id)
                ->exists();
                
            if (!$exists) {
                $this->lmsDB->connection('lms')->table('mdl_quiz')->insert((array) $quiz);
            }
        }
    }
    
    public function generateReport()
    {
        echo "\n=== Migration Report ===\n";
        
        $tables = [
            'mdl_user' => 'Users',
            'mdl_course_categories' => 'Course Categories',
            'mdl_course' => 'Courses',
            'mdl_user_enrolments' => 'User Enrollments',
            'mdl_grade_grades' => 'Grades',
            'mdl_course_modules' => 'Course Modules',
            'mdl_assign' => 'Assignments',
            'mdl_forum' => 'Forums',
            'mdl_quiz' => 'Quizzes',
        ];
        
        foreach ($tables as $table => $description) {
            try {
                $count = $this->lmsDB->connection('lms')->table($table)->count();
                echo "{$description}: {$count} records\n";
            } catch (Exception $e) {
                echo "{$description}: Error counting records\n";
            }
        }
    }
}

// Command line usage
if (isset($argc) && $argc > 0) {
    $migrator = new MoodleMigrationScript();
    
    if (isset($argv[1]) && $argv[1] === '--report') {
        $migrator->generateReport();
    } else {
        $migrator->migrate();
        $migrator->generateReport();
    }
}
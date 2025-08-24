<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Moodle-compatible course modules tables
 */
return new class extends Migration
{
    public function up(): void
    {
        // mdl_course_sections table
        Schema::create('mdl_course_sections', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('course')->index();
            $table->bigInteger('section')->index();
            $table->string('name', 255)->nullable();
            $table->text('summary')->nullable();
            $table->smallInteger('summaryformat')->default(0);
            $table->text('sequence')->nullable();
            $table->boolean('visible')->default(1);
            $table->string('availability', 255)->nullable();
            $table->bigInteger('timemodified')->default(0);
            
            $table->unique(['course', 'section'], 'mdl_coursect_cousec_uk');
        });
        
        // mdl_modules table
        Schema::create('mdl_modules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 20)->unique();
            $table->bigInteger('cron')->default(0);
            $table->bigInteger('lastcron')->default(0);
            $table->string('search', 255)->default('');
            $table->boolean('visible')->default(1);
        });
        
        // mdl_course_modules table
        Schema::create('mdl_course_modules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('course')->index();
            $table->bigInteger('module')->index();
            $table->bigInteger('instance')->index();
            $table->bigInteger('section')->index();
            $table->string('idnumber', 100)->nullable()->index();
            $table->bigInteger('added')->default(0);
            $table->smallInteger('score')->default(0);
            $table->smallInteger('indent')->default(0);
            $table->boolean('visible')->default(1);
            $table->boolean('visibleoncoursepage')->default(1);
            $table->boolean('visibleold')->default(1);
            $table->smallInteger('groupmode')->default(0);
            $table->bigInteger('groupingid')->default(0);
            $table->boolean('completion')->default(0);
            $table->bigInteger('completiongradeitemnumber')->nullable();
            $table->bigInteger('completionview')->default(0);
            $table->bigInteger('completionexpected')->default(0);
            $table->boolean('showdescription')->default(0);
            $table->text('availability')->nullable();
            $table->boolean('deletioninprogress')->default(0);
            
            $table->index(['visible', 'course'], 'mdl_coursemod_viscou_ix');
            $table->index(['instance', 'module'], 'mdl_coursemod_insmod_ix');
            $table->index('groupingid');
        });
        
        // mdl_course_modules_completion table
        Schema::create('mdl_course_modules_completion', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('coursemoduleid')->index();
            $table->bigInteger('userid')->index();
            $table->boolean('completionstate');
            $table->boolean('viewed')->nullable();
            $table->bigInteger('overrideby')->nullable();
            $table->bigInteger('timemodified');
            
            $table->unique(['userid', 'coursemoduleid'], 'mdl_courmoducomp_usecou_uk');
        });
        
        // mdl_course_completions table
        Schema::create('mdl_course_completions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('userid')->index();
            $table->bigInteger('course')->index();
            $table->bigInteger('timeenrolled')->default(0);
            $table->bigInteger('timestarted')->default(0);
            $table->bigInteger('timecompleted')->nullable();
            $table->bigInteger('reaggregate')->default(0);
            
            $table->unique(['userid', 'course'], 'mdl_courcomp_usecou_uk');
            $table->index('timecompleted');
        });
        
        // Insert default modules
        DB::table('mdl_modules')->insert([
            ['name' => 'assign', 'visible' => 1],
            ['name' => 'assignment', 'visible' => 0], // Legacy
            ['name' => 'book', 'visible' => 1],
            ['name' => 'chat', 'visible' => 1],
            ['name' => 'choice', 'visible' => 1],
            ['name' => 'data', 'visible' => 1],
            ['name' => 'feedback', 'visible' => 1],
            ['name' => 'folder', 'visible' => 1],
            ['name' => 'forum', 'visible' => 1],
            ['name' => 'glossary', 'visible' => 1],
            ['name' => 'h5pactivity', 'visible' => 1],
            ['name' => 'imscp', 'visible' => 1],
            ['name' => 'label', 'visible' => 1],
            ['name' => 'lesson', 'visible' => 1],
            ['name' => 'lti', 'visible' => 1],
            ['name' => 'page', 'visible' => 1],
            ['name' => 'quiz', 'visible' => 1],
            ['name' => 'resource', 'visible' => 1],
            ['name' => 'scorm', 'visible' => 1],
            ['name' => 'survey', 'visible' => 1],
            ['name' => 'url', 'visible' => 1],
            ['name' => 'wiki', 'visible' => 1],
            ['name' => 'workshop', 'visible' => 1],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('mdl_course_completions');
        Schema::dropIfExists('mdl_course_modules_completion');
        Schema::dropIfExists('mdl_course_modules');
        Schema::dropIfExists('mdl_modules');
        Schema::dropIfExists('mdl_course_sections');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Moodle-compatible course table (mdl_course)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mdl_course', function (Blueprint $table) {
            $table->bigIncrements('id');
            
            // Course category
            $table->bigInteger('category')->default(0)->index();
            
            // Sorting
            $table->bigInteger('sortorder')->default(0);
            
            // Course information
            $table->string('fullname', 254);
            $table->string('shortname', 255)->unique();
            $table->string('idnumber', 100)->default('')->index();
            $table->text('summary')->nullable();
            $table->smallInteger('summaryformat')->default(0);
            
            // Course format and display
            $table->string('format', 21)->default('topics');
            $table->smallInteger('showgrades')->default(1);
            $table->smallInteger('newsitems')->default(5);
            
            // Timing
            $table->bigInteger('startdate')->default(0);
            $table->bigInteger('enddate')->default(0);
            
            // Marker and legacy fields
            $table->bigInteger('marker')->default(0);
            $table->bigInteger('maxbytes')->default(0);
            $table->smallInteger('legacyfiles')->default(0);
            
            // Display options
            $table->smallInteger('showreports')->default(0);
            $table->smallInteger('visible')->default(1)->index();
            $table->smallInteger('visibleold')->default(1);
            $table->smallInteger('groupmode')->default(0);
            $table->smallInteger('groupmodeforce')->default(0);
            
            // Guest access
            $table->bigInteger('defaultgroupingid')->default(0);
            
            // Language and theme
            $table->string('lang', 30)->default('');
            $table->string('theme', 50)->default('');
            
            // Timestamps
            $table->bigInteger('timecreated')->default(0);
            $table->bigInteger('timemodified')->default(0)->index();
            
            // Completion
            $table->boolean('requested')->default(0);
            $table->boolean('enablecompletion')->default(0);
            $table->boolean('completionnotify')->default(0);
            
            // Cache
            $table->bigInteger('cacherev')->default(0);
            
            // Calendar
            $table->string('calendartype', 30)->default('');
            
            // Display
            $table->boolean('showactivitydates')->default(0);
            $table->boolean('showcompletionconditions')->nullable();
            
            // Indexes
            $table->index('sortorder');
            $table->index(['category', 'sortorder'], 'mdl_cour_cat_sor_ix');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mdl_course');
    }
};
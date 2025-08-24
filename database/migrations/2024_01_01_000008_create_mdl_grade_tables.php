<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Moodle-compatible grade tables
 */
return new class extends Migration
{
    public function up(): void
    {
        // mdl_grade_items table
        Schema::create('mdl_grade_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('courseid')->index();
            $table->bigInteger('categoryid')->nullable()->index();
            $table->string('itemname', 255)->nullable();
            $table->string('itemtype', 30);
            $table->string('itemmodule', 30)->nullable();
            $table->bigInteger('iteminstance')->nullable()->index();
            $table->bigInteger('itemnumber')->nullable();
            $table->text('iteminfo')->nullable();
            $table->string('idnumber', 255)->nullable()->index();
            $table->text('calculation')->nullable();
            $table->smallInteger('gradetype')->default(1);
            $table->decimal('grademax', 10, 5)->default(100);
            $table->decimal('grademin', 10, 5)->default(0);
            $table->bigInteger('scaleid')->nullable()->index();
            $table->bigInteger('outcomeid')->nullable()->index();
            $table->decimal('gradepass', 10, 5)->default(0);
            $table->decimal('multfactor', 10, 5)->default(1.0);
            $table->decimal('plusfactor', 10, 5)->default(0);
            $table->decimal('aggregationcoef', 10, 5)->default(0);
            $table->decimal('aggregationcoef2', 10, 5)->default(0);
            $table->bigInteger('sortorder')->default(0);
            $table->smallInteger('display')->default(0);
            $table->smallInteger('decimals')->nullable();
            $table->smallInteger('hidden')->default(0);
            $table->smallInteger('locked')->default(0);
            $table->bigInteger('locktime')->default(0);
            $table->smallInteger('needsupdate')->default(0);
            $table->decimal('weightoverride', 10, 5)->default(0);
            $table->bigInteger('timecreated')->default(0);
            $table->bigInteger('timemodified')->default(0);
            
            $table->index(['courseid', 'itemtype', 'itemmodule', 'iteminstance', 'itemnumber'], 'mdl_graditem_cou_ite_mod_ins_ite_ix');
            $table->index(['locked', 'locktime'], 'mdl_graditem_loc_loc_ix');
            $table->index('needsupdate');
        });
        
        // mdl_grade_grades table
        Schema::create('mdl_grade_grades', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('itemid')->index();
            $table->bigInteger('userid')->index();
            $table->decimal('rawgrade', 10, 5)->nullable();
            $table->decimal('rawgrademax', 10, 5)->default(100);
            $table->decimal('rawgrademin', 10, 5)->default(0);
            $table->bigInteger('rawscaleid')->nullable();
            $table->bigInteger('usermodified')->nullable();
            $table->decimal('finalgrade', 10, 5)->nullable();
            $table->smallInteger('hidden')->default(0);
            $table->smallInteger('locked')->default(0);
            $table->bigInteger('locktime')->default(0);
            $table->smallInteger('exported')->default(0);
            $table->smallInteger('overridden')->default(0);
            $table->smallInteger('excluded')->default(0);
            $table->text('feedback')->nullable();
            $table->smallInteger('feedbackformat')->default(0);
            $table->text('information')->nullable();
            $table->smallInteger('informationformat')->default(0);
            $table->bigInteger('timecreated')->nullable();
            $table->bigInteger('timemodified')->nullable();
            $table->decimal('aggregationstatus', 10, 0)->default(0);
            $table->decimal('aggregationweight', 10, 5)->nullable();
            
            $table->unique(['userid', 'itemid'], 'mdl_gradgrad_useite_uk');
            $table->index(['locked', 'locktime'], 'mdl_gradgrad_loc_loc_ix');
            $table->index(['itemid', 'hidden'], 'mdl_gradgrad_ite_hid_ix');
            $table->index('rawscaleid');
            $table->index('usermodified');
        });
        
        // mdl_grade_categories table
        Schema::create('mdl_grade_categories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('courseid')->index();
            $table->bigInteger('parent')->nullable()->index();
            $table->bigInteger('depth')->default(0);
            $table->string('path', 255)->nullable();
            $table->string('fullname', 255);
            $table->bigInteger('aggregation')->default(13);
            $table->bigInteger('keephigh')->default(0);
            $table->bigInteger('droplow')->default(0);
            $table->smallInteger('aggregateonlygraded')->default(0);
            $table->smallInteger('aggregateoutcomes')->default(0);
            $table->bigInteger('timecreated');
            $table->bigInteger('timemodified');
            $table->smallInteger('hidden')->default(0);
        });
        
        // mdl_grade_history table
        Schema::create('mdl_grade_history', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('action', 10)->default('');
            $table->bigInteger('oldid');
            $table->string('source', 255)->nullable();
            $table->bigInteger('timemodified')->nullable();
            $table->bigInteger('loggeduser')->nullable()->index();
            $table->bigInteger('itemid')->index();
            $table->bigInteger('userid')->index();
            $table->decimal('rawgrade', 10, 5)->nullable();
            $table->decimal('rawgrademax', 10, 5)->default(100);
            $table->decimal('rawgrademin', 10, 5)->default(0);
            $table->bigInteger('rawscaleid')->nullable();
            $table->bigInteger('usermodified')->nullable()->index();
            $table->decimal('finalgrade', 10, 5)->nullable();
            $table->smallInteger('hidden')->default(0);
            $table->smallInteger('locked')->default(0);
            $table->bigInteger('locktime')->default(0);
            $table->smallInteger('exported')->default(0);
            $table->smallInteger('overridden')->default(0);
            $table->smallInteger('excluded')->default(0);
            $table->text('feedback')->nullable();
            $table->smallInteger('feedbackformat')->default(0);
            $table->text('information')->nullable();
            $table->smallInteger('informationformat')->default(0);
            
            $table->index(['oldid', 'action'], 'mdl_gradhist_old_act_ix');
            $table->index(['itemid', 'userid', 'timemodified'], 'mdl_gradhist_ite_use_tim_ix');
        });
        
        // mdl_gradingform_rubric_criteria table  
        Schema::create('mdl_gradingform_rubric_criteria', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('definitionid')->index();
            $table->bigInteger('sortorder');
            $table->text('description')->nullable();
            $table->smallInteger('descriptionformat')->nullable();
        });
        
        // mdl_gradingform_rubric_levels table
        Schema::create('mdl_gradingform_rubric_levels', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('criterionid')->index();
            $table->decimal('score', 10, 5);
            $table->text('definition')->nullable();
            $table->bigInteger('definitionformat')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mdl_gradingform_rubric_levels');
        Schema::dropIfExists('mdl_gradingform_rubric_criteria');
        Schema::dropIfExists('mdl_grade_history');
        Schema::dropIfExists('mdl_grade_categories');
        Schema::dropIfExists('mdl_grade_grades');
        Schema::dropIfExists('mdl_grade_items');
    }
};
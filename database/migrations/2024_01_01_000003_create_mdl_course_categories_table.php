<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Moodle-compatible course categories table (mdl_course_categories)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mdl_course_categories', function (Blueprint $table) {
            $table->bigIncrements('id');
            
            // Category name and description
            $table->string('name', 255);
            $table->string('idnumber', 100)->nullable()->index();
            $table->text('description')->nullable();
            $table->smallInteger('descriptionformat')->default(0);
            
            // Hierarchy
            $table->bigInteger('parent')->default(0)->index();
            $table->bigInteger('sortorder')->default(0);
            $table->bigInteger('coursecount')->default(0);
            
            // Visibility
            $table->boolean('visible')->default(1);
            $table->boolean('visibleold')->default(1);
            
            // Timestamps
            $table->bigInteger('timemodified')->default(0);
            
            // Depth and path for hierarchy
            $table->bigInteger('depth')->default(0);
            $table->string('path', 255)->nullable();
            
            // Theme
            $table->string('theme', 50)->nullable();
            
            // Indexes
            $table->index(['parent', 'sortorder'], 'mdl_cour_cat_par_sor_ix');
        });
        
        // Insert default category (Moodle requires this)
        DB::table('mdl_course_categories')->insert([
            'id' => 1,
            'name' => 'Miscellaneous',
            'idnumber' => '',
            'description' => '',
            'parent' => 0,
            'sortorder' => 10000,
            'coursecount' => 0,
            'visible' => 1,
            'visibleold' => 1,
            'timemodified' => time(),
            'depth' => 1,
            'path' => '/1',
            'theme' => ''
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('mdl_course_categories');
    }
};
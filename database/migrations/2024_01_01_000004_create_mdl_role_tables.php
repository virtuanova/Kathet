<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Moodle-compatible role tables
 */
return new class extends Migration
{
    public function up(): void
    {
        // mdl_role table
        Schema::create('mdl_role', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 255)->default('');
            $table->string('shortname', 100)->unique();
            $table->text('description')->default('');
            $table->bigInteger('sortorder')->unique();
            $table->string('archetype', 30)->default('');
            
            $table->index('sortorder');
        });
        
        // mdl_role_assignments table
        Schema::create('mdl_role_assignments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('roleid')->index();
            $table->bigInteger('contextid')->index();
            $table->bigInteger('userid')->index();
            $table->bigInteger('timemodified')->default(0);
            $table->bigInteger('modifierid')->default(0)->index();
            $table->string('component', 100)->default('');
            $table->bigInteger('itemid')->default(0);
            $table->bigInteger('sortorder')->default(0);
            
            $table->index(['roleid', 'contextid', 'userid'], 'mdl_roleassi_rolconuse_ix');
            $table->index(['contextid', 'roleid'], 'mdl_roleassi_conrol_ix');
            $table->index(['userid', 'contextid', 'roleid'], 'mdl_roleassi_useconrol_ix');
        });
        
        // mdl_role_capabilities table
        Schema::create('mdl_role_capabilities', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('contextid')->index();
            $table->bigInteger('roleid')->index();
            $table->string('capability', 255);
            $table->bigInteger('permission');
            $table->bigInteger('timemodified')->default(0);
            $table->bigInteger('modifierid')->default(0);
            
            $table->unique(['roleid', 'contextid', 'capability'], 'mdl_rolecapa_rolconcap_uk');
            $table->index('capability');
            $table->index(['contextid', 'roleid', 'capability'], 'mdl_rolecapa_conrolcap_ix');
        });
        
        // mdl_context table
        Schema::create('mdl_context', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('contextlevel')->default(0);
            $table->bigInteger('instanceid')->default(0)->index();
            $table->string('path', 255)->nullable();
            $table->bigInteger('depth')->default(0);
            $table->boolean('locked')->default(0);
            
            $table->unique(['contextlevel', 'instanceid'], 'mdl_cont_conins_uk');
            $table->index('path', 'mdl_cont_pat_ix')->charset('utf8mb4')->collation('utf8mb4_bin');
        });
        
        // mdl_capabilities table  
        Schema::create('mdl_capabilities', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 255)->unique();
            $table->string('captype', 50);
            $table->bigInteger('contextlevel')->default(0);
            $table->string('component', 100)->default('');
            $table->bigInteger('riskbitmask')->default(0);
        });
        
        // Insert default Moodle roles
        DB::table('mdl_role')->insert([
            ['id' => 1, 'name' => '', 'shortname' => 'manager', 'description' => 'Managers can access course and modify them, they usually do not participate in courses.', 'sortorder' => 1, 'archetype' => 'manager'],
            ['id' => 2, 'name' => '', 'shortname' => 'coursecreator', 'description' => 'Course creators can create new courses.', 'sortorder' => 2, 'archetype' => 'coursecreator'],
            ['id' => 3, 'name' => '', 'shortname' => 'editingteacher', 'description' => 'Teachers can do anything within a course, including changing the activities and grading students.', 'sortorder' => 3, 'archetype' => 'editingteacher'],
            ['id' => 4, 'name' => '', 'shortname' => 'teacher', 'description' => 'Non-editing teachers can teach in courses and grade students, but may not alter activities.', 'sortorder' => 4, 'archetype' => 'teacher'],
            ['id' => 5, 'name' => '', 'shortname' => 'student', 'description' => 'Students generally have fewer privileges within a course.', 'sortorder' => 5, 'archetype' => 'student'],
            ['id' => 6, 'name' => '', 'shortname' => 'guest', 'description' => 'Guests have minimal privileges and usually can not enter text anywhere.', 'sortorder' => 6, 'archetype' => 'guest'],
            ['id' => 7, 'name' => '', 'shortname' => 'user', 'description' => 'All logged in users.', 'sortorder' => 7, 'archetype' => 'user'],
            ['id' => 8, 'name' => '', 'shortname' => 'frontpage', 'description' => 'All logged in users in the frontpage course.', 'sortorder' => 8, 'archetype' => 'frontpage'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('mdl_capabilities');
        Schema::dropIfExists('mdl_context');
        Schema::dropIfExists('mdl_role_capabilities');
        Schema::dropIfExists('mdl_role_assignments');
        Schema::dropIfExists('mdl_role');
    }
};
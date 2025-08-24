<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Moodle-compatible enrollment tables
 */
return new class extends Migration
{
    public function up(): void
    {
        // mdl_enrol table - enrollment instances
        Schema::create('mdl_enrol', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('enrol', 20);
            $table->smallInteger('status')->default(0);
            $table->bigInteger('courseid')->index();
            $table->bigInteger('sortorder')->default(0);
            $table->string('name', 255)->nullable();
            $table->bigInteger('enrolperiod')->default(0);
            $table->bigInteger('enrolstartdate')->default(0);
            $table->bigInteger('enrolenddate')->default(0);
            $table->boolean('expirynotify')->default(0);
            $table->bigInteger('expirythreshold')->default(0);
            $table->boolean('notifyall')->default(0);
            $table->string('password', 50)->nullable();
            $table->string('cost', 20)->nullable();
            $table->string('currency', 3)->nullable();
            $table->bigInteger('roleid')->default(0);
            $table->bigInteger('customint1')->nullable();
            $table->bigInteger('customint2')->nullable();
            $table->bigInteger('customint3')->nullable();
            $table->bigInteger('customint4')->nullable();
            $table->bigInteger('customint5')->nullable();
            $table->bigInteger('customint6')->nullable();
            $table->bigInteger('customint7')->nullable();
            $table->bigInteger('customint8')->nullable();
            $table->string('customchar1', 255)->nullable();
            $table->string('customchar2', 255)->nullable();
            $table->string('customchar3', 1333)->nullable();
            $table->decimal('customdec1', 12, 7)->nullable();
            $table->decimal('customdec2', 12, 7)->nullable();
            $table->text('customtext1')->nullable();
            $table->text('customtext2')->nullable();
            $table->text('customtext3')->nullable();
            $table->text('customtext4')->nullable();
            $table->bigInteger('timecreated')->default(0);
            $table->bigInteger('timemodified')->default(0);
            
            $table->index(['enrol', 'status', 'courseid'], 'mdl_enro_enrcou_ix');
        });
        
        // mdl_user_enrolments table - user enrollments
        Schema::create('mdl_user_enrolments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->smallInteger('status')->default(0);
            $table->bigInteger('enrolid')->index();
            $table->bigInteger('userid')->index();
            $table->bigInteger('timestart')->default(0);
            $table->bigInteger('timeend')->default(2147483647);
            $table->bigInteger('modifierid')->default(0)->index();
            $table->bigInteger('timecreated')->default(0);
            $table->bigInteger('timemodified')->default(0);
            
            $table->unique(['enrolid', 'userid'], 'mdl_userenro_enruse_uk');
            $table->index(['status', 'enrolid'], 'mdl_userenro_staenr_ix');
        });
        
        // mdl_groups table
        Schema::create('mdl_groups', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('courseid')->index();
            $table->string('idnumber', 100)->default('');
            $table->string('name', 254);
            $table->text('description')->nullable();
            $table->smallInteger('descriptionformat')->default(0);
            $table->string('enrolmentkey', 50)->nullable();
            $table->bigInteger('picture')->default(0);
            $table->bigInteger('timecreated')->default(0);
            $table->bigInteger('timemodified')->default(0);
            
            $table->index(['courseid', 'idnumber'], 'mdl_grou_couidn_ix');
        });
        
        // mdl_groups_members table
        Schema::create('mdl_groups_members', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('groupid')->index();
            $table->bigInteger('userid')->index();
            $table->bigInteger('timeadded')->default(0);
            $table->string('component', 100)->default('');
            $table->bigInteger('itemid')->default(0);
            
            $table->unique(['groupid', 'userid'], 'mdl_groumemb_grouse_uk');
        });
        
        // mdl_groupings table
        Schema::create('mdl_groupings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('courseid')->index();
            $table->string('name', 255);
            $table->string('idnumber', 100)->default('');
            $table->text('description')->nullable();
            $table->smallInteger('descriptionformat')->default(0);
            $table->string('configdata', 255)->nullable();
            $table->bigInteger('timecreated')->default(0);
            $table->bigInteger('timemodified')->default(0);
            
            $table->index(['courseid', 'idnumber'], 'mdl_grou_couidn2_ix');
        });
        
        // mdl_groupings_groups table
        Schema::create('mdl_groupings_groups', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('groupingid')->index();
            $table->bigInteger('groupid')->index();
            $table->bigInteger('timeadded')->default(0);
            
            $table->index(['groupingid', 'groupid'], 'mdl_grougrou_grogro_ix');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mdl_groupings_groups');
        Schema::dropIfExists('mdl_groupings');
        Schema::dropIfExists('mdl_groups_members');
        Schema::dropIfExists('mdl_groups');
        Schema::dropIfExists('mdl_user_enrolments');
        Schema::dropIfExists('mdl_enrol');
    }
};
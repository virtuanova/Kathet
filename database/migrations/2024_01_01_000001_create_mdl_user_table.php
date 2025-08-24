<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Moodle-compatible user table (mdl_user)
 * This migration creates a table structure compatible with Moodle's user table
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mdl_user', function (Blueprint $table) {
            $table->bigIncrements('id');
            
            // Authentication fields
            $table->string('auth', 30)->default('manual');
            $table->boolean('confirmed')->default(0);
            $table->boolean('policyagreed')->default(0);
            $table->boolean('deleted')->default(0);
            $table->boolean('suspended')->default(0);
            $table->string('mnethostid', 9)->default('1');
            $table->string('username', 100)->unique();
            $table->string('password', 255);
            $table->string('idnumber', 255)->default('');
            
            // Personal information
            $table->string('firstname', 100);
            $table->string('lastname', 100);
            $table->string('email', 100)->index();
            $table->boolean('emailstop')->default(0);
            $table->string('phone1', 20)->default('');
            $table->string('phone2', 20)->default('');
            $table->string('institution', 255)->default('');
            $table->string('department', 255)->default('');
            $table->string('address', 255)->default('');
            $table->string('city', 120)->default('');
            $table->string('country', 2)->default('');
            $table->string('lang', 30)->default('en');
            $table->string('calendartype', 30)->default('gregorian');
            $table->string('theme', 50)->default('');
            $table->string('timezone', 100)->default('99');
            
            // Profile fields
            $table->bigInteger('firstaccess')->default(0);
            $table->bigInteger('lastaccess')->default(0);
            $table->bigInteger('lastlogin')->default(0);
            $table->bigInteger('currentlogin')->default(0);
            $table->string('lastip', 45)->default('');
            $table->string('secret', 15)->default('');
            $table->bigInteger('picture')->default(0);
            $table->text('description')->nullable();
            $table->smallInteger('descriptionformat')->default(1);
            
            // Email settings
            $table->smallInteger('mailformat')->default(1);
            $table->smallInteger('maildigest')->default(0);
            $table->smallInteger('maildisplay')->default(2);
            $table->smallInteger('autosubscribe')->default(1);
            $table->smallInteger('trackforums')->default(0);
            
            // Timestamps
            $table->bigInteger('timecreated')->default(0);
            $table->bigInteger('timemodified')->default(0);
            
            // Trust and image settings
            $table->boolean('trustbitmask')->default(0);
            $table->string('imagealt', 255)->nullable();
            $table->string('lastnamephonetic', 255)->nullable();
            $table->string('firstnamephonetic', 255)->nullable();
            $table->string('middlename', 255)->nullable();
            $table->string('alternatename', 255)->nullable();
            
            // Indexes for Moodle compatibility
            $table->index('mnethostid');
            $table->index('firstname');
            $table->index('lastname');
            $table->index('city');
            $table->index('country');
            $table->index('lastaccess');
            $table->index('confirmed');
            $table->index('auth');
            $table->index('suspended');
            $table->index('deleted');
            $table->index(['email', 'mnethostid'], 'mdl_user_ema_mnethostid_ix');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mdl_user');
    }
};
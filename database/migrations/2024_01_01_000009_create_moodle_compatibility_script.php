<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additional Moodle compatibility tables and settings
 */
return new class extends Migration
{
    public function up(): void
    {
        // mdl_config table - Moodle configuration
        Schema::create('mdl_config', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('plugin', 100)->default('');
            $table->string('name', 100);
            $table->text('value');
            
            $table->unique(['plugin', 'name'], 'mdl_conf_plunam_uk');
        });
        
        // mdl_sessions table - User sessions
        Schema::create('mdl_sessions', function (Blueprint $table) {
            $table->string('id', 128)->primary();
            $table->smallInteger('state')->default(0);
            $table->text('sessdata')->nullable();
            $table->bigInteger('userid')->nullable()->index();
            $table->bigInteger('timecreated')->default(0)->index();
            $table->bigInteger('timemodified')->default(0);
            $table->bigInteger('firstip')->nullable();
            $table->bigInteger('lastip')->nullable();
        });
        
        // mdl_files table - File storage
        Schema::create('mdl_files', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('contenthash', 40)->index();
            $table->string('pathnamehash', 40)->unique();
            $table->bigInteger('contextid')->index();
            $table->string('component', 100);
            $table->string('filearea', 50);
            $table->bigInteger('itemid');
            $table->string('filepath', 255);
            $table->string('filename', 255);
            $table->bigInteger('userid')->nullable()->index();
            $table->bigInteger('filesize');
            $table->string('mimetype', 100)->nullable();
            $table->smallInteger('status')->default(0);
            $table->text('source')->nullable();
            $table->string('author', 255)->nullable();
            $table->string('license', 255)->nullable();
            $table->bigInteger('timecreated');
            $table->bigInteger('timemodified');
            $table->smallInteger('sortorder')->default(0);
            $table->bigInteger('referencefileid')->nullable()->index();
            
            $table->index(['contextid', 'component', 'filearea', 'itemid'], 'mdl_file_con_com_fil_ite_ix');
        });
        
        // mdl_log table - Activity logs (legacy but still used)
        Schema::create('mdl_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('time')->index();
            $table->bigInteger('userid')->index();
            $table->string('ip', 45);
            $table->bigInteger('course');
            $table->string('module', 20);
            $table->string('cmid', 10)->default(0);
            $table->string('action', 40);
            $table->string('url', 100)->default('');
            $table->string('info', 255)->default('');
            
            $table->index(['course', 'time'], 'mdl_log_cou_tim_ix');
            $table->index(['userid', 'course'], 'mdl_log_use_cou_ix');
        });
        
        // mdl_message table - Messages
        Schema::create('mdl_message', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('useridfrom')->index();
            $table->bigInteger('useridto')->index();
            $table->string('subject', 255)->default('');
            $table->text('fullmessage')->nullable();
            $table->smallInteger('fullmessageformat')->default(0);
            $table->text('fullmessagehtml')->nullable();
            $table->text('smallmessage')->nullable();
            $table->bigInteger('notification')->default(0)->index();
            $table->bigInteger('contexturl')->nullable();
            $table->text('contexturlname')->nullable();
            $table->bigInteger('timecreated');
            $table->bigInteger('timeuserfromdeleted')->default(0);
            $table->bigInteger('timeusertodeleted')->default(0);
            $table->string('component', 100)->nullable();
            $table->string('eventtype', 100)->nullable();
            
            $table->index(['useridto', 'timecreated'], 'mdl_mess_usetim_ix');
            $table->index(['useridfrom', 'timecreated'], 'mdl_mess_usetim2_ix');
        });
        
        // Insert essential Moodle configuration
        $configs = [
            ['plugin' => '', 'name' => 'version', 'value' => '2023100900'],
            ['plugin' => '', 'name' => 'release', 'value' => '4.3+ (Build: 20231009)'],
            ['plugin' => '', 'name' => 'branch', 'value' => '403'],
            ['plugin' => '', 'name' => 'wwwroot', 'value' => 'http://localhost:8000'],
            ['plugin' => '', 'name' => 'dataroot', 'value' => '/var/www/moodledata'],
            ['plugin' => '', 'name' => 'dirroot', 'value' => '/var/www/html'],
            ['plugin' => '', 'name' => 'admin', 'value' => 'admin'],
            ['plugin' => '', 'name' => 'directorypermissions', 'value' => '02777'],
            ['plugin' => '', 'name' => 'running_installer', 'value' => '0'],
            ['plugin' => '', 'name' => 'sessiontimeout', 'value' => '7200'],
            ['plugin' => '', 'name' => 'formatstringstriptags', 'value' => '1'],
            ['plugin' => '', 'name' => 'defaultcity', 'value' => ''],
            ['plugin' => '', 'name' => 'defaultcountry', 'value' => ''],
            ['plugin' => '', 'name' => 'autolang', 'value' => '1'],
            ['plugin' => '', 'name' => 'lang', 'value' => 'en'],
            ['plugin' => '', 'name' => 'timezone', 'value' => '99'],
            ['plugin' => '', 'name' => 'forcetimezone', 'value' => '99'],
            ['plugin' => '', 'name' => 'country', 'value' => '0'],
        ];
        
        foreach ($configs as $config) {
            DB::table('mdl_config')->insert($config);
        }
        
        // Insert essential context levels
        Schema::create('mdl_context_temp', function (Blueprint $table) {
            $table->bigInteger('contextlevel');
            $table->bigInteger('instanceid');
            $table->string('path', 255);
            $table->bigInteger('depth');
        });
        
        // Insert system context
        DB::table('mdl_context')->insert([
            'contextlevel' => 10, // CONTEXT_SYSTEM
            'instanceid' => 0,
            'path' => '/1',
            'depth' => 1,
            'locked' => 0
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('mdl_context_temp');
        Schema::dropIfExists('mdl_message');
        Schema::dropIfExists('mdl_log');
        Schema::dropIfExists('mdl_files');
        Schema::dropIfExists('mdl_sessions');
        Schema::dropIfExists('mdl_config');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Moodle-compatible activity tables (assign, quiz, forum, etc.)
 */
return new class extends Migration
{
    public function up(): void
    {
        // mdl_assign table
        Schema::create('mdl_assign', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('course')->index();
            $table->string('name', 255);
            $table->text('intro');
            $table->smallInteger('introformat')->default(0);
            $table->smallInteger('alwaysshowdescription')->default(0);
            $table->smallInteger('nosubmissions')->default(0);
            $table->smallInteger('submissiondrafts')->default(0);
            $table->smallInteger('sendnotifications')->default(0);
            $table->smallInteger('sendlatenotifications')->default(0);
            $table->bigInteger('duedate')->default(0);
            $table->bigInteger('allowsubmissionsfromdate')->default(0);
            $table->bigInteger('grade')->default(0);
            $table->bigInteger('timemodified')->default(0);
            $table->smallInteger('requiresubmissionstatement')->default(0);
            $table->smallInteger('completionsubmit')->default(0);
            $table->bigInteger('cutoffdate')->default(0);
            $table->bigInteger('gradingduedate')->default(0);
            $table->smallInteger('teamsubmission')->default(0);
            $table->smallInteger('requireallteammemberssubmit')->default(0);
            $table->bigInteger('teamsubmissiongroupingid')->default(0);
            $table->smallInteger('blindmarking')->default(0);
            $table->smallInteger('hidegrader')->default(0);
            $table->smallInteger('revealidentities')->default(0);
            $table->string('attemptreopenmethod', 10)->default('none');
            $table->bigInteger('maxattempts')->default(-1);
            $table->smallInteger('markingworkflow')->default(0);
            $table->smallInteger('markingallocation')->default(0);
            $table->smallInteger('sendstudentnotifications')->default(1);
            $table->smallInteger('preventsubmissionnotingroup')->default(0);
            $table->text('activity')->nullable();
            $table->smallInteger('activityformat')->default(0);
            $table->bigInteger('timelimit')->default(0);
            $table->smallInteger('submissionattachments')->default(0);
        });
        
        // mdl_assign_submission table
        Schema::create('mdl_assign_submission', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('assignment')->index();
            $table->bigInteger('userid')->index();
            $table->bigInteger('timecreated')->default(0);
            $table->bigInteger('timemodified')->default(0);
            $table->bigInteger('timestarted')->nullable();
            $table->string('status', 10)->nullable();
            $table->bigInteger('groupid')->default(0);
            $table->bigInteger('attemptnumber')->default(0);
            $table->boolean('latest')->default(0);
            
            $table->unique(['assignment', 'userid', 'groupid', 'attemptnumber'], 'mdl_assisubm_assusegroatt_uk');
            $table->index(['assignment', 'userid', 'groupid', 'latest'], 'mdl_assisubm_assusegrolat_ix');
            $table->index('attemptnumber');
        });
        
        // mdl_quiz table
        Schema::create('mdl_quiz', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('course')->index();
            $table->string('name', 255);
            $table->text('intro');
            $table->smallInteger('introformat')->default(0);
            $table->bigInteger('timeopen')->default(0);
            $table->bigInteger('timeclose')->default(0);
            $table->bigInteger('timelimit')->default(0);
            $table->string('overduehandling', 16)->default('autoabandon');
            $table->bigInteger('graceperiod')->default(0);
            $table->string('preferredbehaviour', 32)->default('');
            $table->smallInteger('canredoquestions')->default(0);
            $table->smallInteger('attempts')->default(0);
            $table->smallInteger('attemptonlast')->default(0);
            $table->smallInteger('grademethod')->default(1);
            $table->decimal('decimalpoints', 2, 0)->default(2);
            $table->smallInteger('questiondecimalpoints')->default(-1);
            $table->bigInteger('reviewattempt')->default(0);
            $table->bigInteger('reviewcorrectness')->default(0);
            $table->bigInteger('reviewmarks')->default(0);
            $table->bigInteger('reviewspecificfeedback')->default(0);
            $table->bigInteger('reviewgeneralfeedback')->default(0);
            $table->bigInteger('reviewrightanswer')->default(0);
            $table->bigInteger('reviewoverallfeedback')->default(0);
            $table->bigInteger('questionsperpage')->default(0);
            $table->string('navmethod', 16)->default('free');
            $table->smallInteger('shuffleanswers')->default(0);
            $table->decimal('sumgrades', 10, 5)->default(0);
            $table->decimal('grade', 10, 5)->default(0);
            $table->bigInteger('timecreated')->default(0);
            $table->bigInteger('timemodified')->default(0);
            $table->string('password', 255)->default('');
            $table->string('subnet', 255)->default('');
            $table->smallInteger('browsersecurity', 32)->default('-');
            $table->bigInteger('delay1')->default(0);
            $table->bigInteger('delay2')->default(0);
            $table->smallInteger('showuserpicture')->default(0);
            $table->smallInteger('showblocks')->default(0);
            $table->smallInteger('completionattemptsexhausted')->default(0);
            $table->smallInteger('completionminattempts')->default(0);
            $table->smallInteger('allowofflineattempts')->default(0);
        });
        
        // mdl_forum table
        Schema::create('mdl_forum', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('course')->index();
            $table->string('type', 20)->default('general');
            $table->string('name', 255);
            $table->text('intro');
            $table->smallInteger('introformat')->default(0);
            $table->bigInteger('duedate')->default(0);
            $table->bigInteger('cutoffdate')->default(0);
            $table->smallInteger('assessed')->default(0);
            $table->bigInteger('assesstimestart')->default(0);
            $table->bigInteger('assesstimefinish')->default(0);
            $table->bigInteger('scale')->default(0);
            $table->bigInteger('grade_forum')->default(0);
            $table->smallInteger('grade_forum_notify')->default(0);
            $table->bigInteger('maxbytes')->default(0);
            $table->bigInteger('maxattachments')->default(1);
            $table->smallInteger('forcesubscribe')->default(0);
            $table->smallInteger('trackingtype')->default(1);
            $table->smallInteger('rsstype')->default(0);
            $table->smallInteger('rssarticles')->default(0);
            $table->bigInteger('timemodified')->default(0);
            $table->bigInteger('warnafter')->default(0);
            $table->bigInteger('blockafter')->default(0);
            $table->bigInteger('blockperiod')->default(0);
            $table->smallInteger('completiondiscussions')->default(0);
            $table->smallInteger('completionreplies')->default(0);
            $table->smallInteger('completionposts')->default(0);
            $table->smallInteger('displaywordcount')->default(0);
            $table->smallInteger('lockdiscussionafter')->default(0);
        });
        
        // mdl_forum_discussions table
        Schema::create('mdl_forum_discussions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('course')->index();
            $table->bigInteger('forum')->index();
            $table->string('name', 255);
            $table->bigInteger('firstpost');
            $table->bigInteger('userid')->index();
            $table->bigInteger('groupid')->default(-1);
            $table->boolean('assessed')->default(1);
            $table->bigInteger('timemodified')->default(0);
            $table->bigInteger('usermodified')->default(0);
            $table->bigInteger('timestart')->default(0);
            $table->bigInteger('timeend')->default(0);
            $table->boolean('pinned')->default(0);
            $table->bigInteger('timelocked')->default(0);
            
            $table->index(['forum', 'pinned', 'timemodified'], 'mdl_forudisc_for_ix');
            $table->index(['userid', 'forum'], 'mdl_forudisc_use_for_ix');
        });
        
        // mdl_forum_posts table
        Schema::create('mdl_forum_posts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('discussion')->index();
            $table->bigInteger('parent')->default(0)->index();
            $table->bigInteger('userid')->index();
            $table->bigInteger('created')->default(0)->index();
            $table->bigInteger('modified')->default(0);
            $table->smallInteger('mailed')->default(0)->index();
            $table->string('subject', 255);
            $table->text('message');
            $table->smallInteger('messageformat')->default(0);
            $table->smallInteger('messagetrust')->default(0);
            $table->string('attachment', 100)->default('');
            $table->bigInteger('totalscore')->default(0);
            $table->smallInteger('mailnow')->default(0);
            $table->boolean('deleted')->default(0);
            $table->bigInteger('privatereplyto')->default(0);
            $table->boolean('wordcount')->nullable();
            $table->bigInteger('charcount')->nullable();
        });
        
        // mdl_resource table
        Schema::create('mdl_resource', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('course')->index();
            $table->string('name', 255);
            $table->text('intro')->nullable();
            $table->smallInteger('introformat')->default(0);
            $table->string('tobemigrated')->default(0);
            $table->bigInteger('legacyfiles')->default(0);
            $table->bigInteger('legacyfileslast')->nullable();
            $table->smallInteger('display')->default(0);
            $table->text('displayoptions')->nullable();
            $table->smallInteger('filterfiles')->default(0);
            $table->bigInteger('revision')->default(0);
            $table->bigInteger('timemodified')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mdl_resource');
        Schema::dropIfExists('mdl_forum_posts');
        Schema::dropIfExists('mdl_forum_discussions');
        Schema::dropIfExists('mdl_forum');
        Schema::dropIfExists('mdl_quiz');
        Schema::dropIfExists('mdl_assign_submission');
        Schema::dropIfExists('mdl_assign');
    }
};
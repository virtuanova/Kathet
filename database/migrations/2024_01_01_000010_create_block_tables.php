<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Moodle-compatible block system tables
 */
return new class extends Migration
{
    public function up(): void
    {
        // mdl_block_instances table
        Schema::create('mdl_block_instances', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('blockname', 40);
            $table->bigInteger('parentcontextid');
            $table->boolean('showinsubcontexts')->default(0);
            $table->string('pagetypepattern', 64);
            $table->string('subpagepattern', 16)->nullable();
            $table->string('defaultregion', 16);
            $table->bigInteger('defaultweight');
            $table->text('configdata')->nullable();
            $table->boolean('visible')->default(1);
            $table->bigInteger('timecreated');
            $table->bigInteger('timemodified');
            
            $table->index(['parentcontextid', 'showinsubcontexts'], 'mdl_blocinst_parsho_ix');
            $table->index('blockname');
            $table->index('visible');
        });
        
        // mdl_block_positions table
        Schema::create('mdl_block_positions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('blockinstanceid');
            $table->bigInteger('contextid');
            $table->string('pagetype', 64);
            $table->string('subpage', 16);
            $table->boolean('visible');
            $table->string('region', 16);
            $table->bigInteger('weight');
            
            $table->unique(['blockinstanceid', 'contextid', 'pagetype', 'subpage'], 'mdl_blocposi_bloconpagsub_uk');
            $table->index(['contextid', 'pagetype', 'subpage'], 'mdl_blocposi_conpagsub_ix');
        });
        
        // mdl_block_rss_client table (for RSS blocks)
        Schema::create('mdl_block_rss_client', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('userid');
            $table->string('title', 255);
            $table->text('preferredtitle');
            $table->text('description');
            $table->boolean('shared')->default(0);
            $table->text('url');
            $table->bigInteger('skiptime')->default(0);
            $table->bigInteger('skipuntil')->default(0);
            
            $table->index('userid');
        });
        
        // Insert default block configurations
        $this->insertDefaultBlocks();
    }

    public function down(): void
    {
        Schema::dropIfExists('mdl_block_rss_client');
        Schema::dropIfExists('mdl_block_positions');
        Schema::dropIfExists('mdl_block_instances');
    }

    /**
     * Insert default block types and instances
     */
    protected function insertDefaultBlocks(): void
    {
        // Create default block instances for front page
        $systemContextId = 1; // System context
        $frontPageContextId = 2; // Front page context
        
        $defaultBlocks = [
            [
                'blockname' => 'navigation',
                'parentcontextid' => $systemContextId,
                'pagetypepattern' => '*',
                'defaultregion' => 'side-pre',
                'defaultweight' => 0,
                'visible' => 1,
            ],
            [
                'blockname' => 'settings',
                'parentcontextid' => $systemContextId,
                'pagetypepattern' => '*',
                'defaultregion' => 'side-pre',
                'defaultweight' => 1,
                'visible' => 1,
            ],
            [
                'blockname' => 'course_list',
                'parentcontextid' => $frontPageContextId,
                'pagetypepattern' => 'site-index',
                'defaultregion' => 'side-post',
                'defaultweight' => 0,
                'visible' => 1,
            ],
            [
                'blockname' => 'calendar_month',
                'parentcontextid' => $frontPageContextId,
                'pagetypepattern' => 'site-index',
                'defaultregion' => 'side-post',
                'defaultweight' => 1,
                'visible' => 1,
            ],
            [
                'blockname' => 'recent_activity',
                'parentcontextid' => $systemContextId,
                'pagetypepattern' => 'course-view-*',
                'defaultregion' => 'side-post',
                'defaultweight' => 0,
                'visible' => 1,
            ],
        ];

        foreach ($defaultBlocks as $block) {
            $instanceId = DB::table('mdl_block_instances')->insertGetId([
                'blockname' => $block['blockname'],
                'parentcontextid' => $block['parentcontextid'],
                'showinsubcontexts' => 1,
                'pagetypepattern' => $block['pagetypepattern'],
                'subpagepattern' => null,
                'defaultregion' => $block['defaultregion'],
                'defaultweight' => $block['defaultweight'],
                'configdata' => '',
                'visible' => $block['visible'],
                'timecreated' => time(),
                'timemodified' => time(),
            ]);

            // Create corresponding position record
            DB::table('mdl_block_positions')->insert([
                'blockinstanceid' => $instanceId,
                'contextid' => $block['parentcontextid'],
                'pagetype' => str_replace('*', 'site-index', $block['pagetypepattern']),
                'subpage' => '',
                'visible' => $block['visible'],
                'region' => $block['defaultregion'],
                'weight' => $block['defaultweight'],
            ]);
        }
    }
};
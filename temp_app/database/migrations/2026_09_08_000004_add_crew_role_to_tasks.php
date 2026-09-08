<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which multimedia role a generated production task belongs to.
 *
 * Accepting coverage claims the tasks for the role taken on, and that was being
 * worked out by matching words in the title — which quietly failed: no title
 * contains "photo" or "video", so photo and video editors claimed nothing at
 * all, and "shot list" never matched "%shoot%". The role is recorded here
 * instead of being guessed from prose.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->string('crew_role')->nullable()->index()->after('for_team');
        });

        // Tasks raised before this column exists keep a null role. Those stay
        // claimable by whoever accepts, so old events do not strand work.
        foreach ([
            'Prepare brief and shot list' => 'shooter',
            'Check gear and coverage plan' => 'shooter',
            'Cover event' => 'shooter',
            'Edit and deliver event photos' => 'photo',
            'Edit and deliver event videos' => 'video',
        ] as $title => $role) {
            DB::table('tasks')->where('title', $title)->update(['crew_role' => $role]);
        }
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropColumn('crew_role');
        });
    }
};

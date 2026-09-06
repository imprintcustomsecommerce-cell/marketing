<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Coverage used to appear for multimedia only because the list was driven by
 * events — nothing was ever handed over, so nobody could say whether the crew
 * had seen a job or simply not noticed it. These columns give the handoff a
 * state: marketing requests, multimedia picks it up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coverages', function (Blueprint $table) {
            $table->string('stage')->default('requested')->index()->after('event_id');

            $table->timestamp('requested_at')->nullable()->after('stage');
            $table->foreignId('requested_by')->nullable()->after('requested_at')->constrained('users')->nullOnDelete();

            $table->timestamp('accepted_at')->nullable()->after('requested_by');
            $table->foreignId('accepted_by')->nullable()->after('accepted_at')->constrained('users')->nullOnDelete();
        });

        // Rows that already exist were logged by the crew themselves, so they
        // were plainly seen: backfilling them as "requested" would drop every
        // historical event into the new-work queue on the day this ships.
        DB::table('coverages')->update([
            'stage' => 'accepted',
            'accepted_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Same as elsewhere: the index on "stage" has to go first, or SQLite
        // rebuilds the table around an index for a column being dropped.
        Schema::table('coverages', function (Blueprint $table) {
            $table->dropIndex(['stage']);
        });

        Schema::table('coverages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requested_by');
            $table->dropConstrainedForeignId('accepted_by');
            $table->dropColumn(['stage', 'requested_at', 'accepted_at']);
        });
    }
};

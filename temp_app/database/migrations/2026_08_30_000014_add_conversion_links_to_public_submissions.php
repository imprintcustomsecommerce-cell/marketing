<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_submissions', function (Blueprint $table) {
            // What this inquiry turned into, so it is converted once and the
            // record it produced can be reached from the inquiry afterwards.
            $table->foreignId('converted_event_id')->nullable()->after('handled_at')->constrained('events')->nullOnDelete();
            $table->foreignId('converted_endorser_id')->nullable()->after('converted_event_id')->constrained('endorsers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('public_submissions', function (Blueprint $table) {
            $table->dropForeign(['converted_event_id']);
            $table->dropForeign(['converted_endorser_id']);
            $table->dropColumn(['converted_event_id', 'converted_endorser_id']);
        });
    }
};

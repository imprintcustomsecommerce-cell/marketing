<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_submissions', function (Blueprint $table) {
            // Who picked the inquiry up, and what the team said about it. The
            // public's own words stay in `data`; this is the staff side.
            $table->text('internal_notes')->nullable()->after('comment');
            $table->foreignId('handled_by')->nullable()->after('internal_notes')->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable()->after('handled_by');
        });
    }

    public function down(): void
    {
        Schema::table('public_submissions', function (Blueprint $table) {
            $table->dropForeign(['handled_by']);
            $table->dropColumn(['internal_notes', 'handled_by', 'handled_at']);
        });
    }
};

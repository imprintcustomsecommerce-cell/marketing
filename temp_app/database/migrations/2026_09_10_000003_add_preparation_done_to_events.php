<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which of the chosen preparation items are actually finished.
 *
 * The checklist has two stages and they were sharing one column, so ticking an
 * item while booking the event marked it already done. `preparation` is now the
 * list of what this event needs, picked on the form; this holds what has since
 * been sorted, ticked off on the event and coverage screens.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->json('preparation_done')->nullable()->after('preparation');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('preparation_done');
        });
    }
};

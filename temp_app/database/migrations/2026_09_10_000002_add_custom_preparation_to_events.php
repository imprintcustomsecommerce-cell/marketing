<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Checklist items typed in for one particular event.
 *
 * The standing list in Event::PREPARATION covers what every booth needs. This
 * holds the one-offs — a borrowed generator, a permit only this venue asks for —
 * as a list of {label, done}, so an item carries its own wording rather than
 * relying on a key that means nothing outside the event it was added to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->json('custom_preparation')->nullable()->after('preparation');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('custom_preparation');
        });
    }
};

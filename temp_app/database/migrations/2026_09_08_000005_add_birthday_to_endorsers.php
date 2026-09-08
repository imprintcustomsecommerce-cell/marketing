<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An endorser's birthday, so the shop can greet them.
 *
 * Nullable: most of the roster is already on file without one, and a birthday
 * is something you learn over time rather than ask for up front.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('endorsers', function (Blueprint $table): void {
            $table->date('birthday')->nullable()->after('team_or_group');
        });
    }

    public function down(): void
    {
        Schema::table('endorsers', function (Blueprint $table): void {
            $table->dropColumn('birthday');
        });
    }
};

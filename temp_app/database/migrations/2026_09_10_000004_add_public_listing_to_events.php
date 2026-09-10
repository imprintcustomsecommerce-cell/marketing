<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether an event is shown on the public website calendar.
 *
 * Off by default, and deliberately so: the diary holds private hall bookings,
 * client work, and internal shoots alongside the ride-outs worth advertising.
 * Publishing has to be something somebody chooses for one event, never
 * something that happens because a record exists.
 *
 * The blurb is what customers read. The event's internal notes are for the shop
 * and never leave it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->boolean('is_public')->default(false)->index()->after('status');
            $table->text('public_summary')->nullable()->after('is_public');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn(['is_public', 'public_summary']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the shop needs to know before taking a booth to somebody else's event.
 *
 * The diary already held the day, place, and who to ring. This adds the parts
 * that decide whether the booth can actually be run: when the crew get in and
 * out, how big the space is, whether it is under cover, what the deal is worth,
 * and the list of things to load into the van.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            // Ingress and egress are the load-in and load-out days, which are
            // often the day either side of the event and drive the crew's call
            // time rather than the event date itself.
            $table->date('ingress_date')->nullable()->after('event_date');
            $table->date('egress_date')->nullable()->after('ingress_date');
            $table->unsignedSmallInteger('duration_days')->nullable()->after('egress_date');

            $table->string('booth_size')->nullable()->after('venue');
            $table->string('venue_type')->nullable()->after('booth_size');

            // Ex-deal is paid in product or exposure rather than cash, so the
            // amount only means anything on a cash deal.
            $table->string('deal_type')->nullable()->after('estimated_pax');
            $table->decimal('cash_amount', 12, 2)->nullable()->after('deal_type');

            // Checked items only, like the coverage checklist: the master list
            // lives in the model so it can be reworded without a migration.
            $table->json('preparation')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn([
                'ingress_date', 'egress_date', 'duration_days',
                'booth_size', 'venue_type', 'deal_type', 'cash_amount', 'preparation',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pr_kits', function (Blueprint $table) {
            // Two kinds of kit leave the shop: one sent to a named endorser,
            // which owes content back, and giveaway stock handed out at an
            // event or raffle, which owes nothing.
            $table->string('purpose')->default('endorser')->after('recipient')->index();
            $table->unsignedSmallInteger('quantity')->default(1)->after('purpose');
        });
    }

    public function down(): void
    {
        Schema::table('pr_kits', function (Blueprint $table) {
            $table->dropColumn(['purpose', 'quantity']);
        });
    }
};

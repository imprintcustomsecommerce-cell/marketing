<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pr_kits', function (Blueprint $table) {
            // House rule: one kit a month, and each kit owes two pieces of
            // content. The quota is stored per kit so a one-off arrangement can
            // differ without changing everyone else's.
            $table->unsignedTinyInteger('content_quota')->default(2)->after('status');
            $table->timestamp('obligations_generated_at')->nullable()->after('content_quota');
        });

        Schema::table('obligations', function (Blueprint $table) {
            $table->foreignId('pr_kit_id')->nullable()->after('event_id')->constrained()->cascadeOnDelete();
            // Which of the kit's required pieces this row is (1 of 2, 2 of 2).
            $table->unsignedTinyInteger('sequence')->nullable()->after('pr_kit_id');
        });
    }

    public function down(): void
    {
        Schema::table('obligations', function (Blueprint $table) {
            $table->dropForeign(['pr_kit_id']);
            $table->dropColumn(['pr_kit_id', 'sequence']);
        });

        Schema::table('pr_kits', function (Blueprint $table) {
            $table->dropColumn(['content_quota', 'obligations_generated_at']);
        });
    }
};

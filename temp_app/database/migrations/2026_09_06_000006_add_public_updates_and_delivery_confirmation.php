<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_submissions', fn (Blueprint $table) => $table->text('public_update')->nullable());
        Schema::table('coverages', function (Blueprint $table) {
            $table->timestamp('delivery_sent_at')->nullable();
            $table->foreignId('delivery_sent_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('coverages', function (Blueprint $table) {
            $table->dropForeign(['delivery_sent_by']);
            $table->dropColumn(['delivery_sent_at', 'delivery_sent_by']);
        });
        Schema::table('public_submissions', fn (Blueprint $table) => $table->dropColumn('public_update'));
    }
};

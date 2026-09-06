<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coverages', function (Blueprint $table) {
            $table->date('photo_due_on')->nullable()->index();
            $table->date('video_due_on')->nullable()->index();
            $table->json('checklist')->nullable();
            $table->string('delivery_url', 1000)->nullable();
        });
        Schema::table('events', fn (Blueprint $table) => $table->timestamp('archived_at')->nullable()->index());
        Schema::table('tasks', fn (Blueprint $table) => $table->timestamp('archived_at')->nullable()->index());
    }

    public function down(): void
    {
        Schema::table('coverages', fn (Blueprint $table) => $table->dropColumn(['photo_due_on', 'video_due_on', 'checklist', 'delivery_url']));
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn('archived_at'));
        Schema::table('tasks', fn (Blueprint $table) => $table->dropColumn('archived_at'));
    }
};

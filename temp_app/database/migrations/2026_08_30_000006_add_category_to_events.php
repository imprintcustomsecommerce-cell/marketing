<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // The three kinds of event the shop runs. Named `category` rather
            // than `event_type` so it is never confused with the free-text
            // "what kind of party" field on the public function hall form.
            $table->string('category')->default('tambike')->after('name')->index();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};

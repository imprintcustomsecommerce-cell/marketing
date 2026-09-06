<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // The coordination thread for this event — organisers, riders, or
            // the venue group — so staff can open it straight from the list.
            $table->string('group_chat_url')->nullable()->after('venue');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('group_chat_url');
        });
    }
};

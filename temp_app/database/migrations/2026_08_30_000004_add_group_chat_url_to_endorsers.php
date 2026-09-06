<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('endorsers', function (Blueprint $table) {
            // The Messenger / Viber / WhatsApp group where this endorser is
            // coordinated, so staff can open the thread straight from the list.
            $table->string('group_chat_url')->nullable()->after('social_media_url');
        });
    }

    public function down(): void
    {
        Schema::table('endorsers', function (Blueprint $table) {
            $table->dropColumn('group_chat_url');
        });
    }
};

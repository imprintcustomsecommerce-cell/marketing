<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A task raised for the multimedia team belongs to nobody until someone there
 * takes it, so the owner has to be allowed to be empty. Everything already on a
 * board keeps its owner and is untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();

            // Which team it was raised for. Null is an ordinary personal card.
            $table->string('for_team')->nullable()->index()->after('user_id');
            $table->timestamp('claimed_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        // The index has to go before the column it covers, or SQLite rebuilds
        // the table around an index pointing at a column that is no longer
        // there and the rollback fails halfway.
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['for_team']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['for_team', 'claimed_at']);
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};

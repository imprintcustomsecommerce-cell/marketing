<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The activity log is gone, and its table with it.
 *
 * Nothing writes to it any more — the LogsActivity trait was taken off every
 * model — so the table would sit here filling with nothing and inviting
 * somebody to wire it back up by halves.
 *
 * Rolling back rebuilds the table but not its rows: an audit trail cannot be
 * recovered once dropped, which is the point worth remembering before running
 * this anywhere the history still matters.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('activity_logs');
    }

    public function down(): void
    {
        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 40)->index();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->string('description');
            $table->json('changes')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });
    }
};

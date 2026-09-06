<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coverages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();

            // Not every event gets a shooter — the company assigns one only
            // sometimes — so this stays nullable and the screens say so plainly.
            $table->foreignId('shooter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('photo_editor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('video_editor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('photo_status')->default('not_started')->index();
            $table->date('photo_posted_on')->nullable();
            $table->string('video_status')->default('not_started')->index();
            $table->date('video_posted_on')->nullable();

            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One coverage log per event.
            $table->unique('event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coverages');
    }
};

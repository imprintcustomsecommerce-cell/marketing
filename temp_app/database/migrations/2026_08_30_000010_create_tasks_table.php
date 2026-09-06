<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('details')->nullable();
            // The board is per person per day, so the date is part of the task
            // rather than something derived from when it was created.
            $table->date('task_date')->index();
            $table->string('status')->default('todo')->index();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'task_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};

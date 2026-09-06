<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('task_date');
            $table->timestamp('submitted_at');
            $table->text('note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('feedback')->nullable();
            $table->timestamps();

            // One submission per person per day; sending again re-opens it.
            $table->unique(['user_id', 'task_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_submissions');
    }
};

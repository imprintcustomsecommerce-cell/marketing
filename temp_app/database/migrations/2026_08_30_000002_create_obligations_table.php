<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obligations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('endorser_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('type')->default('social_post')->index();
            $table->text('description')->nullable();
            $table->date('due_date')->index();
            $table->date('completed_on')->nullable();
            $table->string('proof_url')->nullable();
            $table->string('status')->default('pending')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obligations');
    }
};

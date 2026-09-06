<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('public_link_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->index();
            $table->string('status')->default('new_inquiry')->index();
            $table->string('response')->nullable();
            $table->text('comment')->nullable();
            $table->json('data')->nullable();
            $table->json('uploads')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_submissions');
    }
};

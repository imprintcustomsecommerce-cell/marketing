<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_links', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->string('resource_type');
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->json('visible_data');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->unsignedInteger('max_submissions')->nullable();
            $table->unsignedInteger('submission_count')->default(0);
            $table->string('password')->nullable();
            $table->boolean('allow_confirmation')->default(true);
            $table->boolean('allow_change_request')->default(true);
            $table->timestamps();
            $table->index(['resource_type', 'resource_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_links');
    }
};

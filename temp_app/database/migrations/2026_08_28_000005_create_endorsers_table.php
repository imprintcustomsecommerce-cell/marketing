<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('endorsers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('individual')->index();
            $table->string('contact_number')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('team_or_group')->nullable();
            $table->string('social_media_url')->nullable();
            $table->string('status')->default('new')->index();
            $table->text('profile')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('endorsers');
    }
};

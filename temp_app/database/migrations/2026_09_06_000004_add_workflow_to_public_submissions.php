<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_submissions', function (Blueprint $table): void {
            $table->string('priority')->default('normal')->after('status')->index();
            $table->foreignId('assigned_to')->nullable()->after('priority')->constrained('users')->nullOnDelete();
            $table->dateTime('follow_up_at')->nullable()->after('assigned_to')->index();
        });

        Schema::create('inquiry_contact_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('public_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->index();
            $table->text('note');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiry_contact_logs');

        Schema::table('public_submissions', function (Blueprint $table): void {
            $table->dropForeign(['assigned_to']);
            $table->dropColumn(['priority', 'assigned_to', 'follow_up_at']);
        });
    }
};

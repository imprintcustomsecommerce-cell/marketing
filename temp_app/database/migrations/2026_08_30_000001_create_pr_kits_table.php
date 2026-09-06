<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pr_kits', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->nullable();
            $table->string('recipient');
            $table->foreignId('endorser_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->text('contents')->nullable();
            $table->string('courier')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('address')->nullable();
            // Two separate legs: the kit goes out, and (for loaned items) comes back.
            $table->date('delivery_date')->nullable()->index();
            $table->date('pickup_date')->nullable()->index();
            $table->string('status')->default('scheduled')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pr_kits');
    }
};

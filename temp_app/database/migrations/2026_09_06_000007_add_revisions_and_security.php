<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('coverage_revisions', function (Blueprint $table) {
            $table->id(); $table->foreignId('coverage_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('round'); $table->text('notes'); $table->date('due_on')->nullable();
            $table->timestamp('completed_at')->nullable(); $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps();
            $table->unique(['coverage_id','round']);
        });
        Schema::table('users', function (Blueprint $table) { $table->boolean('must_change_password')->default(false); $table->timestamp('last_login_at')->nullable(); $table->string('last_login_ip',45)->nullable(); });
    }
    public function down(): void { Schema::dropIfExists('coverage_revisions'); Schema::table('users', fn(Blueprint $table) => $table->dropColumn(['must_change_password','last_login_at','last_login_ip'])); }
};

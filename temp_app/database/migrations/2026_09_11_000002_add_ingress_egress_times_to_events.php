<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::table('events', function(Blueprint $t): void { $t->time('ingress_time')->nullable(); $t->time('egress_time')->nullable(); }); } public function down(): void { Schema::table('events', fn(Blueprint $t)=>$t->dropColumn(['ingress_time','egress_time'])); } };

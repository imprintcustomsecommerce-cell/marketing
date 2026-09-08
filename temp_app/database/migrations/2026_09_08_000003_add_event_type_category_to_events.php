<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::table('events', function(Blueprint $t): void { $t->string('event_type')->default('outside_event')->after('name'); $t->string('event_category')->default('others')->after('event_type'); }); } public function down(): void { Schema::table('events', fn(Blueprint $t)=>$t->dropColumn(['event_type','event_category'])); } };

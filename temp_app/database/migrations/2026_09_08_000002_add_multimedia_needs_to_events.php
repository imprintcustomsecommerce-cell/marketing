<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::table('events', function(Blueprint $t): void { $t->unsignedTinyInteger('shooters_needed')->default(1); $t->unsignedTinyInteger('photo_editors_needed')->default(1); $t->unsignedTinyInteger('video_editors_needed')->default(1); }); } public function down(): void { Schema::table('events', fn(Blueprint $t)=>$t->dropColumn(['shooters_needed','photo_editors_needed','video_editors_needed'])); } };

<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::table('events', fn(Blueprint $t)=>$t->decimal('exdeal_amount',12,2)->nullable()->after('cash_amount')); } public function down(): void { Schema::table('events', fn(Blueprint $t)=>$t->dropColumn('exdeal_amount')); } };

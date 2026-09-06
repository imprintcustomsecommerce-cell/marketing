<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up():void{Schema::table('coverages',function(Blueprint $t){$t->string('client_review_status')->nullable();$t->text('client_review_comment')->nullable();$t->timestamp('client_reviewed_at')->nullable();});} public function down():void{Schema::table('coverages',fn(Blueprint $t)=>$t->dropColumn(['client_review_status','client_review_comment','client_reviewed_at']));}};

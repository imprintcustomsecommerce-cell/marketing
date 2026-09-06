<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Team is which side of the shop someone works on; role is what
            // they may do in the hub. They are independent: the administrator
            // is also one of the three marketing people.
            $table->string('team')->default('marketing')->after('role')->index();
        });

        DB::table('users')->update(['team' => 'marketing']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('team');
        });
    }
};

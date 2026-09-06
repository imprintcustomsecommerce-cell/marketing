<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('contact_person')->nullable()->after('organization');
            $table->string('contact_number', 60)->nullable()->after('contact_person');
            $table->string('contact_email')->nullable()->after('contact_number');
        });
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn(['contact_person', 'contact_number', 'contact_email']));
    }
};

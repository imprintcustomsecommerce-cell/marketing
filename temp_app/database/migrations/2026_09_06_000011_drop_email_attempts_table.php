<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * The email notification feature has been removed, so its delivery log goes
 * with it. dropIfExists rather than drop: a database created after the original
 * create-migration was deleted never had this table in the first place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('email_attempts');
    }

    public function down(): void
    {
        // Nothing to restore. The feature that wrote this table is gone.
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * On MySQL/MariaDB with explicit_defaults_for_timestamp off, the first
     * NOT NULL TIMESTAMP column of a table silently gets ON UPDATE
     * CURRENT_TIMESTAMP — so every workflow step rewrote calculated_at.
     * DATETIME has no such behaviour. (Use dateTime, not timestamp, for
     * NOT NULL "when did it happen" columns.)
     */
    public function up(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->dateTime('calculated_at')->change();
        });
    }

    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->timestamp('calculated_at')->change();
        });
    }
};

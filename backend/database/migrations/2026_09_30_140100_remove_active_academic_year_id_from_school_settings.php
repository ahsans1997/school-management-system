<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The current academic year lives only in academic_years.is_current.
     * A second pointer on school_settings would be duplicate state that
     * could drift out of sync ("enter data once", spec §68).
     */
    public function up(): void
    {
        Schema::table('school_settings', function (Blueprint $table) {
            $table->dropIndex(['active_academic_year_id']);
            $table->dropColumn('active_academic_year_id');
        });
    }

    public function down(): void
    {
        Schema::table('school_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('active_academic_year_id')->nullable()->index();
        });
    }
};

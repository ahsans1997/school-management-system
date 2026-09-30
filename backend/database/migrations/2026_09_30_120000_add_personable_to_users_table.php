<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a nullable polymorphic link from a user to a person record
     * (student/teacher/guardian/employee). The target tables are created
     * by their own feature modules; this only stores the link.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Adds personable_type + personable_id (nullable) with a composite index.
            $table->nullableMorphs('personable');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropMorphs('personable');
        });
    }
};

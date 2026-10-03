<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The class/grade catalogue (spec §13), shared across academic years —
     * the year is pinned at enrollment (Module 13). Tables that reference a
     * class (sections, class_subjects, enrollments…) must use ON DELETE
     * RESTRICT so an in-use class cannot be deleted.
     */
    public function up(): void
    {
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            // Sort + promotion key (next level = current + 1). Negative for pre-primary.
            $table->smallInteger('numeric_level')->index();
            $table->string('shift', 20)->nullable();   // App\Enums\Shift
            $table->string('medium', 20)->nullable();  // App\Enums\Medium
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};

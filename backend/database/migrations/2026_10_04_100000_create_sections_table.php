<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sections (A/B/C…) within a class (spec §13). Capacity is stored here and
     * enforced at enrollment (Module 13). Like classes, tables that reference
     * a section (enrollments, routines…) must use ON DELETE RESTRICT.
     */
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            // RESTRICT: a class that still has sections cannot be deleted.
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->string('name', 50);
            $table->unsignedInteger('capacity');
            $table->timestamps();

            // Its leading class_id column doubles as the class_id index.
            $table->unique(['class_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A student's place for one academic year: section (→ class) and roll
     * (spec §13). The class is derived from the section server-side, and a
     * composite FK makes the database refuse a class/section pair that
     * disagrees. Attendance, results and fees build on these rows.
     */
    public function up(): void
    {
        // Target for the composite FK below (id alone is already unique).
        Schema::table('sections', function (Blueprint $table) {
            $table->unique(['id', 'class_id']);
        });

        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->unsignedBigInteger('section_id');
            $table->unsignedSmallInteger('roll');
            $table->string('status', 20)->default('active'); // App\Enums\EnrollmentStatus
            $table->date('ended_on')->nullable();            // when it stopped being active
            $table->string('exit_reason', 255)->nullable();   // e.g. transfer destination
            $table->timestamps();

            $table->foreign(['section_id', 'class_id'])
                ->references(['id', 'class_id'])->on('sections')
                ->restrictOnDelete();

            // One enrollment per student per year (spec §54 composite index).
            $table->unique(['student_id', 'academic_year_id']);
            // Roll unique within a section for a year — across all statuses,
            // so a departed student's roll isn't handed out again that year.
            $table->unique(['section_id', 'academic_year_id', 'roll']);
            $table->index(['academic_year_id', 'class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_enrollments');
        Schema::table('sections', function (Blueprint $table) {
            $table->dropUnique(['id', 'class_id']);
        });
    }
};

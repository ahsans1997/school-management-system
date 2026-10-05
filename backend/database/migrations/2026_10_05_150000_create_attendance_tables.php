<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daily student attendance (spec §14): one sheet per section per day,
     * one row per student on it. Rows point at the student's enrollment, so
     * an enrollment with attendance can't be deleted (it changes status
     * instead), and carry student_id + date for the (student_id, date)
     * index spec §54 asks for.
     */
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->unsignedBigInteger('section_id');
            $table->date('date');
            $table->foreignId('taken_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Class must match the section (same composite FK as enrollments).
            $table->foreign(['section_id', 'class_id'])
                ->references(['id', 'class_id'])->on('sections')
                ->restrictOnDelete();

            // A section's sheet for a day exists once.
            $table->unique(['section_id', 'date']);
            $table->index(['academic_year_id', 'date']);
        });

        Schema::create('attendance_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete(); // denormalized for reports
            $table->date('date');                                                // denormalized for reports
            $table->string('status', 10);                                        // App\Enums\AttendanceStatus
            $table->string('remark', 255)->nullable();
            $table->timestamps();

            $table->unique(['attendance_id', 'student_id']);
            $table->index(['student_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_details');
        Schema::dropIfExists('attendances');
    }
};

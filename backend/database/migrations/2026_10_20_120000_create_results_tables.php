<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Results (spec §17, §56): one row per student per exam, calculated from
     * submitted marks and moved through draft → review → approved →
     * published as a class. Grades, grade points and subject names are
     * snapshotted (no FK to grades), so a published report card doesn't
     * change when a grade scale or subject is edited later.
     */
    public function up(): void
    {
        Schema::create('results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->unsignedBigInteger('section_id');
            $table->decimal('total_marks', 8, 2);
            $table->decimal('full_marks', 8, 2);
            $table->decimal('percentage', 5, 2);
            $table->decimal('gpa', 4, 2);
            $table->string('grade', 10);
            $table->boolean('passed');
            $table->unsignedSmallInteger('failed_subjects')->default(0);
            // Merit among students who passed every subject; NULL otherwise.
            $table->unsignedInteger('position')->nullable();        // within the section
            $table->unsignedInteger('class_position')->nullable();  // within the class
            $table->string('status', 10)->default('draft');         // App\Enums\ResultStatus
            $table->timestamp('calculated_at');
            $table->foreignId('calculated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign(['section_id', 'class_id'])
                ->references(['id', 'class_id'])->on('sections')
                ->restrictOnDelete();

            $table->unique(['exam_id', 'student_id']);
            $table->index(['exam_id', 'class_id', 'section_id']);
            $table->index('status');
        });

        Schema::create('result_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('result_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_subject_id')->constrained()->restrictOnDelete();
            $table->string('subject', 100);
            $table->string('subject_code', 20)->nullable();
            $table->decimal('full_marks', 6, 2);
            $table->decimal('pass_marks', 6, 2);
            $table->decimal('cq', 6, 2)->nullable();
            $table->decimal('mcq', 6, 2)->nullable();
            $table->decimal('practical', 6, 2)->nullable();
            $table->decimal('obtained', 6, 2)->nullable();          // NULL = absent
            $table->boolean('absent');
            $table->decimal('percentage', 5, 2);
            $table->string('grade', 10);
            $table->decimal('grade_point', 4, 2);
            $table->boolean('passed');

            $table->unique(['result_id', 'exam_subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_details');
        Schema::dropIfExists('results');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Exams (spec §16): an exam belongs to a year and uses a grade scale;
     * its papers (exam_subjects) say which class subjects are examined, with
     * full/pass marks, the optional CQ/MCQ/practical split, and the date.
     * Marks (Module 17) and results (Module 18) hang off these rows.
     */
    public function up(): void
    {
        // Target for the composite FK below (id alone is already unique).
        Schema::table('class_subjects', function (Blueprint $table) {
            $table->unique(['id', 'class_id']);
        });

        // A grade scale is a set of bands; each band starts at a percentage
        // cut-off and runs up to the next one, so bands can't overlap or
        // leave gaps (a 79.5 % mark would fall between "70–79" and "80–100").
        Schema::create('grade_scales', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_scale_id')->constrained()->cascadeOnDelete();
            $table->string('name', 10);                    // e.g. "A+"
            $table->decimal('grade_point', 4, 2);
            $table->decimal('min_percent', 5, 2);          // band = [min_percent, next band's min)
            $table->timestamps();

            $table->unique(['grade_scale_id', 'name']);
            $table->unique(['grade_scale_id', 'min_percent']);
        });

        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            // RESTRICT: a scale an exam uses can't be deleted.
            $table->foreignId('grade_scale_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('type', 20);                    // App\Enums\ExamType
            $table->date('start_date');
            $table->date('end_date')->nullable();          // blank = a one-day exam
            $table->timestamps();
            $table->softDeletes();

            // Unique across archived exams too: restoring one can't create a duplicate.
            $table->unique(['academic_year_id', 'name']);
            $table->index(['academic_year_id', 'type']);
        });

        Schema::create('exam_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            // The class's subject row; the composite FK makes the database
            // refuse a subject the class doesn't take.
            $table->unsignedBigInteger('class_subject_id');
            // NULL = every section of the class sits this paper.
            $table->unsignedBigInteger('section_id')->nullable();
            // 0 for "all sections", so the unique index below also catches
            // two class-wide rows (NULLs never collide in a unique index).
            $table->unsignedBigInteger('section_key')->storedAs('coalesce(section_id, 0)');
            $table->decimal('full_marks', 6, 2);
            $table->decimal('pass_marks', 6, 2);
            // Optional split of full_marks; when set, the parts add up to it.
            $table->decimal('cq_marks', 6, 2)->nullable();
            $table->decimal('mcq_marks', 6, 2)->nullable();
            $table->decimal('practical_marks', 6, 2)->nullable();
            $table->date('exam_date')->nullable();         // blank = not scheduled yet
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->timestamps();

            $table->foreign(['class_subject_id', 'class_id'])
                ->references(['id', 'class_id'])->on('class_subjects')
                ->restrictOnDelete();
            $table->foreign(['section_id', 'class_id'])
                ->references(['id', 'class_id'])->on('sections')
                ->restrictOnDelete();

            $table->unique(['exam_id', 'class_subject_id', 'section_key']);
            $table->index(['exam_id', 'class_id']);
            $table->index(['exam_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_subjects');
        Schema::dropIfExists('exams');
        Schema::dropIfExists('grades');
        Schema::dropIfExists('grade_scales');
        Schema::table('class_subjects', function (Blueprint $table) {
            $table->dropUnique(['id', 'class_id']);
        });
    }
};

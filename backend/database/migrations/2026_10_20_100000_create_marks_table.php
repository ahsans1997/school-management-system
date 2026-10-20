<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marks (spec §16–17): one row per student per paper. Entered per paper
     * and section as a draft, then submitted (locked) for results (Module
     * 18). Never exposed publicly. The total is always computed server-side.
     */
    public function up(): void
    {
        Schema::create('marks', function (Blueprint $table) {
            $table->id();
            // RESTRICT: a paper with marks can't be removed from its exam.
            $table->foreignId('exam_subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            // The enrollment the student sat the paper in (their section that
            // year); an enrollment with marks can't be deleted.
            $table->foreignId('student_enrollment_id')->constrained()->restrictOnDelete();
            // The paper's CQ/MCQ/practical parts, when it's split; a paper
            // without a split is marked as a single figure (total only).
            $table->decimal('cq', 6, 2)->nullable();
            $table->decimal('mcq', 6, 2)->nullable();
            $table->decimal('practical', 6, 2)->nullable();
            $table->decimal('total', 6, 2)->nullable();    // NULL when absent
            $table->boolean('absent')->default(false);
            $table->string('status', 10)->default('draft'); // App\Enums\MarkStatus
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['exam_subject_id', 'student_id']);
            $table->index('student_id');
            $table->index(['exam_subject_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marks');
    }
};

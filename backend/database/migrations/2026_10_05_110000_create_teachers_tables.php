<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Teachers (spec §11) and what they teach. Teaching is assigned per
     * SECTION (6A Maths and 6B Maths can have different teachers) and points
     * at the class's subject row, so a subject can't be unassigned from a
     * class while someone teaches it. These assignments are the source of
     * truth that routine, attendance and marks entry scope against (spec §52).
     */
    public function up(): void
    {
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id', 20)->unique();   // generated "T-0001" when blank
            $table->string('name', 100)->index();
            $table->string('designation', 100)->nullable();
            $table->string('department', 100)->nullable()->index();
            $table->string('qualification', 150)->nullable();
            $table->date('joining_date');
            $table->string('phone', 20);
            $table->string('email')->nullable();           // contact address, not the login
            $table->string('address', 500)->nullable();
            $table->decimal('salary', 15, 2)->nullable();   // money: DECIMAL, never float (spec §53)
            $table->string('photo_path')->nullable();       // private disk
            $table->softDeletes();                           // archive, never hard-delete
            $table->timestamps();
        });

        Schema::create('teaching_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->constrained()->restrictOnDelete();
            // The class's subject row (class_subjects) — must belong to the section's class.
            $table->foreignId('class_subject_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            // One teacher per subject per section.
            $table->unique(['section_id', 'class_subject_id']);
        });

        Schema::table('sections', function (Blueprint $table) {
            $table->foreignId('class_teacher_id')->nullable()->after('capacity')->constrained('teachers')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('class_teacher_id');
        });
        Schema::dropIfExists('teaching_assignments');
        Schema::dropIfExists('teachers');
    }
};

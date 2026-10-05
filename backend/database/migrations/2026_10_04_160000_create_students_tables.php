<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Students (spec §9) with their documents and guardians (spec §20).
     * Class, section, roll and session are NOT stored here: they belong to
     * the student's enrollment for a year (Module 13).
     */
    public function up(): void
    {
        // Counters for generated numbers (App\Support\Sequence).
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->unsignedBigInteger('next_value');
            $table->timestamps();
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            // Public-facing ID (ID card, public result lookup) — random, not sequential.
            $table->string('student_id', 20)->unique();
            // Admission register number — "YYYY-NNNN" from the admission year.
            $table->string('admission_no', 20)->unique();
            $table->date('admission_date');
            $table->string('name', 100)->index();
            $table->date('dob');
            $table->string('gender', 10);                  // App\Enums\Gender
            $table->string('blood_group', 3)->nullable();  // App\Enums\BloodGroup
            // Plain text: certificates need parents' names even when they aren't guardians.
            $table->string('father_name', 100)->nullable();
            $table->string('mother_name', 100)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('previous_school', 150)->nullable();
            $table->string('photo_path')->nullable();      // private disk
            $table->softDeletes();                          // archive, never hard-delete
            $table->timestamps();
        });

        Schema::create('student_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('title', 100);
            $table->string('path');                         // private disk
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('student_guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            // RESTRICT: a guardian linked to a student can't be deleted.
            $table->foreignId('guardian_id')->constrained()->restrictOnDelete();
            $table->string('relationship', 20);             // App\Enums\GuardianRelationship
            $table->boolean('is_primary')->default(false);
            // At most one primary per student, enforced by the database:
            // student_id when primary, NULL otherwise (NULLs never collide).
            $table->unsignedBigInteger('primary_marker')
                ->nullable()
                ->storedAs('case when is_primary = 1 then student_id end')
                ->unique();
            $table->timestamps();

            $table->unique(['student_id', 'guardian_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_guardians');
        Schema::dropIfExists('student_documents');
        Schema::dropIfExists('students');
        Schema::dropIfExists('number_sequences');
    }
};

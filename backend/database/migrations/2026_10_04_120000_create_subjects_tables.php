<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The subject catalogue and which classes teach each subject (spec §13).
     * The optional subject teacher on class_subjects arrives with the
     * teachers table in Module 12.
     */
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            // Optional board/school code; unique when present (NULLs don't collide).
            $table->string('code', 20)->nullable()->unique();
            $table->string('type', 20)->nullable(); // App\Enums\SubjectType
            $table->timestamps();
        });

        Schema::create('class_subjects', function (Blueprint $table) {
            $table->id();
            // RESTRICT both ways: an assigned subject — or a class with
            // subjects — can't be deleted until it's unassigned.
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            // Leading class_id doubles as the class_id index; subject_id gets its own.
            $table->unique(['class_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_subjects');
        Schema::dropIfExists('subjects');
    }
};

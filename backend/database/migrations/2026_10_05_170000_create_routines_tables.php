<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Weekly class routines (spec §15): one per section per year, made of
     * period slots. A teaching slot points at a teaching assignment (section
     * × subject × teacher, Module 12), so the routine can't disagree with who
     * teaches what; a non-teaching slot (break, assembly) has a label. Teacher
     * and room views are queries over the same slots.
     */
    public function up(): void
    {
        Schema::create('routines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->unsignedBigInteger('section_id');
            $table->timestamps();

            $table->foreign(['section_id', 'class_id'])
                ->references(['id', 'class_id'])->on('sections')
                ->restrictOnDelete();

            $table->unique(['section_id', 'academic_year_id']);
        });

        Schema::create('routine_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routine_id')->constrained()->cascadeOnDelete();
            $table->string('day', 3);                      // App\Enums\Weekday
            $table->unsignedTinyInteger('period_no');
            $table->time('start_time');
            $table->time('end_time');
            // RESTRICT: an assignment in use by a routine can't be removed silently.
            $table->foreignId('teaching_assignment_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('label', 50)->nullable();        // non-teaching slot, e.g. "Break"
            $table->string('room', 50)->nullable();
            $table->timestamps();

            $table->unique(['routine_id', 'day', 'period_no']);
            $table->index(['teaching_assignment_id', 'day']);
            $table->index(['day', 'room']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_slots');
        Schema::dropIfExists('routines');
    }
};

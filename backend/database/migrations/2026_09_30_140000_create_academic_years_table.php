<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Root of the academic hierarchy (spec §13). Tables that reference a year
     * must use ON DELETE RESTRICT — the delete action relies on it to refuse
     * deleting a year that is in use.
     */
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(false);

            // 1 for the current year, NULL for every other. A UNIQUE index
            // admits any number of NULLs but only one 1, so the database
            // itself guarantees at most one current year — no race or bug
            // can create a second. Never written by the app.
            $table->unsignedTinyInteger('current_marker')
                ->storedAs('case when is_current = 1 then 1 end')
                ->unique();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_years');
    }
};

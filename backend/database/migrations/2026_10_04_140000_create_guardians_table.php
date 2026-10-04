<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guardians/parents (spec §20). A guardian's optional login is the user
     * whose polymorphic person link (users.personable, Module 03) points
     * here — there is no guardians.user_id, so the link lives in one place.
     * Links to students (student_guardians) arrive with Students (Module 11).
     */
    public function up(): void
    {
        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->index();
            // Not unique: parents often share one number. Indexed for lookup at the front desk.
            $table->string('phone', 20)->index();
            $table->string('email')->nullable(); // contact address, not the login
            $table->string('occupation', 100)->nullable();
            $table->string('address', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardians');
    }
};

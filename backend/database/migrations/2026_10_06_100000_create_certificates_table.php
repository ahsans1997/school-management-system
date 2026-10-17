<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The register of certificates the school has issued (spec §9): each has
     * a serial number and issue date that stay fixed, so a reprint shows the
     * same certificate. Transfer certificates hang off the enrollment that
     * ended in a transfer; testimonials arrive with results (Module 18).
     */
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);                     // App\Enums\CertificateType
            $table->string('serial_no', 30)->unique();      // e.g. "TC-2026-0001"
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_enrollment_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('issued_on');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('conduct', 100)->nullable();     // "Conduct and character: …"
            $table->timestamps();

            // One certificate of a kind per enrollment.
            $table->unique(['type', 'student_enrollment_id']);
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};

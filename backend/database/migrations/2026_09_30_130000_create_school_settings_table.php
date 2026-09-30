<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Singleton institution configuration (single-school platform).
     * Secret groups (email/sms/payment) are stored encrypted at rest.
     */
    public function up(): void
    {
        Schema::create('school_settings', function (Blueprint $table) {
            $table->id();

            // Identity
            $table->string('name')->default('My School');
            $table->string('logo_path')->nullable();
            $table->string('favicon_path')->nullable();
            $table->string('address', 500)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('eiin', 50)->nullable();
            $table->string('school_code', 50)->nullable();
            $table->unsignedSmallInteger('established_year')->nullable();
            $table->string('principal_name')->nullable();
            $table->json('socials')->nullable();

            // System
            $table->string('currency', 3)->default('BDT');
            $table->string('timezone')->default('Asia/Dhaka');
            $table->string('date_format', 20)->default('d/m/Y');
            $table->string('language', 5)->default('en');

            // Secrets — encrypted JSON (see model casts)
            $table->text('email_settings')->nullable();
            $table->text('sms_settings')->nullable();
            $table->text('payment_settings')->nullable();

            // Academic pointer (FK added when academic_years exists — Module 06)
            $table->unsignedBigInteger('active_academic_year_id')->nullable()->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_settings');
    }
};

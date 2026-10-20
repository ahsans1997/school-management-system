<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The audit trail (spec §43), with the schema Module 38 plans. Written
     * now by the result-related workflows (marks submit/reopen and changes
     * after entry, result calculation and publishing — spec §55); Module 38
     * adds automatic capture for other models and the viewer. Append-only:
     * no updated_at, and the model refuses updates and deletes.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // null = system
            $table->string('action', 60);          // e.g. "results.published"
            $table->string('module', 40);          // e.g. "results"
            $table->nullableMorphs('auditable');   // the record acted on
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('user_id');
            $table->index(['module', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};

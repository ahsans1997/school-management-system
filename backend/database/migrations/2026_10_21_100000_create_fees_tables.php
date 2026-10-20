<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fees (spec §18): a fee-type catalogue, per class + year amounts, and
     * monthly invoices built from them by a queued generation run. Money is
     * DECIMAL(15,2), never float (spec §53). Invoices are never deleted: a
     * mistaken one is voided (only while nothing is paid), which frees its
     * period for a new invoice. `paid` / `due` change only through Payments
     * (Module 20), inside a transaction.
     */
    public function up(): void
    {
        Schema::create('fee_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('code', 20)->nullable()->unique();
            $table->string('category', 20);              // App\Enums\FeeCategory
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('fee_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('frequency', 10);             // App\Enums\FeeFrequency
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['fee_type_id', 'class_id', 'academic_year_id']);
            $table->index(['academic_year_id', 'class_id']);
        });

        // One "generate invoices" request: queued, then worked through by
        // GenerateInvoicesJob; the screen polls it for progress.
        Schema::create('fee_invoice_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->restrictOnDelete();
            $table->char('period', 7);                   // YYYY-MM
            $table->date('due_date');
            $table->json('fee_type_ids')->nullable();    // chosen fee types; null = the defaults
            $table->string('status', 10)->default('queued'); // queued, running, completed, failed
            $table->unsignedInteger('created')->default(0);
            $table->unsignedInteger('skipped')->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->string('error', 500)->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('fee_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', 20)->unique();  // INV-YYYY-NNNNN
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('fee_invoice_run_id')->nullable()->constrained()->nullOnDelete();
            $table->char('period', 7);                   // YYYY-MM
            $table->date('due_date');
            $table->decimal('total', 15, 2);
            $table->decimal('paid', 15, 2)->default(0);
            $table->decimal('due', 15, 2);
            $table->string('status', 10)->default('unpaid'); // App\Enums\InvoiceStatus
            // The period while the invoice counts; NULL once void, so a voided
            // month can be invoiced again (NULLs never collide in a unique index).
            $table->char('live_period', 7)->nullable()->storedAs("case when status = 'void' then null else period end");
            // DATETIME, not TIMESTAMP: MariaDB would give the first NOT NULL
            // timestamp column ON UPDATE CURRENT_TIMESTAMP and rewrite it.
            $table->dateTime('issued_at');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 255)->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'academic_year_id', 'live_period']);
            $table->index('status');
            $table->index(['academic_year_id', 'period']);
            $table->index(['class_id', 'status']);
        });

        Schema::create('fee_invoice_items', function (Blueprint $table) {
            $table->id();
            // RESTRICT: invoices are never deleted, so neither are their lines.
            $table->foreignId('fee_invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('fee_type_id')->constrained()->restrictOnDelete();
            // RESTRICT: a structure that was billed is deactivated, not deleted.
            $table->foreignId('fee_structure_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('description', 150);
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->index('fee_type_id');
        });

        // The money rules in the database too (Payments, Module 20, moves
        // paid/due): positive totals, 0 ≤ paid ≤ total, due = total − paid.
        // SQLite (tests) can't add a CHECK to an existing table; the actions
        // enforce the same rules there.
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE fee_invoices ADD CONSTRAINT fee_invoices_amounts_check
                CHECK (total > 0 AND paid >= 0 AND paid <= total AND due = total - paid)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_invoice_items');
        Schema::dropIfExists('fee_invoices');
        Schema::dropIfExists('fee_invoice_runs');
        Schema::dropIfExists('fee_structures');
        Schema::dropIfExists('fee_types');
    }
};

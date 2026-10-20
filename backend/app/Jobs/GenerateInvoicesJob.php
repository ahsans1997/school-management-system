<?php

namespace App\Jobs;

use App\Actions\Fee\GenerateInvoices;
use App\Models\FeeInvoiceRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Generate a class's (or section's) invoices for a month in the background
 * (spec §61). Runs once: a failure is recorded on the run, and re-queuing
 * is safe because students already invoiced for the month are skipped.
 */
class GenerateInvoicesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** Below the database queue's retry_after (90 s), so no second worker picks it up mid-run. */
    public int $timeout = 80;

    public function __construct(public readonly int $runId) {}

    public function handle(GenerateInvoices $generate): void
    {
        $run = FeeInvoiceRun::find($this->runId);
        if ($run === null || $run->status !== 'queued') {
            return;
        }

        $generate->process($run);
    }

    /** The worker gave up (timeout, crash): don't leave the run "running". */
    public function failed(?Throwable $e): void
    {
        $run = FeeInvoiceRun::find($this->runId);
        if ($run !== null && in_array($run->status, ['queued', 'running'], true)) {
            app(GenerateInvoices::class)->failed($run);
        }
    }
}

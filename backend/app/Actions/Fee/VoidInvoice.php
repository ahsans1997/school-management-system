<?php

namespace App\Actions\Fee;

use App\Enums\InvoiceStatus;
use App\Models\FeeInvoice;
use App\Models\User;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Cancel a mistaken invoice. Only while nothing is paid against it (money
 * received is refunded through Payments, Module 20). A void invoice stays
 * on record — invoices are never deleted — and frees its month for a new
 * invoice. Audited (spec §43).
 */
class VoidInvoice
{
    public function execute(User $user, FeeInvoice $invoice, string $reason): FeeInvoice
    {
        return DB::transaction(function () use ($user, $invoice, $reason) {
            $invoice = FeeInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            abort_if($invoice->status === InvoiceStatus::Void, 409, 'This invoice is already void.');
            abort_if(Money::cents($invoice->paid) > 0, 409, 'Money has been paid against this invoice. Refund the payments before voiding it.');

            $before = $invoice->status->value;
            $invoice->forceFill([
                'status' => InvoiceStatus::Void,
                'voided_at' => now(),
                'voided_by' => $user->id,
                'void_reason' => trim($reason),
            ])->save();

            Audit::record($user, 'fees.invoice_voided', 'fees', $invoice,
                old: ['status' => $before, 'total' => Money::format($invoice->total)],
                new: ['status' => InvoiceStatus::Void->value, 'reason' => $invoice->void_reason],
            );

            return $invoice;
        });
    }
}

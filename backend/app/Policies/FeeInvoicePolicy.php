<?php

namespace App\Policies;

use App\Enums\DashboardAudience;
use App\Models\FeeInvoice;
use App\Models\User;

/**
 * The second layer for invoices (spec §52). The staff fee screens show
 * every family's invoices, so they need the fee.* permission AND a staff
 * audience: a Parent or Student login never gets them, even if a role edit
 * hands it fee.view. Families see their own invoices through /fees/me.
 */
class FeeInvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->staff($user) && $user->can('fee.view');
    }

    public function view(User $user, FeeInvoice $invoice): bool
    {
        return $this->staff($user) && $user->can('fee.view');
    }

    /** Generate invoices. */
    public function create(User $user): bool
    {
        return $this->staff($user) && $user->can('fee.create');
    }

    public function cancel(User $user, FeeInvoice $invoice): bool
    {
        return $this->staff($user) && $user->can('fee.create');
    }

    /** The due report. */
    public function report(User $user): bool
    {
        return $this->staff($user) && $user->can('fee.report');
    }

    private function staff(User $user): bool
    {
        return ! in_array(DashboardAudience::forUser($user), [DashboardAudience::Parent, DashboardAudience::Student], true);
    }
}

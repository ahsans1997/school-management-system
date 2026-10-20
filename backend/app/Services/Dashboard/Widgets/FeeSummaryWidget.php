<?php

namespace App\Services\Dashboard\Widgets;

use App\Enums\DashboardAudience;
use App\Models\SchoolSetting;
use App\Models\User;
use App\Services\Dashboard\Widget;
use App\Services\Fee\FeeService;

/** This year's fees for management: billed, collected, outstanding, overdue. */
final class FeeSummaryWidget extends Widget
{
    public function key(): string
    {
        return 'pending_fees';
    }

    public function title(): string
    {
        return 'Fees';
    }

    public function audiences(): array
    {
        return [DashboardAudience::Admin, DashboardAudience::Staff];
    }

    public function permission(): string
    {
        return 'fee.view';
    }

    protected function data(User $user): array
    {
        return [...app(FeeService::class)->summary(), 'currency' => SchoolSetting::current()->currency];
    }
}

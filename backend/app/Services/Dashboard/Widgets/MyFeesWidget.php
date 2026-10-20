<?php

namespace App\Services\Dashboard\Widgets;

use App\Enums\DashboardAudience;
use App\Models\FeeInvoice;
use App\Models\SchoolSetting;
use App\Models\User;
use App\Services\Dashboard\Widget;
use App\Services\Fee\FeeService;
use App\Support\Money;

/**
 * What the caller owes: the student's own invoices (`my_fees`) or each of
 * a parent's children's (`child_fees`) — the total due and the unpaid
 * months, oldest first.
 */
final class MyFeesWidget extends Widget
{
    private const LIMIT = 4;

    public function __construct(private readonly DashboardAudience $audience) {}

    public function key(): string
    {
        return $this->audience === DashboardAudience::Parent ? 'child_fees' : 'my_fees';
    }

    public function title(): string
    {
        return $this->audience === DashboardAudience::Parent ? 'Fees & dues' : 'My fees';
    }

    public function audiences(): array
    {
        return [$this->audience];
    }

    protected function data(User $user): array
    {
        $mine = app(FeeService::class)->mine($user);

        return [
            'linked' => $mine['students'] !== [],
            'currency' => SchoolSetting::current()->currency,
            'students' => array_map(fn (array $s) => [
                'student' => ['id' => $s['student']->id, 'name' => $s['student']->name],
                'due' => $s['due'],
                'unpaid' => $s['invoices']
                    ->filter(fn (FeeInvoice $i) => Money::cents($i->due) > 0)
                    ->sortBy('period')
                    ->take(self::LIMIT)
                    ->map(fn (FeeInvoice $i) => [
                        'id' => $i->id,
                        'period' => $i->period,
                        'due' => Money::format($i->due),
                        'due_date' => $i->due_date->toDateString(),
                    ])->values()->all(),
            ], $mine['students']),
        ];
    }
}

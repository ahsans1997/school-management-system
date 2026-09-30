<?php

namespace App\Services\Dashboard\Widgets;

use App\Enums\DashboardAudience;
use App\Models\SchoolSetting;
use App\Models\User;
use App\Services\Dashboard\Widget;
use Illuminate\Support\Facades\Storage;

/** Public-facing school identity only (no contact secrets, no system config). */
final class SchoolWidget extends Widget
{
    public function key(): string
    {
        return 'school';
    }

    public function title(): string
    {
        return 'School';
    }

    public function audiences(): array
    {
        return DashboardAudience::cases();
    }

    protected function data(User $user): array
    {
        $settings = SchoolSetting::current(); // cached singleton

        return [
            'name' => $settings->name,
            'logo_url' => $settings->logo_path
                ? Storage::disk('public')->url($settings->logo_path)
                : null,
            'eiin' => $settings->eiin,
            'established_year' => $settings->established_year,
            'principal_name' => $settings->principal_name,
            'address' => $settings->address,
        ];
    }
}

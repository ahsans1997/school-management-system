<?php

namespace App\Support;

use App\Models\SchoolSetting;
use Illuminate\Support\Facades\Storage;

/** The school's details printed on ID cards and certificates (School Settings, Module 04). */
final class SchoolLetterhead
{
    /**
     * @return array{name: string, logo_url: string|null, address: string|null, phone: string|null, email: string|null, eiin: string|null, principal_name: string|null}
     */
    public static function toArray(): array
    {
        $settings = SchoolSetting::current(); // cached singleton

        return [
            'name' => $settings->name,
            'logo_url' => $settings->logo_path ? Storage::disk('public')->url($settings->logo_path) : null,
            'address' => $settings->address,
            'phone' => $settings->phone,
            'email' => $settings->email,
            'eiin' => $settings->eiin,
            'principal_name' => $settings->principal_name,
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\SchoolSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin SchoolSetting */
class SchoolSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $disk = Storage::disk('public');

        return [
            'name' => $this->name,
            'logo_url' => $this->logo_path ? $disk->url($this->logo_path) : null,
            'favicon_url' => $this->favicon_path ? $disk->url($this->favicon_path) : null,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'website' => $this->website,
            'eiin' => $this->eiin,
            'school_code' => $this->school_code,
            'established_year' => $this->established_year,
            'principal_name' => $this->principal_name,
            'socials' => (object) ($this->socials ?? []),

            'currency' => $this->currency,
            'timezone' => $this->timezone,
            'date_format' => $this->date_format,
            'language' => $this->language,

            'email_settings' => $this->masked('email_settings'),
            'sms_settings' => $this->masked('sms_settings'),
            'payment_settings' => $this->masked('payment_settings'),

            'active_academic_year_id' => $this->active_academic_year_id,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Return a secret group with every secret value replaced by a
     * `<key>_set` boolean — secrets never leave the server.
     *
     * @return array<string, mixed>
     */
    private function masked(string $group): array
    {
        $secretKeys = SchoolSetting::SECRET_KEYS[$group];
        $values = $this->{$group} ?? [];
        $out = [];

        foreach ($values as $key => $value) {
            if (! in_array($key, $secretKeys, true)) {
                $out[$key] = $value;
            }
        }

        foreach ($secretKeys as $key) {
            $out["{$key}_set"] = filled($values[$key] ?? null);
        }

        return $out;
    }
}

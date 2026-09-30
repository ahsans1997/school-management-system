<?php

namespace App\Actions\Settings;

use App\Models\SchoolSetting;

class UpdateSchoolSettingsAction
{
    /**
     * Apply a partial update to the singleton and bust the cache.
     *
     * @param  array<string, mixed>  $data  Validated fields (only present keys applied).
     */
    public function execute(array $data): SchoolSetting
    {
        $settings = SchoolSetting::query()->firstOrCreate([]);

        // Merge secret groups over stored values: omitted, null or blank keys
        // keep what's stored, so clients never need to resend a secret.
        foreach (array_keys(SchoolSetting::SECRET_KEYS) as $group) {
            if (array_key_exists($group, $data)) {
                $provided = array_filter(
                    $data[$group] ?? [],
                    fn ($value) => $value !== null && $value !== '',
                );
                $data[$group] = array_merge($settings->{$group} ?? [], $provided);
            }
        }

        $settings->fill($data)->save();
        SchoolSetting::forgetCache();

        return $settings;
    }
}

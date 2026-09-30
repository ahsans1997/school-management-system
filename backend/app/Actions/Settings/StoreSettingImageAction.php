<?php

namespace App\Actions\Settings;

use App\Models\SchoolSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class StoreSettingImageAction
{
    /**
     * Store an uploaded logo/favicon on the public disk (random filename —
     * never the client's), replace the previous file, and bust the cache.
     *
     * @param  'logo_path'|'favicon_path'  $attribute
     */
    public function execute(UploadedFile $file, string $attribute): SchoolSetting
    {
        $settings = SchoolSetting::query()->firstOrCreate([]);
        $disk = Storage::disk('public');
        $previous = $settings->{$attribute};

        $path = $file->store('settings', 'public');
        $settings->update([$attribute => $path]);

        if ($previous && $previous !== $path && $disk->exists($previous)) {
            $disk->delete($previous);
        }

        SchoolSetting::forgetCache();

        return $settings;
    }
}

<?php

namespace App\Http\Controllers;

use App\Actions\Settings\StoreSettingImageAction;
use App\Actions\Settings\UpdateSchoolSettingsAction;
use App\Http\Requests\Settings\UpdateSchoolSettingsRequest;
use App\Http\Requests\Settings\UploadFaviconRequest;
use App\Http\Requests\Settings\UploadLogoRequest;
use App\Http\Resources\SchoolSettingsResource;
use App\Models\SchoolSetting;

class SchoolSettingsController extends Controller
{
    public function show(): SchoolSettingsResource
    {
        return new SchoolSettingsResource(SchoolSetting::current());
    }

    public function update(UpdateSchoolSettingsRequest $request, UpdateSchoolSettingsAction $action): SchoolSettingsResource
    {
        $action->execute($request->validated());

        return new SchoolSettingsResource(SchoolSetting::current());
    }

    public function uploadLogo(UploadLogoRequest $request, StoreSettingImageAction $action): SchoolSettingsResource
    {
        $action->execute($request->file('file'), 'logo_path');

        return new SchoolSettingsResource(SchoolSetting::current());
    }

    public function uploadFavicon(UploadFaviconRequest $request, StoreSettingImageAction $action): SchoolSettingsResource
    {
        $action->execute($request->file('file'), 'favicon_path');

        return new SchoolSettingsResource(SchoolSetting::current());
    }
}

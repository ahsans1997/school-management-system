<?php

namespace App\Actions\Files;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class RemovePhotoAction
{
    public function execute(Model $owner): Model
    {
        if ($owner->photo_path !== null) {
            Storage::disk('local')->delete($owner->photo_path);
            $owner->forceFill(['photo_path' => null])->save();
        }

        return $owner;
    }
}

<?php

namespace App\Actions\Files;

use App\Models\Contracts\HasPrivateFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class RemovePhotoAction
{
    /**
     * @template TOwner of Model&HasPrivateFiles
     *
     * @param  TOwner  $owner
     * @return TOwner
     */
    public function execute(Model&HasPrivateFiles $owner): Model&HasPrivateFiles
    {
        $path = $owner->getAttribute('photo_path');
        if ($path !== null) {
            Storage::disk('local')->delete($path);
            $owner->forceFill(['photo_path' => null])->save();
        }

        return $owner;
    }
}

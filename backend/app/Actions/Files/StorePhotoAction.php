<?php

namespace App\Actions\Files;

use App\Models\Contracts\HasPrivateFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class StorePhotoAction
{
    /**
     * Private disk, random filename; the previous photo is removed.
     *
     * @template TOwner of Model&HasPrivateFiles
     *
     * @param  TOwner  $owner
     * @return TOwner
     */
    public function execute(Model&HasPrivateFiles $owner, UploadedFile $file): Model&HasPrivateFiles
    {
        $previous = $owner->getAttribute('photo_path');
        $path = $file->store($owner->fileFolder(), 'local');

        $owner->forceFill(['photo_path' => $path])->save();

        if ($previous !== null) {
            Storage::disk('local')->delete($previous);
        }

        return $owner;
    }
}

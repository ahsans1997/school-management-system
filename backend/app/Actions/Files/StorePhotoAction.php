<?php

namespace App\Actions\Files;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class StorePhotoAction
{
    /**
     * Private disk, random filename; the previous photo is removed. The
     * owner has a `photo_path` column and HasDocuments' fileFolder().
     */
    public function execute(Model $owner, UploadedFile $file): Model
    {
        $previous = $owner->photo_path;
        $path = $file->store($owner->fileFolder(), 'local');

        $owner->forceFill(['photo_path' => $path])->save();

        if ($previous !== null) {
            Storage::disk('local')->delete($previous);
        }

        return $owner;
    }
}

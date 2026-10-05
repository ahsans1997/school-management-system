<?php

namespace App\Models\Contracts;

use App\Models\Document;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A record that keeps private files: a photo (`photo_path` column) and
 * documents (HasDocuments). Lets the shared App\Actions\Files actions take
 * any such record — Student, Teacher — with a checked type.
 */
interface HasPrivateFiles
{
    /**
     * @return MorphMany<Document, covariant Model>
     */
    public function documents(): MorphMany;

    /** Folder on the private disk for this record's files. */
    public function fileFolder(): string;
}

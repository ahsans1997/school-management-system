<?php

namespace App\Models\Concerns;

use App\Models\Document;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** A record that keeps files in the shared documents table. */
trait HasDocuments
{
    /**
     * Newest first.
     *
     * @return MorphMany<Document, $this>
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable')->latest('id');
    }

    /** Folder on the private disk, e.g. "students/12" — photos and documents live under it. */
    public function fileFolder(): string
    {
        return $this->getMorphClass().'s/'.$this->getKey();
    }
}

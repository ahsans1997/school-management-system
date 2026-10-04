<?php

namespace App\Http\Requests\Section;

use App\Models\Section;

class StoreSectionRequest extends SectionRequest
{
    protected function classId(): int
    {
        return $this->route('school_class')->id;
    }

    protected function ignoredSection(): ?Section
    {
        return null;
    }
}

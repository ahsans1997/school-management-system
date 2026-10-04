<?php

namespace App\Http\Requests\Section;

use App\Models\Section;

class UpdateSectionRequest extends SectionRequest
{
    protected function classId(): int
    {
        return $this->ignoredSection()->class_id;
    }

    protected function ignoredSection(): Section
    {
        return $this->route('section');
    }
}

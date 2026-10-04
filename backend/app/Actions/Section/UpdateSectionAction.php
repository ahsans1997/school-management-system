<?php

namespace App\Actions\Section;

use App\Models\Section;

class UpdateSectionAction
{
    /**
     * Name and capacity only — a section never moves to another class.
     *
     * @param  array{name: string, capacity: int}  $data
     */
    public function execute(Section $section, array $data): Section
    {
        $section->update($data);

        return $section;
    }
}

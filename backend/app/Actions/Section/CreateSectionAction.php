<?php

namespace App\Actions\Section;

use App\Models\SchoolClass;
use App\Models\Section;

class CreateSectionAction
{
    /**
     * @param  array{name: string, capacity: int}  $data
     */
    public function execute(SchoolClass $class, array $data): Section
    {
        return $class->sections()->create($data);
    }
}

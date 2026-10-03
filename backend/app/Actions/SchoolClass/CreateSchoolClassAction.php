<?php

namespace App\Actions\SchoolClass;

use App\Models\SchoolClass;

class CreateSchoolClassAction
{
    /**
     * @param  array{name: string, numeric_level: int, shift?: ?string, medium?: ?string}  $data
     */
    public function execute(array $data): SchoolClass
    {
        return SchoolClass::create($data);
    }
}

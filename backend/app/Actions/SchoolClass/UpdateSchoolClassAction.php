<?php

namespace App\Actions\SchoolClass;

use App\Models\SchoolClass;

class UpdateSchoolClassAction
{
    /**
     * @param  array{name: string, numeric_level: int, shift?: ?string, medium?: ?string}  $data
     */
    public function execute(SchoolClass $class, array $data): SchoolClass
    {
        $class->update($data);

        return $class;
    }
}

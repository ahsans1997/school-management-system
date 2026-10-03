<?php

namespace App\Actions\SchoolClass;

use App\Models\SchoolClass;
use App\Support\GuardedDelete;

class DeleteSchoolClassAction
{
    /** "In use" is enforced by FK RESTRICT on referencing tables. */
    public function execute(SchoolClass $class): void
    {
        GuardedDelete::delete(
            $class,
            'This class is in use (for example by sections or enrollments) and cannot be deleted.',
        );
    }
}

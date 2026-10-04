<?php

namespace App\Actions\Section;

use App\Models\Section;
use App\Support\GuardedDelete;

class DeleteSectionAction
{
    /** "In use" is enforced by FK RESTRICT on referencing tables. */
    public function execute(Section $section): void
    {
        GuardedDelete::delete(
            $section,
            'This section is in use (for example by enrollments) and cannot be deleted.',
        );
    }
}

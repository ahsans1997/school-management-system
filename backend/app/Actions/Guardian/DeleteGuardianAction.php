<?php

namespace App\Actions\Guardian;

use App\Models\Guardian;
use App\Support\GuardedDelete;

class DeleteGuardianAction
{
    public function execute(Guardian $guardian): void
    {
        // Deleting the person would orphan its login (the morph link has no FK).
        abort_if(
            $guardian->account()->exists(),
            409,
            'This guardian has a login account. Delete the account under Users first.',
        );

        // Linked students (student_guardians, Module 11) block via FK RESTRICT.
        GuardedDelete::delete($guardian, 'This guardian is linked to students and cannot be deleted.');
    }
}

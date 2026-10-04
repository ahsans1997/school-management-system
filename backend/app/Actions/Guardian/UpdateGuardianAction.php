<?php

namespace App\Actions\Guardian;

use App\Models\Guardian;
use Illuminate\Support\Facades\DB;

class UpdateGuardianAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Guardian $guardian, array $data): Guardian
    {
        return DB::transaction(function () use ($guardian, $data) {
            $guardian->update($data);

            // The login's display name follows the guardian's.
            $guardian->account?->update(['name' => $guardian->name]);

            return $guardian;
        });
    }
}

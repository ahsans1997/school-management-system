<?php

namespace App\Actions\Guardian;

use App\Actions\Person\CreatePersonAccountAction;
use App\Models\Guardian;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreateGuardianAction
{
    public function __construct(private readonly CreatePersonAccountAction $createAccount) {}

    /**
     * The guardian and, optionally, its login — together or not at all.
     *
     * @param  array<string, mixed>  $data  profile fields plus optional `account` {email, password}
     */
    public function execute(array $data): Guardian
    {
        return DB::transaction(function () use ($data) {
            $guardian = Guardian::create(Arr::except($data, 'account'));

            if (! empty($data['account'])) {
                $this->createAccount->execute($guardian, 'Parent', $data['account']);
            } else {
                $guardian->setRelation('account', null);
            }

            return $guardian;
        });
    }
}

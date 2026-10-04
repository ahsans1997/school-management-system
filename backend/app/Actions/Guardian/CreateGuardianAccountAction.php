<?php

namespace App\Actions\Guardian;

use App\Actions\User\CreateUserAction;
use App\Models\Guardian;
use App\Models\User;

class CreateGuardianAccountAction
{
    public function __construct(private readonly CreateUserAction $createUser) {}

    /**
     * A Parent-portal login linked to the guardian. The role is fixed here —
     * never taken from the client.
     *
     * @param  array{email: string, password: string}  $credentials
     */
    public function execute(Guardian $guardian, array $credentials): User
    {
        abort_if($guardian->account()->exists(), 409, 'This guardian already has a login account.');

        $user = $this->createUser->execute([
            'name' => $guardian->name,
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'roles' => ['Parent'],
            'personable_type' => $guardian->getMorphClass(),
            'personable_id' => $guardian->getKey(),
        ]);

        $guardian->setRelation('account', $user);

        return $user;
    }
}

<?php

namespace App\Actions\Person;

use App\Actions\User\CreateUserAction;
use App\Models\Contracts\HasAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * A login for a person record (guardian, teacher…), linked through
 * users.personable. The role is chosen by the calling code — never by the
 * client — and a person has at most one login.
 */
class CreatePersonAccountAction
{
    public function __construct(private readonly CreateUserAction $createUser) {}

    /**
     * @param  array{email: string, password: string}  $credentials
     */
    public function execute(Model&HasAccount $person, string $role, array $credentials): User
    {
        // e.g. "This guardian already has a login account."
        abort_if($person->account()->exists(), 409, "This {$person->getMorphClass()} already has a login account.");

        $user = $this->createUser->execute([
            'name' => $person->getAttribute('name'),
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'roles' => [$role],
            'personable_type' => $person->getMorphClass(),
            'personable_id' => $person->getKey(),
        ]);

        $person->setRelation('account', $user);

        return $user;
    }
}

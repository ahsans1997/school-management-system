<?php

namespace App\Models\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * A person record (with a `name`) that can have a portal login: the `users`
 * row whose polymorphic `personable` points at it (Guardian, Teacher). Used
 * by App\Actions\Person\CreatePersonAccountAction.
 */
interface HasAccount
{
    public function account(): MorphOne;
}

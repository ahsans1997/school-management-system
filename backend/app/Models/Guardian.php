<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * A parent/guardian (spec §20). Stored under the morph alias "guardian"
 * (AppServiceProvider), matching User::PERSON_TYPES.
 */
class Guardian extends Model
{
    protected $guarded = ['id'];

    /**
     * The guardian's Parent-portal login, if one was created.
     *
     * @return MorphOne<User, $this>
     */
    public function account(): MorphOne
    {
        return $this->morphOne(User::class, 'personable');
    }
}

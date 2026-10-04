<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Validates users.personable_id against personable_type: the person record
 * must exist and must not already have a login. Types whose module isn't
 * built yet (no morph-map entry) are stored as given, as in Module 03.
 */
class PersonLink implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    /** @param  ?User  $ignore  the user being updated, which may keep its own link */
    public function __construct(private readonly ?User $ignore = null) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $type = $this->data['personable_type'] ?? null;
        $model = is_string($type) ? Relation::getMorphedModel($type) : null;

        if ($model === null) {
            return;
        }

        if (! $model::query()->whereKey($value)->exists()) {
            $fail('That person record does not exist.');

            return;
        }

        $taken = User::query()
            ->where('personable_type', $type)
            ->where('personable_id', $value)
            ->when($this->ignore, fn ($query, User $ignore) => $query->whereKeyNot($ignore->getKey()))
            ->exists();

        if ($taken) {
            $fail('That person already has a login account.');
        }
    }
}

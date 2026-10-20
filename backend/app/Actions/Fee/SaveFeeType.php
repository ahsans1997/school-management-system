<?php

namespace App\Actions\Fee;

use App\Models\FeeType;
use App\Models\User;
use App\Support\Audit;
use App\Support\GuardedDelete;
use BackedEnum;
use Illuminate\Support\Facades\DB;

/**
 * Create, update or delete a fee type (deleting is refused once it's used).
 * Audited: turning a type off stops it being billed for every class.
 */
class SaveFeeType
{
    /**
     * @param  array<string, mixed>  $data  validated
     */
    public function execute(User $user, ?FeeType $type, array $data): FeeType
    {
        return DB::transaction(function () use ($user, $type, $data) {
            $before = $type?->only(['name', 'code', 'category', 'is_active']);
            $values = [
                'name' => trim($data['name']),
                'code' => isset($data['code']) ? strtoupper(trim($data['code'])) : null,
                'category' => $data['category'],
                'is_active' => $data['is_active'] ?? $type->is_active ?? true,
            ];

            if ($type === null) {
                $type = FeeType::create($values);
            } else {
                $type->update($values);
            }

            $after = $type->only(['name', 'code', 'category', 'is_active']);
            if ($before != $after) {
                Audit::record($user, $before === null ? 'fees.type_created' : 'fees.type_updated', 'fees', $type,
                    old: $before === null ? null : $this->plain($before), new: $this->plain($after));
            }

            return $type;
        });
    }

    public function delete(User $user, FeeType $type): void
    {
        DB::transaction(function () use ($user, $type) {
            $before = $this->plain($type->only(['id', 'name', 'code', 'category', 'is_active']));
            GuardedDelete::delete($type, "{$type->name} is used by fee structures or invoices, so it can't be deleted. Turn it off instead.");
            Audit::record($user, 'fees.type_deleted', 'fees', null, old: $before);
        });
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function plain(array $values): array
    {
        return array_map(fn ($v) => $v instanceof BackedEnum ? $v->value : $v, $values);
    }
}

<?php

namespace App\Actions\Fee;

use App\Models\FeeStructure;
use App\Models\User;
use App\Support\Audit;
use App\Support\GuardedDelete;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Set what a class pays for a fee type in a year. Its fee type, class and
 * year are fixed once created; amount, frequency and active can change
 * (issued invoices keep the amount they were billed). Audited (spec §43).
 */
class SaveFeeStructure
{
    /**
     * @param  array<string, mixed>  $data  validated
     */
    public function execute(User $user, ?FeeStructure $structure, array $data): FeeStructure
    {
        return DB::transaction(function () use ($user, $structure, $data) {
            $before = $structure ? $this->snapshot($structure) : null;
            $values = [
                'amount' => Money::format($data['amount']),
                'frequency' => $data['frequency'],
                'is_active' => $data['is_active'] ?? $structure->is_active ?? true,
            ];

            if ($structure === null) {
                $structure = FeeStructure::create([
                    ...$values,
                    'fee_type_id' => $data['fee_type_id'],
                    'class_id' => $data['class_id'],
                    'academic_year_id' => $data['academic_year_id'],
                ]);
            } else {
                $structure->update($values);
            }

            if ($before === null || $before !== $this->snapshot($structure)) {
                Audit::record($user, $before === null ? 'fees.structure_created' : 'fees.structure_updated', 'fees', $structure,
                    old: $before, new: $this->snapshot($structure));
            }

            return $structure;
        });
    }

    public function delete(User $user, FeeStructure $structure): void
    {
        DB::transaction(function () use ($user, $structure) {
            $before = $this->snapshot($structure);
            GuardedDelete::delete($structure, 'This fee has been billed on invoices, so it can\'t be deleted. Turn it off instead.');
            Audit::record($user, 'fees.structure_deleted', 'fees', null, old: $before);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(FeeStructure $structure): array
    {
        return [
            'id' => $structure->id,
            'fee_type_id' => $structure->fee_type_id,
            'class_id' => $structure->class_id,
            'academic_year_id' => $structure->academic_year_id,
            'amount' => Money::format($structure->amount),
            'frequency' => $structure->frequency->value,
            'is_active' => $structure->is_active,
        ];
    }
}

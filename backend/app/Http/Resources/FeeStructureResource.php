<?php

namespace App\Http\Resources;

use App\Models\FeeStructure;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Money goes out as a "1500.00" string, never a float.
 *
 * @mixin FeeStructure
 */
class FeeStructureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fee_type' => $this->whenLoaded('feeType', fn () => [
                'id' => $this->feeType->id,
                'name' => $this->feeType->name,
                'category' => $this->feeType->category->value,
                'is_active' => $this->feeType->is_active,
            ]),
            'class' => $this->whenLoaded('schoolClass', fn () => ['id' => $this->schoolClass->id, 'name' => $this->schoolClass->name]),
            'academic_year' => $this->whenLoaded('academicYear', fn () => ['id' => $this->academicYear->id, 'name' => $this->academicYear->name]),
            'amount' => Money::format($this->amount),
            'frequency' => $this->frequency->value,
            'is_active' => $this->is_active,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

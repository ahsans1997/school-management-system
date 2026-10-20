<?php

namespace App\Http\Resources;

use App\Models\FeeInvoice;
use App\Models\FeeInvoiceItem;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An invoice; lines when loaded. Money goes out as "1500.00" strings.
 *
 * @mixin FeeInvoice
 */
class FeeInvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_no' => $this->invoice_no,
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student->id,
                'name' => $this->student->name,
                'student_id' => $this->student->student_id,
            ]),
            'class' => $this->whenLoaded('schoolClass', fn () => ['id' => $this->schoolClass->id, 'name' => $this->schoolClass->name]),
            'section' => $this->whenLoaded('enrollment', fn () => $this->enrollment->relationLoaded('section')
                ? ['id' => $this->enrollment->section->id, 'name' => $this->enrollment->section->name]
                : null),
            'roll' => $this->whenLoaded('enrollment', fn () => $this->enrollment->roll),
            'academic_year' => $this->whenLoaded('academicYear', fn () => ['id' => $this->academicYear->id, 'name' => $this->academicYear->name]),
            'period' => $this->period,
            'due_date' => $this->due_date->toDateString(),
            'total' => Money::format($this->total),
            'paid' => Money::format($this->paid),
            'due' => Money::format($this->due),
            'status' => $this->status->value,
            'issued_at' => $this->issued_at->toIso8601String(),
            'issued_by' => $this->whenLoaded('issuedBy', fn () => $this->issuedBy?->name),
            'voided_at' => $this->voided_at?->toIso8601String(),
            'voided_by' => $this->whenLoaded('voidedBy', fn () => $this->voidedBy?->name),
            'void_reason' => $this->void_reason,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn (FeeInvoiceItem $item) => [
                'id' => $item->id,
                'fee_type_id' => $item->fee_type_id,
                'description' => $item->description,
                'amount' => Money::format($item->amount),
            ])->values()),
        ];
    }
}

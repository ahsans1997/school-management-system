<?php

namespace App\Http\Resources;

use App\Models\FeeInvoiceRun;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FeeInvoiceRun */
class FeeInvoiceRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'academic_year' => $this->whenLoaded('academicYear', fn () => ['id' => $this->academicYear->id, 'name' => $this->academicYear->name]),
            'class' => $this->whenLoaded('schoolClass', fn () => ['id' => $this->schoolClass->id, 'name' => $this->schoolClass->name]),
            'section' => $this->whenLoaded('section', fn () => $this->section ? ['id' => $this->section->id, 'name' => $this->section->name] : null),
            'period' => $this->period,
            'due_date' => $this->due_date->toDateString(),
            'fee_type_ids' => $this->fee_type_ids,
            'status' => $this->status,
            'created' => $this->created,
            'skipped' => $this->skipped,
            'total' => Money::format($this->total),
            'error' => $this->error,
            'requested_by' => $this->whenLoaded('requestedBy', fn () => $this->requestedBy?->name),
            'created_at' => $this->created_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
        ];
    }
}

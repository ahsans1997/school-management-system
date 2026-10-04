<?php

namespace App\Http\Resources;

use App\Models\SchoolClass;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SchoolClass */
class SchoolClassResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'numeric_level' => $this->numeric_level,
            'shift' => $this->shift?->value,
            'medium' => $this->medium?->value,
            'sections_count' => $this->whenCounted('sections'),
            'subjects_count' => $this->whenCounted('subjects'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

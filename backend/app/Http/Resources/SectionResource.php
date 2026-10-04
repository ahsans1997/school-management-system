<?php

namespace App\Http\Resources;

use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Section */
class SectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'class_id' => $this->class_id,
            'name' => $this->name,
            'capacity' => $this->capacity,
            'class' => new SchoolClassResource($this->whenLoaded('schoolClass')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

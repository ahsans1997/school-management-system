<?php

namespace App\Http\Resources;

use App\Models\FeeType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FeeType */
class FeeTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'category' => $this->category->value,
            'is_active' => $this->is_active,
            'structures_count' => $this->whenCounted('structures'),
        ];
    }
}

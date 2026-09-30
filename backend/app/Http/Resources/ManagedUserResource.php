<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class ManagedUserResource extends JsonResource
{
    /**
     * Response shape for user-management endpoints. Never exposes the hash.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'roles' => $this->whenLoaded(
                'roles',
                fn () => $this->roles->pluck('name')->values(),
            ),
            'personable_type' => $this->personable_type,
            'personable_id' => $this->personable_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

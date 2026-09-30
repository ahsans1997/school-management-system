<?php

namespace App\Http\Resources;

use App\Enums\DashboardAudience;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'roles' => $this->getRoleNames()->values(),
            'permissions' => $this->getAllPermissions()->pluck('name')->values(),
            // Which area/dashboard the client should route this user to.
            'audience' => DashboardAudience::forUser($this->resource)->value,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

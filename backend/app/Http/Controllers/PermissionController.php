<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    /**
     * Full permission list, grouped by domain prefix for the assignment UI.
     */
    public function index(): JsonResponse
    {
        $grouped = Permission::query()
            ->where('guard_name', config('permissions.guard', 'api'))
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Permission $permission) => explode('.', $permission->name)[0])
            ->map(fn ($permissions, string $group) => [
                'group' => $group,
                'permissions' => $permissions->pluck('name')->values(),
            ])
            ->values();

        return response()->json(['data' => $grouped]);
    }
}

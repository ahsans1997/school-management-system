<?php

namespace App\Http\Controllers;

use App\Http\Resources\DashboardResource;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard): DashboardResource
    {
        return new DashboardResource($dashboard->build($request->user()));
    }
}

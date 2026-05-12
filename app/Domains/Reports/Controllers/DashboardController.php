<?php

namespace App\Domains\Reports\Controllers;

use App\Domains\Reports\Services\DashboardService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $dashboard = $this->dashboardService->getDashboard($request->user());
        return $this->success($dashboard);
    }
}

<?php

namespace App\Domains\Finance\Controllers;

use App\Domains\Finance\Services\BankAccountService;
use App\Domains\Finance\Services\FinanceReportService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceReportController extends Controller
{
    public function __construct(
        private readonly FinanceReportService $reportService,
        private readonly BankAccountService $accountService,
    ) {}

    public function monthlySummary(Request $request): JsonResponse
    {
        $request->validate([
            'year' => ['required', 'integer', 'min:2000'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $summary = $this->reportService->getMonthlySummary(
            $request->user(),
            (int) $request->year,
            (int) $request->month
        );

        return $this->success($summary);
    }

    public function yearlySummary(Request $request): JsonResponse
    {
        $request->validate(['year' => ['required', 'integer', 'min:2000']]);

        return $this->success($this->reportService->getYearlySummary($request->user(), (int) $request->year));
    }

    public function cashFlow(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        return $this->success($this->reportService->getCashFlow(
            $request->user(),
            $request->start_date,
            $request->end_date
        ));
    }

    public function consolidatedBalance(Request $request): JsonResponse
    {
        return $this->success($this->accountService->getConsolidatedBalance($request->user()));
    }
}

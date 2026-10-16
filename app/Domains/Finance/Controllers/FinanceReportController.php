<?php

namespace App\Domains\Finance\Controllers;

use App\Domains\Finance\Services\BankAccountService;
use App\Domains\Finance\Services\FinanceReportService;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
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

    public function series(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'group' => ['required', 'in:day,month'],
        ]);
        if (Carbon::parse($request->start_date)->diffInDays(Carbon::parse($request->end_date)) > 400) {
            return $this->error('Período máximo: 400 dias', 422);
        }

        return $this->success($this->reportService->getSeries($request->user(), $request->start_date, $request->end_date, $request->group));
    }

    public function categoryTotals(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        return $this->success($this->reportService->getCategoryTotals($request->user(), $request->start_date, $request->end_date));
    }

    public function consolidatedBalance(Request $request): JsonResponse
    {
        return $this->success($this->accountService->getConsolidatedBalance($request->user()));
    }
}

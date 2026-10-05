<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SalesInvoiceResource;
use App\Services\SalesReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Sales activity reports — daily, monthly and by-branch breakdowns of
 * posted/partially_paid/paid invoices. Read-only, computed on demand from
 * sales_invoices — see SalesReportService for exactly which statuses count
 * and how each figure is derived. Nothing here is cached or stored.
 */
class SalesReportController extends Controller
{
    public function __construct(private readonly SalesReportService $salesReportService)
    {
    }

    public function daily(Request $request): JsonResponse
    {
        $data = $this->salesReportService->daily(
            $request->query('company_id'),
            $request->query('date', now()->toDateString()),
        );
        $data['invoices'] = SalesInvoiceResource::collection($data['invoices']);

        return response()->json(['data' => $data]);
    }

    public function monthly(Request $request): JsonResponse
    {
        $year = $request->integer('year', now()->year);
        $month = $request->integer('month', now()->month);

        if ($month < 1 || $month > 12) {
            throw ValidationException::withMessages(['month' => ['Month must be between 1 and 12.']]);
        }

        return response()->json([
            'data' => $this->salesReportService->monthly($request->query('company_id'), $year, $month),
        ]);
    }

    public function byBranch(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->salesReportService->byBranch(
                $request->query('company_id'),
                $request->query('date_from'),
                $request->query('date_to'),
            ),
        ]);
    }
}

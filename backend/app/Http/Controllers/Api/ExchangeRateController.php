<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use App\Services\ExchangeRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExchangeRateController extends Controller
{
    public function __construct(private readonly ExchangeRateService $rates)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate(['company_id' => ['required', 'uuid', 'exists:companies,id']]);

        return response()->json([
            'data' => ExchangeRate::where('company_id', $request->query('company_id'))
                ->orderByDesc('rate_date')
                ->limit((int) $request->query('limit', 90))
                ->get(),
        ]);
    }

    /** Rate in force today (or on ?date=) — what a new USD document would use. */
    public function current(Request $request): JsonResponse
    {
        $request->validate([
            'company_id' => ['required', 'uuid', 'exists:companies,id'],
            'date' => ['nullable', 'date'],
        ]);

        $date = $request->query('date', now()->toDateString());

        return response()->json(['data' => [
            'currency' => 'USD',
            'date' => $date,
            'rate' => $this->rates->rateFor($request->query('company_id'), 'USD', $date),
        ]]);
    }

    /** Enter (or correct) the day's rate: IQD per 1 USD. One row per day. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'uuid', 'exists:companies,id'],
            'currency' => ['required', 'in:USD'],
            'rate_date' => ['required', 'date', 'before_or_equal:today'],
            'rate' => ['required', 'numeric', 'gt:0', 'max:100000'],
        ]);

        $rate = $this->rates->set($data['company_id'], $data['currency'], $data['rate_date'], (float) $data['rate'], $request->user());

        return response()->json(['data' => $rate], 201);
    }
}

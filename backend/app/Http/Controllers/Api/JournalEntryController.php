<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\JournalEntry\StoreJournalEntryRequest;
use App\Http\Resources\JournalEntryResource;
use App\Models\JournalEntry;
use App\Services\JournalEntryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mostly a read-only ledger view: every entry is normally posted by
 * JournalEntryService from a Purchasing/Sales/Expense action — see
 * `reference_type`/`reference_id` to trace an entry back to what caused it.
 * `store()` is the exception: a manual entry for adjustments, opening
 * balances and corrections that don't come from one of those, still going
 * through the same JournalEntryService (so it's still balance-validated and
 * still append-only from here — no update/destroy route).
 */
class JournalEntryController extends Controller
{
    public function __construct(private readonly JournalEntryService $journalEntryService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $entries = JournalEntry::query()
            ->with('lines.account')
            ->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->query('company_id')))
            ->when($request->filled('reference_type'), fn ($q) => $q->where('reference_type', $request->query('reference_type')))
            ->when($request->filled('reference_id'), fn ($q) => $q->where('reference_id', $request->query('reference_id')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('entry_date', '>=', $request->query('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('entry_date', '<=', $request->query('date_to')))
            ->orderBy('entry_date', 'desc')
            ->orderBy('entry_number', 'desc')
            ->paginate($request->integer('per_page', 30));

        return response()->json(JournalEntryResource::collection($entries)->response()->getData(true));
    }

    public function show(JournalEntry $journalEntry): JsonResponse
    {
        return response()->json(['data' => new JournalEntryResource($journalEntry->load('lines.account'))]);
    }

    public function store(StoreJournalEntryRequest $request): JsonResponse
    {
        $data = $request->validated();

        $entry = $this->journalEntryService->post(
            companyId: $data['company_id'],
            branchId: $data['branch_id'] ?? null,
            entryDate: $data['entry_date'],
            description: $data['description'],
            lines: $data['lines'],
            user: $request->user(),
        );

        return response()->json(['data' => new JournalEntryResource($entry)], 201);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expense\StoreExpenseRequest;
use App\Http\Requests\Expense\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Services\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function __construct(private readonly ExpenseService $expenseService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $expenses = Expense::query()
            ->with('paidFromAccount')
            ->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->query('company_id')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->query('branch_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('expense_date', '>=', $request->query('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('expense_date', '<=', $request->query('date_to')))
            ->orderBy('expense_date', 'desc')
            ->paginate($request->integer('per_page', 20));

        return response()->json(ExpenseResource::collection($expenses)->response()->getData(true));
    }

    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $expense = $this->expenseService->create($request->validated(), $request->user());

        return response()->json(['data' => new ExpenseResource($expense)], 201);
    }

    public function show(Expense $expense): JsonResponse
    {
        return response()->json(['data' => new ExpenseResource($expense->load(['items.account', 'paidFromAccount']))]);
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): JsonResponse
    {
        $expense = $this->expenseService->update($expense, $request->validated());

        return response()->json(['data' => new ExpenseResource($expense)]);
    }

    /** Posts the expense: Dr every line's expense account, Cr the paid-from cash/bank account. */
    public function post(Request $request, Expense $expense): JsonResponse
    {
        $expense = $this->expenseService->post($expense, $request->user());

        return response()->json(['data' => new ExpenseResource($expense)]);
    }

    public function cancel(Expense $expense): JsonResponse
    {
        return response()->json(['data' => new ExpenseResource($this->expenseService->cancel($expense))]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SalesInvoice\StoreSalesInvoiceRequest;
use App\Http\Requests\SalesInvoice\UpdateSalesInvoiceRequest;
use App\Http\Resources\SalesInvoiceResource;
use App\Models\SalesInvoice;
use App\Services\SalesInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesInvoiceController extends Controller
{
    public function __construct(private readonly SalesInvoiceService $salesInvoiceService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $invoices = SalesInvoice::query()
            ->with(['customer'])
            ->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->query('company_id')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->query('branch_id')))
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->query('customer_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('invoice_date', '>=', $request->query('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('invoice_date', '<=', $request->query('date_to')))
            ->orderBy('invoice_date', 'desc')
            ->paginate($request->integer('per_page', 20));

        return response()->json(SalesInvoiceResource::collection($invoices)->response()->getData(true));
    }

    public function store(StoreSalesInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->salesInvoiceService->create($request->validated(), $request->user());

        return response()->json(['data' => new SalesInvoiceResource($invoice)], 201);
    }

    public function show(SalesInvoice $salesInvoice): JsonResponse
    {
        return response()->json(['data' => new SalesInvoiceResource(
            $salesInvoice->load(['customer', 'items.product', 'items.stockMovements'])
        )]);
    }

    public function update(UpdateSalesInvoiceRequest $request, SalesInvoice $salesInvoice): JsonResponse
    {
        $salesInvoice = $this->salesInvoiceService->update($salesInvoice, $request->validated());

        return response()->json(['data' => new SalesInvoiceResource($salesInvoice)]);
    }

    /**
     * Posts the sale: debits stock (FEFO unless a line names a batch),
     * posts the revenue/tax/receivable/COGS journal entry, and the invoice
     * starts counting toward the customer's balance. Irreversible — use a
     * Sales Return to reverse it, not by editing this invoice.
     */
    public function post(Request $request, SalesInvoice $salesInvoice): JsonResponse
    {
        $invoice = $this->salesInvoiceService->post($salesInvoice, $request->user());

        return response()->json(['data' => new SalesInvoiceResource($invoice)]);
    }

    public function cancel(SalesInvoice $salesInvoice): JsonResponse
    {
        return response()->json(['data' => new SalesInvoiceResource($this->salesInvoiceService->cancel($salesInvoice))]);
    }
}

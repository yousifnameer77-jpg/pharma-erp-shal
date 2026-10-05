<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseInvoice\StorePurchaseInvoiceRequest;
use App\Http\Requests\PurchaseInvoice\UpdatePurchaseInvoiceRequest;
use App\Http\Resources\PurchaseInvoiceResource;
use App\Models\PurchaseInvoice;
use App\Services\PurchaseInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseInvoiceController extends Controller
{
    public function __construct(private readonly PurchaseInvoiceService $purchaseInvoiceService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $invoices = PurchaseInvoice::query()
            ->with(['supplier'])
            ->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->query('company_id')))
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', $request->query('supplier_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->orderBy('invoice_date', 'desc')
            ->paginate($request->integer('per_page', 20));

        return response()->json(PurchaseInvoiceResource::collection($invoices)->response()->getData(true));
    }

    public function store(StorePurchaseInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->purchaseInvoiceService->create($request->validated(), $request->user());

        return response()->json(['data' => new PurchaseInvoiceResource($invoice)], 201);
    }

    public function show(PurchaseInvoice $purchaseInvoice): JsonResponse
    {
        return response()->json(['data' => new PurchaseInvoiceResource($purchaseInvoice->load(['supplier', 'items.product']))]);
    }

    public function update(UpdatePurchaseInvoiceRequest $request, PurchaseInvoice $purchaseInvoice): JsonResponse
    {
        $purchaseInvoice = $this->purchaseInvoiceService->update($purchaseInvoice, $request->validated());

        return response()->json(['data' => new PurchaseInvoiceResource($purchaseInvoice)]);
    }

    /** Posts the invoice: creates the Accounts Payable liability and counts it toward the supplier's balance. */
    public function post(Request $request, PurchaseInvoice $purchaseInvoice): JsonResponse
    {
        $invoice = $this->purchaseInvoiceService->post($purchaseInvoice, $request->user());

        return response()->json(['data' => new PurchaseInvoiceResource($invoice)]);
    }

    public function cancel(PurchaseInvoice $purchaseInvoice): JsonResponse
    {
        return response()->json(['data' => new PurchaseInvoiceResource($this->purchaseInvoiceService->cancel($purchaseInvoice))]);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\PrescriptionRecord;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\Stock;
use App\Services\DocumentSequenceService;
use App\Services\JournalEntryService;
use App\Services\StockMovementService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PosController extends Controller
{
    public function __construct(
        private readonly DocumentSequenceService $sequences,
        private readonly StockMovementService $stockMovements,
    ) {
    }

    /**
     * Get products optimized for POS terminal with multi-tier pricing, batches, and stock.
     */
    public function products(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $user?->branch?->company_id ?? \App\Models\Company::first()?->id;
        $warehouseId = $request->header('X-Warehouse-Id') ?? $user?->branch?->warehouses()->first()?->id ?? \App\Models\Warehouse::first()?->id;

        $products = Product::with(['category', 'batches' => function ($q) {
            $q->where('expiry_date', '>', Carbon::today())
              ->orderBy('expiry_date', 'asc');
        }])
        ->where('is_active', true)
        ->get()
        ->map(function (Product $p) use ($warehouseId) {
            $totalStock = 0;
            $batchList = [];

            foreach ($p->batches as $b) {
                $qty = $warehouseId 
                    ? Stock::where('batch_id', $b->id)->where('warehouse_id', $warehouseId)->value('quantity_on_hand') ?? 0
                    : Stock::where('batch_id', $b->id)->sum('quantity_on_hand');

                $qty = (float) $qty;
                if ($qty > 0) {
                    $totalStock += $qty;
                    $daysToExpiry = Carbon::today()->diffInDays(Carbon::parse($b->expiry_date), false);
                    $batchList[] = [
                        'id' => $b->id,
                        'batch_number' => $b->batch_number,
                        'expiry_date' => $b->expiry_date,
                        'quantity' => $qty,
                        'days_to_expiry' => $daysToExpiry,
                    ];
                }
            }

            $cost = (float) ($p->purchase_price ?? 0);
            $retail = (float) ($p->sale_price ?? 0);
            $wholesale = (float) ($p->wholesale_price ?? ($retail > 0 ? round($retail * 0.85, 2) : round($cost * 1.15, 2)));

            $profitRetail = $cost > 0 ? round((($retail - $cost) / $cost) * 100, 1) : 0;
            $profitWholesale = $cost > 0 ? round((($wholesale - $cost) / $cost) * 100, 1) : 0;

            return [
                'id' => $p->id,
                'code' => $p->code,
                'barcode' => $p->barcode ?? $p->code,
                'name' => $p->name,
                'generic_name' => $p->generic_name,
                'form' => $p->form,
                'strength' => $p->strength,
                'category_name' => $p->category?->name ?? 'عام (General)',
                'is_controlled_substance' => (bool) $p->is_controlled_substance,
                'requires_prescription' => (bool) $p->requires_prescription,
                'purchase_price' => $cost,
                'wholesale_price' => $wholesale,
                'sale_price' => $retail,
                'profit_margin_retail' => $profitRetail,
                'profit_margin_wholesale' => $profitWholesale,
                'total_stock' => (float) $totalStock,
                'batches' => $batchList,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $products,
        ]);
    }

    /**
     * Fast POS Checkout with multi-tier pricing, FEFO stock decrement, and prescription registry.
     */
    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.batch_id' => 'nullable|string',
            'pricing_tier' => 'required|in:retail,wholesale',
            'payment_method' => 'required|in:cash,card,debt',
            'customer_id' => 'nullable|string',
            'customer_name' => 'nullable|string',
            'discount_amount' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'prescription' => 'nullable|array',
            'prescription.patient_name' => 'nullable|string|max:150',
            'prescription.patient_national_id' => 'nullable|string|max:50',
            'prescription.doctor_name' => 'nullable|string|max:150',
            'prescription.doctor_syndicate_id' => 'nullable|string|max:50',
            'prescription.prescription_number' => 'nullable|string|max:50',
            'prescription.prescription_date' => 'nullable|date',
            'prescription.diagnosis_notes' => 'nullable|string',
        ]);

        $user = $request->user();
        $companyId = $user?->branch?->company_id ?? \App\Models\Company::first()?->id;
        $branchId = $request->header('X-Branch-Id') ?? $user?->branch_id ?? \App\Models\Branch::first()?->id;
        $warehouseId = $request->header('X-Warehouse-Id') ?? \App\Models\Warehouse::where('branch_id', $branchId)->value('id') ?? \App\Models\Warehouse::first()?->id;

        // Get or Create Walk-in Customer
        $customerId = $validated['customer_id'] ?? null;
        if (!$customerId) {
            $walkIn = Customer::firstOrCreate(
                ['company_id' => $companyId, 'name' => 'زبون نقدي مباشر (Walk-in Customer)'],
                ['code' => 'CUST-CASH', 'phone' => '0770000000', 'is_active' => true]
            );
            $customerId = $walkIn->id;
        }

        return DB::transaction(function () use ($validated, $user, $companyId, $branchId, $warehouseId, $customerId) {
            $invoiceNumber = $this->sequences->next($companyId, 'sales_invoice', 'INV-POS');

            $subtotal = 0;
            $itemsData = [];
            $controlledItems = [];

            foreach ($validated['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);
                $qty = (float) $item['quantity'];
                $unitPrice = (float) $item['unit_price'];
                $lineSubtotal = round($qty * $unitPrice, 2);
                $subtotal += $lineSubtotal;

                $itemsData[] = [
                    'product' => $product,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'subtotal' => $lineSubtotal,
                    'batch_id' => $item['batch_id'] ?? null,
                ];

                if ($product->is_controlled_substance || $product->requires_prescription) {
                    $controlledItems[] = [
                        'product' => $product,
                        'quantity' => $qty,
                        'batch_id' => $item['batch_id'] ?? null,
                    ];
                }
            }

            // Verify prescription details if controlled substances are present
            if (!empty($controlledItems)) {
                $rx = $validated['prescription'] ?? [];
                if (empty($rx['doctor_name']) || empty($rx['prescription_number']) || empty($rx['patient_name'])) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'السلة تحتوي على أدوية مراقبة أو خاضعة لوصفة طبية. يجب إدخال اسم الطبيب، رقم الوصفة، واسم المريض قبل الصرف.',
                    ], 422);
                }
            }

            $discount = (float) ($validated['discount_amount'] ?? 0);
            $tax = (float) ($validated['tax_amount'] ?? 0);
            $total = max(0, round($subtotal - $discount + $tax, 2));
            $isPaid = in_array($validated['payment_method'], ['cash', 'card']);
            $paidAmount = $isPaid ? $total : 0;

            $invoice = SalesInvoice::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'warehouse_id' => $warehouseId,
                'customer_id' => $customerId,
                'invoice_number' => $invoiceNumber,
                'invoice_date' => Carbon::today()->toDateString(),
                'status' => $isPaid ? 'paid' : 'posted',
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'tax_amount' => $tax,
                'total_amount' => $total,
                'paid_amount' => $paidAmount,
                'notes' => 'POS Checkout [' . strtoupper($validated['pricing_tier']) . '] Via ' . strtoupper($validated['payment_method']),
                'created_by' => $user->id,
                'posted_at' => Carbon::now(),
            ]);

            // Deduct stock through StockMovementService: it row-locks, picks
            // FEFO (or the chosen batch), refuses expired/insufficient stock
            // and writes the append-only movement ledger. A failure throws and
            // rolls the whole checkout back — nothing is sold that isn't there.
            $allMovements = collect();

            foreach ($itemsData as $row) {
                $movements = $this->stockMovements->recordSale([
                    'product_id' => $row['product']->id,
                    'batch_id' => $row['batch_id'],
                    'warehouse_id' => $warehouseId,
                    'quantity' => $row['quantity'],
                    'reference_type' => 'sales_invoice',
                    'reference_id' => $invoice->id,
                    'notes' => "POS sale {$invoiceNumber}",
                ], $user);

                $allMovements = $allMovements->merge($movements);

                // One invoice line per batch drawn, so returns can target the right batch.
                $remaining = $row['subtotal'];
                $count = $movements->count();
                foreach ($movements->values() as $i => $movement) {
                    $lineTotal = $i === $count - 1
                        ? round($remaining, 2)
                        : round((float) $movement->quantity * $row['unit_price'], 2);
                    $remaining -= $lineTotal;

                    SalesInvoiceItem::create([
                        'sales_invoice_id' => $invoice->id,
                        'product_id' => $row['product']->id,
                        'batch_id' => $movement->batch_id,
                        'quantity' => $movement->quantity,
                        'unit_price' => $row['unit_price'],
                        'tax_rate' => $row['product']->tax_rate ?? 0,
                        'discount_rate' => 0,
                        'line_total' => $lineTotal,
                    ]);
                }
            }

            $this->postJournal($invoice, $allMovements, $validated['payment_method'], $user);

            // Save Prescription Records if controlled
            $savedPrescriptions = [];
            if (!empty($controlledItems)) {
                $rx = $validated['prescription'];
                foreach ($controlledItems as $ci) {
                    $pr = PrescriptionRecord::create([
                        'company_id' => $companyId,
                        'branch_id' => $branchId,
                        'sales_invoice_id' => $invoice->id,
                        'product_id' => $ci['product']->id,
                        'batch_id' => $ci['batch_id'],
                        'patient_name' => $rx['patient_name'],
                        'patient_national_id' => $rx['patient_national_id'] ?? null,
                        'patient_phone' => $validated['customer_phone'] ?? null,
                        'doctor_name' => $rx['doctor_name'],
                        'doctor_syndicate_id' => $rx['doctor_syndicate_id'] ?? null,
                        'doctor_clinic' => $rx['doctor_clinic'] ?? null,
                        'prescription_number' => $rx['prescription_number'],
                        'prescription_date' => $rx['prescription_date'] ?? Carbon::today()->toDateString(),
                        'diagnosis_notes' => $rx['diagnosis_notes'] ?? null,
                        'quantity_dispensed' => $ci['quantity'],
                        'dispensed_by' => $user->id,
                        'dispensed_at' => Carbon::now(),
                    ]);
                    $savedPrescriptions[] = $pr;
                }
            }

            // Audit Trail
            AuditLog::create([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'action' => 'POS_CHECKOUT',
                'auditable_type' => SalesInvoice::class,
                'auditable_id' => $invoice->id,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'new_values' => [
                    'invoice_number' => $invoiceNumber,
                    'total' => $total,
                    'pricing_tier' => $validated['pricing_tier'],
                    'payment_method' => $validated['payment_method'],
                    'controlled_drugs_count' => count($controlledItems),
                ],
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'تم إتمام عملية البيع وإصدار الفاتورة وتحديث المخزون بنجاح.',
                'data' => [
                    'receipt' => [
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoiceNumber,
                        'date' => Carbon::now()->format('Y-m-d H:i'),
                        'cashier' => $user->full_name ?? $user->username,
                        'customer' => $validated['customer_name'] ?? 'زبون نقدي مباشر',
                        'pricing_tier' => $validated['pricing_tier'] === 'retail' ? 'مفرد (Retail)' : 'جملة (Wholesale)',
                        'payment_method' => strtoupper($validated['payment_method']),
                        'items' => collect($itemsData)->map(fn($it) => [
                            'name' => $it['product']->name,
                            'unit_price' => $it['unit_price'],
                            'quantity' => $it['quantity'],
                            'subtotal' => $it['subtotal'],
                            'is_controlled' => (bool) $it['product']->is_controlled_substance,
                        ]),
                        'subtotal' => $subtotal,
                        'discount' => $discount,
                        'tax' => $tax,
                        'total' => $total,
                        'prescriptions' => $savedPrescriptions,
                    ],
                ],
            ]);
        });
    }

    /**
     * Revenue/COGS journal entry for a POS sale. Cash sales debit Cash, card
     * sales debit the Bank account, debt sales debit Accounts Receivable.
     */
    private function postJournal(SalesInvoice $invoice, $movements, string $method, $user): void
    {
        $companyId = $invoice->company_id;
        $batchCosts = Batch::whereIn('id', $movements->pluck('batch_id')->unique())->pluck('purchase_price', 'id');
        $totalCost = (float) $movements->sum(fn ($m) => (float) $m->quantity * (float) ($batchCosts[$m->batch_id] ?? 0));
        $netRevenue = (float) $invoice->subtotal - (float) $invoice->discount_amount;

        $debitCode = match ($method) {
            'cash' => ChartOfAccount::CODE_CASH,
            'card' => ChartOfAccount::CODE_BANK,
            default => ChartOfAccount::CODE_ACCOUNTS_RECEIVABLE,
        };

        $lines = [];
        if ((float) $invoice->total_amount > 0) {
            $lines[] = ['account_id' => ChartOfAccount::findByCode($companyId, $debitCode)->id, 'debit' => (float) $invoice->total_amount, 'description' => 'POS sale'];
        }
        if ($netRevenue > 0) {
            $lines[] = ['account_id' => ChartOfAccount::findByCode($companyId, ChartOfAccount::CODE_REVENUE)->id, 'credit' => $netRevenue, 'description' => 'Sales revenue'];
        }
        if ((float) $invoice->tax_amount > 0) {
            $lines[] = ['account_id' => ChartOfAccount::findByCode($companyId, ChartOfAccount::CODE_TAX_OUTPUT)->id, 'credit' => (float) $invoice->tax_amount, 'description' => 'Sales tax payable'];
        }
        if ($totalCost > 0) {
            $lines[] = ['account_id' => ChartOfAccount::findByCode($companyId, ChartOfAccount::CODE_COGS)->id, 'debit' => $totalCost, 'description' => 'Cost of goods sold'];
            $lines[] = ['account_id' => ChartOfAccount::findByCode($companyId, ChartOfAccount::CODE_INVENTORY)->id, 'credit' => $totalCost, 'description' => 'Stock sold'];
        }

        app(JournalEntryService::class)->post(
            companyId: $companyId,
            branchId: $invoice->branch_id,
            entryDate: $invoice->invoice_date->toDateString(),
            description: "POS sale {$invoice->invoice_number}",
            lines: $lines,
            user: $user,
            referenceType: 'sales_invoice',
            referenceId: $invoice->id,
        );
    }

    /**
     * Update product pricing tiers (Cost, Wholesale, Retail) with instant margins.
     */
    public function updatePrice(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'purchase_price' => 'nullable|numeric|min:0',
            'wholesale_price' => 'nullable|numeric|min:0',
            'sale_price' => 'required|numeric|min:0',
        ]);

        $product = Product::findOrFail($id);
        $cost = $validated['purchase_price'] ?? $product->purchase_price;
        $wholesale = $validated['wholesale_price'] ?? $product->wholesale_price;
        $retail = $validated['sale_price'];

        // Safety warning if selling below cost
        if ($cost && $retail < $cost) {
            return response()->json([
                'status' => 'error',
                'message' => 'تحذير أمني: لا يمكن تحديد سعر البيع بأقل من سعر التكلفة!',
            ], 422);
        }

        $product->update([
            'purchase_price' => $cost,
            'wholesale_price' => $wholesale,
            'sale_price' => $retail,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'تم تحديث مستويات الأسعار وهامش الربح بنجاح.',
            'data' => $product,
        ]);
    }
}


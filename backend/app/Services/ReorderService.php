<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\Product;
use App\Models\PurchaseRequest;
use App\Models\Stock;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ReorderService
{
    public function __construct(private readonly PurchaseRequestService $purchaseRequestService)
    {
    }

    public function getSuggestions(?string $warehouseId = null, ?string $branchId = null): array
    {
        $products = Product::where('is_active', true)
            ->with(['category:id,name', 'manufacturer:id,name'])
            ->get();

        $items = new Collection();
        $summary = [
            'total_products_below_min' => 0,
            'total_suggested_units' => 0,
            'estimated_total_reorder_cost' => 0.0,
        ];

        foreach ($products as $product) {
            $stockQuery = Stock::query()->whereHas('batch', fn ($q) => $q->where('product_id', $product->id));

            if ($warehouseId) {
                $stockQuery->where('warehouse_id', $warehouseId);
            } elseif ($branchId) {
                $stockQuery->whereHas('warehouse', fn ($q) => $q->where('branch_id', $branchId));
            }

            $currentStock = (float) $stockQuery->sum('quantity_on_hand');
            $minStock = (float) $product->min_stock_level;
            $reorderPoint = (float) $product->reorder_point;

            // Trigger if current stock is at or below reorder point or minimum stock
            $triggerThreshold = max($reorderPoint, $minStock);
            if ($triggerThreshold <= 0) {
                $triggerThreshold = 5.0; // sensible default if not explicitly configured
            }

            if ($currentStock > $triggerThreshold) {
                continue;
            }

            $targetStock = max($reorderPoint * 2, $minStock * 2, 10.0);
            $suggestedQty = (int) ceil(max($targetStock - $currentStock, 1.0));

            // Find last supplier and cost from the latest batch
            $lastBatch = Batch::where('product_id', $product->id)
                ->with('supplier:id,name')
                ->latest('created_at')
                ->first();

            $cost = (float) ($lastBatch?->purchase_price ?? $product->purchase_price ?? 0);
            $estCost = round($suggestedQty * $cost, 2);

            $summary['total_products_below_min']++;
            $summary['total_suggested_units'] += $suggestedQty;
            $summary['estimated_total_reorder_cost'] += $estCost;

            $items->push([
                'product_id' => $product->id,
                'name' => $product->name,
                'generic_name' => $product->generic_name,
                'code' => $product->code,
                'barcode' => $product->barcode,
                'category' => $product->category?->name,
                'manufacturer' => $product->manufacturer?->name,
                'current_stock' => $currentStock,
                'min_stock_level' => $minStock,
                'reorder_point' => $reorderPoint,
                'suggested_quantity' => $suggestedQty,
                'unit_cost' => $cost,
                'estimated_total_cost' => $estCost,
                'last_supplier' => $lastBatch?->supplier ? [
                    'id' => $lastBatch->supplier->id,
                    'name' => $lastBatch->supplier->name,
                ] : null,
            ]);
        }

        $summary['estimated_total_reorder_cost'] = round($summary['estimated_total_reorder_cost'], 2);

        return [
            'summary' => $summary,
            'items' => $items->sortBy('current_stock')->values(),
        ];
    }

    /**
     * Generate a Purchase Request from a chosen set of reorder items.
     *
     * @param array<int, array{product_id: string, quantity: float, notes?: ?string}> $items
     */
    public function generatePurchaseRequest(
        array $items,
        User $user,
        ?string $warehouseId = null,
        ?string $notes = null
    ): PurchaseRequest {
        if (empty($items)) {
            throw ValidationException::withMessages([
                'items' => ['At least one product item is required to generate a reorder request.'],
            ]);
        }

        $targetWarehouse = null;
        if ($warehouseId) {
            $targetWarehouse = Warehouse::with('branch')->findOrFail($warehouseId);
        } else {
            // Find main warehouse of user's branch, or any active warehouse
            $targetWarehouse = Warehouse::with('branch')
                ->where('is_active', true)
                ->when($user->branch_id, fn ($q) => $q->where('branch_id', $user->branch_id))
                ->where('type', 'main')
                ->first()
                ?? Warehouse::with('branch')->where('is_active', true)->first();
        }

        if (! $targetWarehouse) {
            throw ValidationException::withMessages([
                'warehouse_id' => ['No active warehouse found to assign this purchase request to.'],
            ]);
        }

        $companyId = $targetWarehouse->branch?->company_id ?? $user->company_id;
        if (! $companyId) {
            throw ValidationException::withMessages([
                'company_id' => ['Cannot determine company for purchase request.'],
            ]);
        }

        $requestData = [
            'company_id' => $companyId,
            'branch_id' => $targetWarehouse->branch_id,
            'warehouse_id' => $targetWarehouse->id,
            'notes' => $notes ?? 'Auto-generated from Reorder Suggestions',
            'items' => $items,
        ];

        $purchaseRequest = $this->purchaseRequestService->create($requestData, $user);

        AuditLog::record(
            action: 'created_from_reorder',
            auditable: $purchaseRequest,
            oldValues: null,
            newValues: [
                'request_number' => $purchaseRequest->request_number,
                'items_count' => count($items),
                'warehouse_id' => $targetWarehouse->id,
            ],
            userId: $user->id
        );

        return $purchaseRequest;
    }
}


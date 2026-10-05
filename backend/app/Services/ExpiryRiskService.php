<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\Stock;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ExpiryRiskService
{
    public function __construct(private readonly StockMovementService $stockMovementService)
    {
    }

    public function getExpiryRisk(
        ?string $branchId = null,
        ?string $warehouseId = null,
        ?string $riskLevel = null,
        int $daysThreshold = 180
    ): array {
        $today = Carbon::today();
        $limitDate = (clone $today)->addDays($daysThreshold);

        $stockQuery = Stock::query()
            ->where('quantity_on_hand', '>', 0)
            ->with([
                'batch.product:id,name,generic_name,code,barcode,purchase_price',
                'batch.supplier:id,name',
                'warehouse:id,name,branch_id,type',
            ]);

        if ($warehouseId) {
            $stockQuery->where('warehouse_id', $warehouseId);
        } elseif ($branchId) {
            $stockQuery->whereHas('warehouse', fn ($q) => $q->where('branch_id', $branchId));
        }

        $stockRows = $stockQuery->get();

        $items = new Collection();
        $summary = [
            'total_batches_at_risk' => 0,
            'expired_count' => 0,
            'expired_value' => 0.0,
            'critical_count' => 0,
            'critical_value' => 0.0,
            'warning_count' => 0,
            'warning_value' => 0.0,
            'notice_count' => 0,
            'notice_value' => 0.0,
            'total_financial_risk_value' => 0.0,
        ];

        foreach ($stockRows as $stock) {
            $batch = $stock->batch;
            if (! $batch || ! $batch->expiry_date) {
                continue;
            }

            $expiry = Carbon::parse($batch->expiry_date)->startOfDay();
            $diffDays = (int) $today->diffInDays($expiry, false);

            if ($diffDays > $daysThreshold) {
                continue;
            }

            $cost = (float) ($batch->purchase_price ?? $batch->product?->purchase_price ?? 0);
            $qty = (float) $stock->quantity_on_hand;
            $riskValue = round($qty * $cost, 3);

            if ($diffDays < 0) {
                $category = 'expired';
                $summary['expired_count']++;
                $summary['expired_value'] += $riskValue;
            } elseif ($diffDays <= 30) {
                $category = 'critical';
                $summary['critical_count']++;
                $summary['critical_value'] += $riskValue;
            } elseif ($diffDays <= 90) {
                $category = 'warning';
                $summary['warning_count']++;
                $summary['warning_value'] += $riskValue;
            } else {
                $category = 'notice';
                $summary['notice_count']++;
                $summary['notice_value'] += $riskValue;
            }

            $summary['total_batches_at_risk']++;
            $summary['total_financial_risk_value'] += $riskValue;

            if ($riskLevel && $riskLevel !== 'all' && $category !== $riskLevel) {
                continue;
            }

            $items->push([
                'stock_id' => $stock->id,
                'batch_id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'expiry_date' => $batch->expiry_date->toDateString(),
                'days_to_expiry' => $diffDays,
                'risk_level' => $category,
                'quantity_on_hand' => $qty,
                'reserved_quantity' => (float) $stock->reserved_quantity,
                'unit_cost' => $cost,
                'total_risk_value' => $riskValue,
                'warehouse' => [
                    'id' => $stock->warehouse->id,
                    'name' => $stock->warehouse->name,
                    'type' => $stock->warehouse->type,
                ],
                'product' => [
                    'id' => $batch->product->id,
                    'name' => $batch->product->name,
                    'generic_name' => $batch->product->generic_name,
                    'code' => $batch->product->code,
                    'barcode' => $batch->product->barcode,
                ],
                'supplier' => $batch->supplier ? [
                    'id' => $batch->supplier->id,
                    'name' => $batch->supplier->name,
                ] : null,
            ]);
        }

        // Round summary values
        $summary['expired_value'] = round($summary['expired_value'], 2);
        $summary['critical_value'] = round($summary['critical_value'], 2);
        $summary['warning_value'] = round($summary['warning_value'], 2);
        $summary['notice_value'] = round($summary['notice_value'], 2);
        $summary['total_financial_risk_value'] = round($summary['total_financial_risk_value'], 2);

        // Sort items by days_to_expiry ascending (expired first)
        $sortedItems = $items->sortBy('days_to_expiry')->values();

        return [
            'summary' => $summary,
            'items' => $sortedItems,
        ];
    }

    public function quarantineBatch(
        string $batchId,
        string $fromWarehouseId,
        float $quantity,
        User $user,
        ?string $notes = null
    ): array {
        $fromWarehouse = Warehouse::findOrFail($fromWarehouseId);
        $batch = Batch::with('product')->findOrFail($batchId);

        // Find or create a quarantine warehouse for the same branch
        $quarantineWarehouse = Warehouse::where('branch_id', $fromWarehouse->branch_id)
            ->where('type', 'quarantine')
            ->where('is_active', true)
            ->first();

        if (! $quarantineWarehouse) {
            $quarantineWarehouse = Warehouse::create([
                'branch_id' => $fromWarehouse->branch_id,
                'code' => 'QRN-' . substr($fromWarehouse->code, 0, 10),
                'name' => 'Quarantine Warehouse (' . $fromWarehouse->name . ')',
                'type' => 'quarantine',
                'is_active' => true,
            ]);
        }

        if ($quarantineWarehouse->id === $fromWarehouseId) {
            throw ValidationException::withMessages([
                'warehouse_id' => ['Batch is already in a quarantine warehouse.'],
            ]);
        }

        $movement = $this->stockMovementService->recordTransfer([
            'batch_id' => $batchId,
            'from_warehouse_id' => $fromWarehouseId,
            'to_warehouse_id' => $quarantineWarehouse->id,
            'quantity' => $quantity,
            'notes' => $notes ?? "Quarantined via Expiry Risk Control by {$user->full_name}",
        ], $user);

        AuditLog::record(
            action: 'quarantined',
            auditable: $batch,
            oldValues: ['warehouse_id' => $fromWarehouseId, 'warehouse_name' => $fromWarehouse->name],
            newValues: ['to_warehouse_id' => $quarantineWarehouse->id, 'quantity' => $quantity],
            userId: $user->id
        );

        return [
            'movement' => $movement,
            'quarantine_warehouse' => $quarantineWarehouse,
        ];
    }
}


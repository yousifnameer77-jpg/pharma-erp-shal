<?php

namespace App\Services;

use App\Exceptions\Inventory\ExpiredBatchException;
use App\Exceptions\Inventory\InsufficientStockException;
use App\Models\Batch;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only place stock_movements rows are created and stock quantities are
 * mutated. Every public method runs inside a DB transaction and takes row
 * locks (SELECT ... FOR UPDATE) on the batch/stock rows it touches before
 * checking anything — so two concurrent requests against the same
 * batch/warehouse serialize instead of racing, which is what actually makes
 * "never sell quantity that doesn't exist" hold under load, not just in the
 * happy path.
 */
class StockMovementService
{
    /**
     * Receive stock against an existing batch (new batches go through
     * BatchService::receive(), which creates the batch and calls this).
     */
    public function recordPurchase(array $data, User $user): StockMovement
    {
        return $this->apply(
            type: 'purchase_in',
            batchId: $data['batch_id'],
            quantity: (float) $data['quantity'],
            user: $user,
            toWarehouseId: $data['warehouse_id'],
            referenceType: $data['reference_type'] ?? null,
            referenceId: $data['reference_id'] ?? null,
            unitCost: $data['unit_cost'] ?? null,
        );
    }

    /**
     * Sell stock. Two modes:
     *  - `batch_id` given: sell from exactly that batch (a pharmacist
     *    deliberately picking one) — still blocked if it's expired.
     *  - `batch_id` omitted, `product_id` given: auto-allocate FEFO across
     *    every non-expired batch of that product in the warehouse, oldest
     *    expiry first, splitting across batches if one alone can't cover the
     *    quantity. This is the normal POS path and is what guarantees an
     *    expired batch is never sold by construction, not just by a check.
     *
     * @return Collection<int, StockMovement> one row per batch drawn from.
     */
    public function recordSale(array $data, User $user): Collection
    {
        $quantity = (float) $data['quantity'];

        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => ['Quantity must be greater than zero.']]);
        }

        return DB::transaction(function () use ($data, $quantity, $user) {
            if (! empty($data['batch_id'])) {
                return new Collection([
                    $this->sellSpecificBatch($data['batch_id'], $data['warehouse_id'], $quantity, $data, $user),
                ]);
            }

            return $this->sellFefo($data['product_id'], $data['warehouse_id'], $quantity, $data, $user);
        });
    }

    public function recordTransfer(array $data, User $user): StockMovement
    {
        return $this->apply(
            type: 'transfer',
            batchId: $data['batch_id'],
            quantity: (float) $data['quantity'],
            user: $user,
            fromWarehouseId: $data['from_warehouse_id'],
            toWarehouseId: $data['to_warehouse_id'],
            notes: $data['notes'] ?? null,
        );
    }

    /** A customer handing goods back — increases stock. */
    public function recordCustomerReturn(array $data, User $user): StockMovement
    {
        return $this->apply(
            type: 'return_in',
            batchId: $data['batch_id'],
            quantity: (float) $data['quantity'],
            user: $user,
            toWarehouseId: $data['warehouse_id'],
            referenceType: $data['reference_type'] ?? null,
            referenceId: $data['reference_id'] ?? null,
            notes: $data['notes'] ?? null,
        );
    }

    /** Sending goods back to a supplier — decreases stock. */
    public function recordSupplierReturn(array $data, User $user): StockMovement
    {
        return $this->apply(
            type: 'return_out',
            batchId: $data['batch_id'],
            quantity: (float) $data['quantity'],
            user: $user,
            fromWarehouseId: $data['warehouse_id'],
            referenceType: $data['reference_type'] ?? null,
            referenceId: $data['reference_id'] ?? null,
            notes: $data['notes'] ?? null,
        );
    }

    public function recordDamage(array $data, User $user): StockMovement
    {
        return $this->apply(
            type: 'damage_out',
            batchId: $data['batch_id'],
            quantity: (float) $data['quantity'],
            user: $user,
            fromWarehouseId: $data['warehouse_id'],
            notes: $data['notes'],
        );
    }

    /** How expired stock is formally written off — deliberately not expiry-guarded. */
    public function recordExpired(array $data, User $user): StockMovement
    {
        return $this->apply(
            type: 'expired_out',
            batchId: $data['batch_id'],
            quantity: (float) $data['quantity'],
            user: $user,
            fromWarehouseId: $data['warehouse_id'],
            notes: $data['notes'],
        );
    }

    public function recordAdjustment(array $data, User $user): StockMovement
    {
        $direction = $data['direction'];

        return $this->apply(
            type: $direction === 'in' ? 'adjustment_in' : 'adjustment_out',
            batchId: $data['batch_id'],
            quantity: (float) $data['quantity'],
            user: $user,
            fromWarehouseId: $direction === 'out' ? $data['warehouse_id'] : null,
            toWarehouseId: $direction === 'in' ? $data['warehouse_id'] : null,
            notes: $data['notes'],
        );
    }

    private function sellSpecificBatch(string $batchId, string $warehouseId, float $quantity, array $data, User $user): StockMovement
    {
        $batch = Batch::whereKey($batchId)->lockForUpdate()->firstOrFail();

        if ($batch->is_expired) {
            throw new ExpiredBatchException($batch);
        }

        $this->debit($warehouseId, $batch->id, $quantity);

        return StockMovement::create([
            'type' => 'sale_out',
            'batch_id' => $batch->id,
            'from_warehouse_id' => $warehouseId,
            'quantity' => $quantity,
            'reference_type' => $data['reference_type'] ?? null,
            'reference_id' => $data['reference_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'performed_by' => $user->id,
        ]);
    }

    /**
     * @return Collection<int, StockMovement>
     */
    private function sellFefo(string $productId, string $warehouseId, float $quantity, array $data, User $user): Collection
    {
        $eligible = Stock::query()
            ->join('batches', 'batches.id', '=', 'stock.batch_id')
            ->where('stock.warehouse_id', $warehouseId)
            ->where('batches.product_id', $productId)
            ->where('batches.expiry_date', '>=', now()->toDateString())
            ->where('stock.quantity_on_hand', '>', 0)
            ->orderBy('batches.expiry_date')
            ->lockForUpdate()
            ->get(['stock.*']);

        $available = (float) $eligible->sum(fn (Stock $stock) => $stock->quantity_on_hand - $stock->reserved_quantity);

        if ($available < $quantity) {
            throw new InsufficientStockException(
                productId: $productId,
                batchId: null,
                warehouseId: $warehouseId,
                requested: $quantity,
                available: $available,
            );
        }

        $remaining = $quantity;
        $movements = new Collection();

        foreach ($eligible as $stock) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($remaining, (float) $stock->quantity_on_hand - (float) $stock->reserved_quantity);

            if ($take <= 0) {
                continue;
            }

            $stock->decrement('quantity_on_hand', $take);

            $movements->push(StockMovement::create([
                'type' => 'sale_out',
                'batch_id' => $stock->batch_id,
                'from_warehouse_id' => $warehouseId,
                'quantity' => $take,
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'performed_by' => $user->id,
            ]));

            $remaining -= $take;
        }

        return $movements;
    }

    private function apply(
        string $type,
        string $batchId,
        float $quantity,
        User $user,
        ?string $fromWarehouseId = null,
        ?string $toWarehouseId = null,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?float $unitCost = null,
        ?string $notes = null,
    ): StockMovement {
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => ['Quantity must be greater than zero.']]);
        }

        return DB::transaction(function () use (
            $type, $batchId, $quantity, $user, $fromWarehouseId, $toWarehouseId,
            $referenceType, $referenceId, $unitCost, $notes,
        ) {
            $batch = Batch::whereKey($batchId)->lockForUpdate()->firstOrFail();

            if ($fromWarehouseId) {
                $this->debit($fromWarehouseId, $batch->id, $quantity);
            }

            if ($toWarehouseId) {
                $this->credit($toWarehouseId, $batch->id, $quantity);
            }

            return StockMovement::create([
                'type' => $type,
                'batch_id' => $batch->id,
                'from_warehouse_id' => $fromWarehouseId,
                'to_warehouse_id' => $toWarehouseId,
                'quantity' => $quantity,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'unit_cost' => $unitCost,
                'notes' => $notes,
                'performed_by' => $user->id,
            ]);
        });
    }

    private function debit(string $warehouseId, string $batchId, float $quantity): void
    {
        $stock = Stock::where('warehouse_id', $warehouseId)->where('batch_id', $batchId)->lockForUpdate()->first();
        $available = $stock ? (float) $stock->quantity_on_hand - (float) $stock->reserved_quantity : 0.0;

        if (! $stock || $available < $quantity) {
            throw new InsufficientStockException(
                productId: null,
                batchId: $batchId,
                warehouseId: $warehouseId,
                requested: $quantity,
                available: $available,
            );
        }

        $stock->decrement('quantity_on_hand', $quantity);
    }

    private function credit(string $warehouseId, string $batchId, float $quantity): void
    {
        $stock = Stock::where('warehouse_id', $warehouseId)->where('batch_id', $batchId)->lockForUpdate()->first();

        if (! $stock) {
            try {
                $stock = Stock::create([
                    'warehouse_id' => $warehouseId,
                    'batch_id' => $batchId,
                    'quantity_on_hand' => 0,
                    'reserved_quantity' => 0,
                ]);
            } catch (QueryException) {
                // Lost a race to create the (warehouse, batch) row — someone else's
                // concurrent credit() won it. Fetch and lock theirs instead.
                $stock = Stock::where('warehouse_id', $warehouseId)->where('batch_id', $batchId)->lockForUpdate()->firstOrFail();
            }
        }

        $stock->increment('quantity_on_hand', $quantity);
    }
}

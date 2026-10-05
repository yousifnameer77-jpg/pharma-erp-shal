<?php

namespace App\Models;

use App\Exceptions\Inventory\StockMovementImmutableException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only ledger: every stock change (purchase, sale, transfer, return,
 * damage, expiry write-off, manual adjustment) is one immutable row here.
 * The API deliberately exposes no update/destroy route for this resource,
 * and booted() below makes that a model-level invariant too — to correct a
 * mistake, record a compensating movement (e.g. an adjustment_in to undo an
 * over-counted damage_out), never edit or delete history.
 */
class StockMovement extends Model
{
    use HasUuids;

    const UPDATED_AT = null;

    public const TYPES = [
        'purchase_in', 'sale_out', 'transfer',
        'return_in', 'return_out',
        'damage_out', 'expired_out',
        'adjustment_in', 'adjustment_out',
    ];

    protected $fillable = [
        'type', 'batch_id', 'from_warehouse_id', 'to_warehouse_id', 'quantity',
        'reference_type', 'reference_id', 'unit_cost', 'notes', 'performed_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:3',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (self $movement) => throw new StockMovementImmutableException($movement));
        static::deleting(fn (self $movement) => throw new StockMovementImmutableException($movement));
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}

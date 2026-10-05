<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per (warehouse, batch): the current, fast-to-read snapshot of "how
 * much is here right now". stock_movements is the historical ledger that
 * explains how this number got here — never the other way around.
 */
class Stock extends Model
{
    use HasUuids;

    protected $table = 'stock';

    public $timestamps = false;

    protected $fillable = ['warehouse_id', 'batch_id', 'quantity_on_hand', 'reserved_quantity'];

    protected $casts = [
        'quantity_on_hand' => 'decimal:3',
        'reserved_quantity' => 'decimal:3',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $stock) {
            $stock->updated_at = now();
        });
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function getAvailableQuantityAttribute(): float
    {
        return (float) $this->quantity_on_hand - (float) $this->reserved_quantity;
    }
}

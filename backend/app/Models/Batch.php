<?php

namespace App\Models;

use App\Exceptions\Inventory\BatchInUseException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batch extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'product_id', 'batch_number', 'manufacture_date', 'expiry_date', 'supplier_id', 'purchase_price',
    ];

    protected function casts(): array
    {
        return [
            'manufacture_date' => 'date',
            'expiry_date' => 'date',
            'purchase_price' => 'decimal:3',
        ];
    }

    protected static function booted(): void
    {
        // A batch only exists in this system because a receiving movement
        // created it (see BatchService::receive()), so in practice this always
        // fires — deletion is not a supported way to correct a mistaken
        // receipt; record a compensating adjustment/return instead.
        static::deleting(function (self $batch) {
            if ($batch->stockMovements()->exists()) {
                throw new BatchInUseException($batch);
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function stock(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Convention: a batch is sellable through and including its expiry date
     * itself, and only counts as expired starting the day after.
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->expiry_date->lt(now()->startOfDay());
    }

    public function getTotalQuantityAttribute(): float
    {
        if (array_key_exists('stock_sum_quantity_on_hand', $this->attributes)) {
            return (float) $this->attributes['stock_sum_quantity_on_hand'];
        }

        return (float) $this->stock()->sum('quantity_on_hand');
    }

    public function scopeExpiringWithin(Builder $query, int $days): Builder
    {
        return $query->whereBetween('expiry_date', [now()->toDateString(), now()->addDays($days)->toDateString()]);
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expiry_date', '<', now()->toDateString());
    }
}

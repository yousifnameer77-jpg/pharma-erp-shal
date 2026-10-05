<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Product extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'category_id', 'manufacturer_id', 'code', 'barcode', 'name', 'generic_name',
        'form', 'strength', 'base_unit', 'pack_size',
        'is_controlled_substance', 'requires_prescription',
        'min_stock_level', 'reorder_point',
        'purchase_price', 'wholesale_price', 'sale_price', 'tax_rate', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_controlled_substance' => 'boolean',
            'requires_prescription' => 'boolean',
            'is_active' => 'boolean',
            'pack_size' => 'integer',
            'min_stock_level' => 'decimal:3',
            'reorder_point' => 'decimal:3',
            'purchase_price' => 'decimal:3',
            'wholesale_price' => 'decimal:3',
            'sale_price' => 'decimal:3',
            'tax_rate' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(ProductBarcode::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    /**
     * Every stock row across every batch of this product, via batches — lets
     * controllers preload a single summed total with withSum('stockRecords', ...)
     * instead of N+1 queries per product.
     */
    public function stockRecords(): HasManyThrough
    {
        return $this->hasManyThrough(Stock::class, Batch::class);
    }

    public function getTotalStockAttribute(): float
    {
        if (array_key_exists('total_stock_sum', $this->attributes)) {
            return (float) $this->attributes['total_stock_sum'];
        }

        return (float) $this->stockRecords()->sum('quantity_on_hand');
    }

    public function getIsBelowMinStockAttribute(): bool
    {
        return $this->total_stock < (float) $this->min_stock_level;
    }
}

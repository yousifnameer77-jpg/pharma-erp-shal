<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'purchase_order_id', 'product_id', 'quantity', 'unit_price',
        'tax_rate', 'received_quantity', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:3',
            'tax_rate' => 'decimal:2',
            'received_quantity' => 'decimal:3',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getRemainingQuantityAttribute(): float
    {
        return (float) $this->quantity - (float) $this->received_quantity;
    }

    public function getLineTotalAttribute(): float
    {
        $base = (float) $this->quantity * (float) $this->unit_price;

        return $base + $base * (float) $this->tax_rate / 100;
    }
}

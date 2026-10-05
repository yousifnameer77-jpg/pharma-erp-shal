<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesInvoiceItem extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'sales_invoice_id', 'product_id', 'batch_id', 'quantity', 'unit_price',
        'tax_rate', 'discount_rate', 'line_total', 'returned_quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:3',
            'tax_rate' => 'decimal:2',
            'discount_rate' => 'decimal:2',
            'line_total' => 'decimal:3',
            'returned_quantity' => 'decimal:3',
        ];
    }

    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function returnItems(): HasMany
    {
        return $this->hasMany(SalesReturnItem::class);
    }

    public function getRemainingReturnableQuantityAttribute(): float
    {
        return (float) $this->quantity - (float) $this->returned_quantity;
    }

    /**
     * Which batches actually got debited for this line — populated at
     * posting via StockMovementService::recordSale() (FEFO-split if the line
     * didn't name a batch). Traced back through the stock ledger rather than
     * stored redundantly here.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'reference_id')
            ->where('reference_type', 'sales_invoice_item');
    }
}

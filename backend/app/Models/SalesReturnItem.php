<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReturnItem extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'sales_return_id', 'sales_invoice_item_id', 'product_id', 'batch_id',
        'quantity', 'unit_price', 'tax_rate', 'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:3',
            'tax_rate' => 'decimal:2',
            'line_total' => 'decimal:3',
        ];
    }

    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class);
    }

    public function salesInvoiceItem(): BelongsTo
    {
        return $this->belongsTo(SalesInvoiceItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }
}

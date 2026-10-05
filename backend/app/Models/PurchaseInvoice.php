<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseInvoice extends Model
{
    use HasUuids;

    public const STATUSES = ['draft', 'posted', 'partially_paid', 'paid', 'cancelled'];

    protected $fillable = [
        'company_id', 'supplier_id', 'purchase_order_id', 'goods_receipt_id',
        'invoice_number', 'supplier_invoice_number', 'invoice_date', 'due_date',
        'status', 'subtotal', 'tax_amount', 'total_amount', 'paid_amount',
        'notes', 'created_by', 'posted_at', 'currency', 'exchange_rate',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:3',
            'tax_amount' => 'decimal:3',
            'total_amount' => 'decimal:3',
            'paid_amount' => 'decimal:3',
            'posted_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceItem::class);
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(SupplierPaymentAllocation::class);
    }

    public function getRemainingDueAttribute(): float
    {
        return (float) $this->total_amount - (float) $this->paid_amount;
    }
}

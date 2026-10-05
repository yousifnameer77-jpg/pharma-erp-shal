<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesInvoice extends Model
{
    use Auditable, HasUuids;

    public const STATUSES = ['draft', 'posted', 'partially_paid', 'paid', 'cancelled'];

    protected $fillable = [
        'company_id', 'branch_id', 'warehouse_id', 'customer_id',
        'invoice_number', 'invoice_date', 'due_date', 'status',
        'subtotal', 'discount_amount', 'tax_amount', 'total_amount', 'paid_amount',
        'notes', 'created_by', 'posted_at', 'currency', 'exchange_rate',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:3',
            'discount_amount' => 'decimal:3',
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

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesInvoiceItem::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SalesReturn::class);
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(CustomerPaymentAllocation::class);
    }

    public function getRemainingDueAttribute(): float
    {
        return (float) $this->total_amount - (float) $this->paid_amount;
    }
}

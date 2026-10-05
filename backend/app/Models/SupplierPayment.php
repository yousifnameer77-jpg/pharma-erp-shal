<?php

namespace App\Models;

use App\Exceptions\Purchasing\SupplierPaymentImmutableException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Append-only — see SupplierPaymentImmutableException and SupplierPaymentService::record(). */
class SupplierPayment extends Model
{
    use HasUuids;

    const UPDATED_AT = null;

    public const METHODS = ['cash', 'bank_transfer', 'cheque', 'card', 'other'];

    protected $fillable = [
        'company_id', 'supplier_id', 'payment_number', 'payment_date', 'amount',
        'method', 'paid_from_account_id', 'reference', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:3',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (self $payment) => throw new SupplierPaymentImmutableException($payment));
        static::deleting(fn (self $payment) => throw new SupplierPaymentImmutableException($payment));
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function paidFromAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'paid_from_account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(SupplierPaymentAllocation::class);
    }
}

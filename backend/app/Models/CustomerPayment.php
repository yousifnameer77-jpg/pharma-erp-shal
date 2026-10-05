<?php

namespace App\Models;

use App\Exceptions\Sales\CustomerPaymentImmutableException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Append-only — see CustomerPaymentImmutableException and CustomerPaymentService::record(). */
class CustomerPayment extends Model
{
    use HasUuids;

    const UPDATED_AT = null;

    public const METHODS = ['cash', 'bank_transfer', 'cheque', 'card', 'other'];

    protected $fillable = [
        'company_id', 'customer_id', 'payment_number', 'payment_date', 'amount',
        'method', 'received_into_account_id', 'reference', 'notes', 'created_by',
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
        static::updating(fn (self $payment) => throw new CustomerPaymentImmutableException($payment));
        static::deleting(fn (self $payment) => throw new CustomerPaymentImmutableException($payment));
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function receivedIntoAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'received_into_account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(CustomerPaymentAllocation::class);
    }
}

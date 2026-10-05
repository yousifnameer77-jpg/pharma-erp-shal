<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPaymentAllocation extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['customer_payment_id', 'sales_invoice_id', 'amount'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:3',
        ];
    }

    public function customerPayment(): BelongsTo
    {
        return $this->belongsTo(CustomerPayment::class);
    }

    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }
}

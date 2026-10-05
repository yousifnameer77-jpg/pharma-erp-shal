<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'customer_id' => $this->customer_id,
            'payment_number' => $this->payment_number,
            'payment_date' => $this->payment_date,
            'amount' => $this->amount,
            'method' => $this->method,
            'received_into_account_id' => $this->received_into_account_id,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'allocations' => $this->whenLoaded('allocations', fn () => $this->allocations->map(fn ($allocation) => [
                'sales_invoice_id' => $allocation->sales_invoice_id,
                'amount' => $allocation->amount,
            ])),
            'created_at' => $this->created_at,
        ];
    }
}

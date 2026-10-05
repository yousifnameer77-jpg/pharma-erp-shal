<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'supplier_id' => $this->supplier_id,
            'payment_number' => $this->payment_number,
            'payment_date' => $this->payment_date,
            'amount' => $this->amount,
            'method' => $this->method,
            'paid_from_account_id' => $this->paid_from_account_id,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'allocations' => $this->whenLoaded('allocations', fn () => $this->allocations->map(fn ($allocation) => [
                'purchase_invoice_id' => $allocation->purchase_invoice_id,
                'amount' => $allocation->amount,
            ])),
            'created_at' => $this->created_at,
        ];
    }
}

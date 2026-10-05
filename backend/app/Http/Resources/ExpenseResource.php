<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
            'expense_number' => $this->expense_number,
            'expense_date' => $this->expense_date,
            'paid_from_account_id' => $this->paid_from_account_id,
            'total_amount' => $this->total_amount,
            'status' => $this->status,
            'notes' => $this->notes,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'account_id' => $item->account_id,
                'account' => $item->relationLoaded('account') && $item->account ? [
                    'code' => $item->account->code,
                    'name' => $item->account->name,
                ] : null,
                'amount' => $item->amount,
                'description' => $item->description,
            ])),
            'posted_at' => $this->posted_at,
            'created_at' => $this->created_at,
        ];
    }
}

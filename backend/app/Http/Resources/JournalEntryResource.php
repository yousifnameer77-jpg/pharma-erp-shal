<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JournalEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entry_number' => $this->entry_number,
            'entry_date' => $this->entry_date,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'description' => $this->description,
            'created_by' => $this->created_by,
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($line) => [
                'id' => $line->id,
                'account_id' => $line->account_id,
                'account' => $line->relationLoaded('account') && $line->account ? [
                    'code' => $line->account->code,
                    'name' => $line->account->name,
                ] : null,
                'debit' => $line->debit,
                'credit' => $line->credit,
                'description' => $line->description,
            ])),
            'created_at' => $this->created_at,
        ];
    }
}

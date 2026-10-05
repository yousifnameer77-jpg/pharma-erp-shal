<?php

namespace App\Services;

use App\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;

/**
 * Hands out gapless-per-key, concurrency-safe document numbers such as
 * PO-000001, GR-000001, INV-000001 — one counter per (company, key), locked
 * with SELECT ... FOR UPDATE so two requests minting a number for the same
 * company/key in parallel still get distinct, sequential values instead of
 * racing to the same one.
 */
class DocumentSequenceService
{
    public function next(string $companyId, string $key, string $prefix): string
    {
        return DB::transaction(function () use ($companyId, $key, $prefix) {
            $sequence = DocumentSequence::where('company_id', $companyId)->where('key', $key)->lockForUpdate()->first();

            if (! $sequence) {
                $sequence = DocumentSequence::create(['company_id' => $companyId, 'key' => $key, 'next_number' => 1]);
                $sequence = DocumentSequence::where('id', $sequence->id)->lockForUpdate()->first();
            }

            $number = $sequence->next_number;
            $sequence->increment('next_number');

            return sprintf('%s-%06d', $prefix, $number);
        });
    }
}

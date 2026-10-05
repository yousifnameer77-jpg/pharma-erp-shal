<?php

namespace App\Exceptions\Accounting;

use App\Models\JournalEntry;
use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Raised by JournalEntry::booted()'s updating()/deleting() guards — mirrors
 * StockMovementImmutableException. The API exposes no update/destroy route
 * for journal entries at all; this makes it a model-level invariant too.
 */
class JournalEntryImmutableException extends Exception
{
    public function __construct(public readonly JournalEntry $entry)
    {
        parent::__construct("Journal entry {$entry->entry_number} is posted and cannot be modified or deleted. Post a reversing entry instead.");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'journal_entry_immutable',
            'journal_entry_id' => $this->entry->id,
        ], 422);
    }
}

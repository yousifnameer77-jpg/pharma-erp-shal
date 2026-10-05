<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntryLine extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['journal_entry_id', 'account_id', 'debit', 'credit', 'description'];

    protected function casts(): array
    {
        return [
            'debit' => 'decimal:3',
            'credit' => 'decimal:3',
        ];
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }
}

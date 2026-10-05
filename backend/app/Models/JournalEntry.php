<?php

namespace App\Models;

use App\Exceptions\Accounting\JournalEntryImmutableException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Append-only, like StockMovement — see that model's docblock for the
 * general pattern. Only JournalEntryService writes here.
 */
class JournalEntry extends Model
{
    use HasUuids;

    const UPDATED_AT = null;

    protected $fillable = [
        'company_id', 'branch_id', 'entry_number', 'entry_date',
        'reference_type', 'reference_id', 'description', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (self $entry) => throw new JournalEntryImmutableException($entry));
        static::deleting(fn (self $entry) => throw new JournalEntryImmutableException($entry));
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }
}

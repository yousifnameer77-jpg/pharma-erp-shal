<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @internal Written only by DocumentSequenceService. */
class DocumentSequence extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['company_id', 'key', 'next_number'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

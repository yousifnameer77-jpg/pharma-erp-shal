<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    use HasUuids;

    protected $fillable = ['company_id', 'currency', 'rate_date', 'rate', 'created_by'];

    protected function casts(): array
    {
        return ['rate_date' => 'date:Y-m-d', 'rate' => 'decimal:4'];
    }
}

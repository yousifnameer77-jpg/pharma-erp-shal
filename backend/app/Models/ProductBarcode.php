<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductBarcode extends Model
{
    use HasUuids;

    protected $fillable = ['product_id', 'barcode', 'packaging_unit', 'conversion_factor'];

    protected function casts(): array
    {
        return [
            'conversion_factor' => 'decimal:3',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

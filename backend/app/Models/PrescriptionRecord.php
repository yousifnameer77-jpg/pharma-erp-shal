<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionRecord extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'company_id',
        'branch_id',
        'sales_invoice_id',
        'product_id',
        'batch_id',
        'patient_name',
        'patient_national_id',
        'patient_phone',
        'doctor_name',
        'doctor_syndicate_id',
        'doctor_clinic',
        'prescription_number',
        'prescription_date',
        'diagnosis_notes',
        'quantity_dispensed',
        'dosage_instructions',
        'dispensed_by',
        'dispensed_at',
    ];

    protected function casts(): array
    {
        return [
            'prescription_date' => 'date',
            'dispensed_at' => 'datetime',
            'quantity_dispensed' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }

    public function dispensedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispensed_by');
    }
}


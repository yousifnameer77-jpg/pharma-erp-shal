<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Expense extends Model
{
    use HasUuids;

    public const STATUSES = ['draft', 'posted', 'cancelled'];

    protected $fillable = [
        'company_id', 'branch_id', 'expense_number', 'expense_date',
        'paid_from_account_id', 'total_amount', 'notes', 'status', 'created_by', 'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'total_amount' => 'decimal:3',
            'posted_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function paidFromAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'paid_from_account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ExpenseItem::class);
    }
}

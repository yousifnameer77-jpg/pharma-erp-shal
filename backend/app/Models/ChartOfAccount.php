<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChartOfAccount extends Model
{
    use HasUuids;

    /** Well-known codes the seeded default chart of accounts always provides — see AccountingSeeder. */
    public const CODE_INVENTORY = 'INVENTORY';

    public const CODE_GRNI = 'GRNI';

    public const CODE_ACCOUNTS_PAYABLE = 'AP';

    public const CODE_TAX_INPUT = 'TAX_INPUT';

    public const CODE_CASH = 'CASH';

    public const CODE_ACCOUNTS_RECEIVABLE = 'AR';

    public const CODE_REVENUE = 'REVENUE';

    public const CODE_COGS = 'COGS';

    public const CODE_TAX_OUTPUT = 'TAX_OUTPUT';

    public const CODE_BANK = 'BANK';

    public const CODE_GENERAL_EXPENSE = 'EXPENSE';

    /**
     * Optional finer grouping within `type = asset`/`liability`, used only to
     * pick out accounts for the Cash / Banks / Receivables / Payables views
     * — see AccountingReportController. A company can have several `bank`
     * accounts; there's normally exactly one `cash`, one `receivable`
     * (CODE_ACCOUNTS_RECEIVABLE) and one `payable` (CODE_ACCOUNTS_PAYABLE).
     */
    public const CATEGORY_CASH = 'cash';

    public const CATEGORY_BANK = 'bank';

    public const CATEGORY_RECEIVABLE = 'receivable';

    public const CATEGORY_PAYABLE = 'payable';

    protected $fillable = ['company_id', 'code', 'name', 'type', 'category', 'parent_id', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ChartOfAccount::class, 'parent_id');
    }

    /**
     * Looks up one of this company's accounts by its well-known code (see the
     * CODE_* constants). Throws (uncaught -> 500) rather than silently
     * skipping a posting if the company's chart of accounts hasn't been
     * seeded with AccountingSeeder — a missing account is a setup bug, not a
     * user-facing 422.
     */
    public static function findByCode(string $companyId, string $code): self
    {
        return self::where('company_id', $companyId)->where('code', $code)->firstOrFail();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public static function forCategory(string $companyId, string $category)
    {
        return self::where('company_id', $companyId)->where('category', $category)->orderBy('code')->get();
    }
}

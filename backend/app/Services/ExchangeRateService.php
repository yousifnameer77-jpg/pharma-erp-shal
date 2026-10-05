<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Manual daily exchange rates (IQD per 1 USD) and the currency helpers every
 * posting service uses. IQD is the base currency, so its rate is always 1
 * and the ledger is always posted in IQD.
 */
class ExchangeRateService
{
    public const BASE = 'IQD';

    public const FOREIGN = 'USD';

    /** Create or overwrite the rate for a date (one rate per company/currency/day). */
    public function set(string $companyId, string $currency, string $date, float $rate, ?User $user = null): ExchangeRate
    {
        return ExchangeRate::updateOrCreate(
            ['company_id' => $companyId, 'currency' => $currency, 'rate_date' => $date],
            ['rate' => $rate, 'created_by' => $user?->id],
        );
    }

    /**
     * The rate in force on $date: that day's entry, else the most recent
     * earlier one (a missed day keeps yesterday's rate). No rate at all is an
     * error — we never guess a conversion.
     */
    public function rateFor(string $companyId, string $currency, string $date): float
    {
        if ($currency === self::BASE) {
            return 1.0;
        }

        $row = ExchangeRate::where('company_id', $companyId)
            ->where('currency', $currency)
            ->where('rate_date', '<=', $date)
            ->orderByDesc('rate_date')
            ->first();

        if (! $row) {
            throw ValidationException::withMessages([
                'currency' => ["No {$currency} exchange rate has been entered on or before {$date}. Enter today's rate first."],
            ]);
        }

        return (float) $row->rate;
    }

    /** Document-currency amount -> IQD at the document's rate. */
    public function toBase(float $amount, float $rate): float
    {
        return round($amount * $rate, 3);
    }

    /** Account that absorbs realized exchange differences (debit = loss, credit = gain). */
    public function gainLossAccount(string $companyId): ChartOfAccount
    {
        return ChartOfAccount::firstOrCreate(
            ['company_id' => $companyId, 'code' => ChartOfAccount::CODE_FX_GAIN_LOSS],
            ['name' => 'Foreign Exchange Gain/Loss', 'type' => 'expense', 'is_active' => true],
        );
    }
}

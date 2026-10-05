<?php

namespace Tests\Feature;

use App\Exceptions\Accounting\UnbalancedJournalEntryException;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Services\JournalEntryService;
use Tests\Support;
use Tests\TestCase;

class JournalEntryTest extends TestCase
{
    use Support;

    private function accounts(): array
    {
        $companyId = Company::firstOrFail()->id;

        return ChartOfAccount::where('company_id', $companyId)->limit(2)->pluck('id')->all();
    }

    public function test_balanced_entry_is_posted(): void
    {
        [$a, $b] = $this->accounts();
        $companyId = Company::firstOrFail()->id;

        $entry = app(JournalEntryService::class)->post(
            $companyId, null, now()->toDateString(), 'Test entry',
            [['account_id' => $a, 'debit' => 100], ['account_id' => $b, 'credit' => 100]],
            $this->admin(),
        );

        $this->assertCount(2, $entry->lines);
        $this->assertSame('100.000', number_format((float) $entry->lines->sum('debit'), 3, '.', ''));
    }

    public function test_unbalanced_entry_is_rejected_and_nothing_saved(): void
    {
        [$a, $b] = $this->accounts();
        $companyId = Company::firstOrFail()->id;

        $this->expectException(UnbalancedJournalEntryException::class);

        try {
            app(JournalEntryService::class)->post(
                $companyId, null, now()->toDateString(), 'Bad entry',
                [['account_id' => $a, 'debit' => 100], ['account_id' => $b, 'credit' => 90]],
                $this->admin(),
            );
        } finally {
            $this->assertDatabaseCount('journal_entries', 0);
        }
    }

    public function test_entry_numbers_are_sequential(): void
    {
        [$a, $b] = $this->accounts();
        $companyId = Company::firstOrFail()->id;
        $svc = app(JournalEntryService::class);
        $lines = [['account_id' => $a, 'debit' => 5], ['account_id' => $b, 'credit' => 5]];

        $e1 = $svc->post($companyId, null, now()->toDateString(), 'one', $lines, $this->admin());
        $e2 = $svc->post($companyId, null, now()->toDateString(), 'two', $lines, $this->admin());

        $this->assertNotSame($e1->entry_number, $e2->entry_number);
    }
}

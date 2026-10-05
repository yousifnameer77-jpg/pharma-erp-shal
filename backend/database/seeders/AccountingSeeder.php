<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\Company;
use Illuminate\Database\Seeder;

/**
 * Seeds the minimal chart of accounts the Purchasing and Sales posting logic
 * depends on (see the CODE_* constants on ChartOfAccount and their use in
 * GoodsReceiptService/PurchaseInvoiceService/SupplierPaymentService and
 * SalesInvoiceService/SalesReturnService/CustomerPaymentService), for every
 * company. Idempotent (updateOrCreate keyed on company_id+code), so it's safe
 * to re-run, and `seedForCompany()` is exposed for a future "create company"
 * flow to call directly instead of re-running the whole seeder.
 */
class AccountingSeeder extends Seeder
{
    public function run(): void
    {
        Company::all()->each(fn (Company $company) => self::seedForCompany($company));
    }

    public static function seedForCompany(Company $company): void
    {
        $accounts = [
            ['code' => ChartOfAccount::CODE_INVENTORY, 'name' => 'Inventory', 'type' => 'asset', 'category' => null],
            ['code' => ChartOfAccount::CODE_GRNI, 'name' => 'Goods Received Not Invoiced', 'type' => 'liability', 'category' => null],
            ['code' => ChartOfAccount::CODE_ACCOUNTS_PAYABLE, 'name' => 'Accounts Payable', 'type' => 'liability', 'category' => ChartOfAccount::CATEGORY_PAYABLE],
            ['code' => ChartOfAccount::CODE_TAX_INPUT, 'name' => 'Purchase Tax Input', 'type' => 'asset', 'category' => null],
            ['code' => ChartOfAccount::CODE_CASH, 'name' => 'Cash on Hand', 'type' => 'asset', 'category' => ChartOfAccount::CATEGORY_CASH],
            ['code' => ChartOfAccount::CODE_ACCOUNTS_RECEIVABLE, 'name' => 'Accounts Receivable', 'type' => 'asset', 'category' => ChartOfAccount::CATEGORY_RECEIVABLE],
            ['code' => ChartOfAccount::CODE_REVENUE, 'name' => 'Sales Revenue', 'type' => 'revenue', 'category' => null],
            ['code' => ChartOfAccount::CODE_COGS, 'name' => 'Cost of Goods Sold', 'type' => 'expense', 'category' => null],
            ['code' => ChartOfAccount::CODE_TAX_OUTPUT, 'name' => 'Sales Tax Payable', 'type' => 'liability', 'category' => null],
            // A starter bank account (companies typically add more via POST
            // /chart-of-accounts with category=bank) and a catch-all expense
            // account so Banks and Expenses both work out of the box.
            ['code' => ChartOfAccount::CODE_BANK, 'name' => 'Main Bank Account', 'type' => 'asset', 'category' => ChartOfAccount::CATEGORY_BANK],
            ['code' => ChartOfAccount::CODE_GENERAL_EXPENSE, 'name' => 'General Expenses', 'type' => 'expense', 'category' => null],
        ];

        foreach ($accounts as $account) {
            ChartOfAccount::updateOrCreate(
                ['company_id' => $company->id, 'code' => $account['code']],
                ['name' => $account['name'], 'type' => $account['type'], 'category' => $account['category'], 'is_active' => true],
            );
        }
    }
}

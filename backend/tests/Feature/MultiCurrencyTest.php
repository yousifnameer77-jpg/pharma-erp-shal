<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\SalesInvoice;
use App\Services\CustomerAccountService;
use App\Services\CustomerPaymentService;
use App\Services\ExchangeRateService;
use App\Services\GoodsReceiptService;
use App\Services\PurchaseInvoiceService;
use App\Services\PurchaseOrderService;
use App\Services\SalesInvoiceService;
use App\Services\SupplierAccountService;
use App\Services\SupplierPaymentService;
use Illuminate\Validation\ValidationException;
use Tests\Support;
use Tests\TestCase;

class MultiCurrencyTest extends TestCase
{
    use Support;

    private function rate(float $rate, ?string $date = null): void
    {
        app(ExchangeRateService::class)->set($this->company()->id, 'USD', $date ?? now()->toDateString(), $rate, $this->admin());
    }

    private function usdSalesInvoice(): SalesInvoice
    {
        $wh = $this->warehouse();
        $product = $this->product();
        $this->receive($product, $wh, 'FX1', now()->addYear()->toDateString(), 20);
        $svc = app(SalesInvoiceService::class);

        $invoice = $svc->create([
            'company_id' => $this->company()->id,
            'branch_id' => Branch::firstOrFail()->id,
            'warehouse_id' => $wh->id,
            'customer_id' => $this->customer()->id,
            'invoice_date' => now()->toDateString(),
            'currency' => 'USD',
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 100]], // $200
        ], $this->admin());

        return $svc->post($invoice, $this->admin());
    }

    private function receiveCash(float $usd, ?array $allocations = null)
    {
        return app(CustomerPaymentService::class)->record(array_filter([
            'company_id' => $this->company()->id,
            'customer_id' => $this->customer()->id,
            'payment_date' => now()->toDateString(),
            'amount' => $usd,
            'currency' => 'USD',
            'method' => 'cash',
            'received_into_account_id' => $this->account(ChartOfAccount::CODE_CASH)->id,
            'allocations' => $allocations,
        ]), $this->admin());
    }

    public function test_usd_document_requires_an_exchange_rate(): void
    {
        $this->expectException(ValidationException::class);
        $this->usdSalesInvoice();
    }

    public function test_rate_falls_back_to_most_recent_earlier_day(): void
    {
        $this->rate(1300, now()->subDays(3)->toDateString());
        $svc = app(ExchangeRateService::class);

        $this->assertSame(1300.0, $svc->rateFor($this->company()->id, 'USD', now()->toDateString()));
        $this->assertSame(1.0, $svc->rateFor($this->company()->id, 'IQD', now()->toDateString()));
    }

    public function test_usd_sale_is_booked_in_iqd_at_the_invoice_rate(): void
    {
        $this->rate(1500);
        $invoice = $this->usdSalesInvoice();

        $this->assertSame('USD', $invoice->currency);
        $this->assertSame(200.0, (float) $invoice->total_amount);
        $this->assertSame(300000.0, (float) $invoice->fresh()->total_amount_base);
        $this->assertSame(300000.0, $this->accountBalance(ChartOfAccount::CODE_ACCOUNTS_RECEIVABLE));
        $this->assertSame(-300000.0, $this->accountBalance(ChartOfAccount::CODE_REVENUE));
        $this->assertSame(300000.0, app(CustomerAccountService::class)->balance($this->customer())['balance']);
        $this->assertLedgerBalanced();
    }

    public function test_payment_at_a_higher_rate_books_realized_gain(): void
    {
        $this->rate(1500, now()->subDays(2)->toDateString());
        $this->rate(1500);
        $invoice = $this->usdSalesInvoice();

        $this->rate(1520); // dollar strengthened before the customer paid
        $this->receiveCash(200);

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(0.0, $this->accountBalance(ChartOfAccount::CODE_ACCOUNTS_RECEIVABLE));
        // 200 x 1520 received vs 200 x 1500 booked = 4,000 IQD gain (credit).
        $this->assertSame(-4000.0, $this->accountBalance(ChartOfAccount::CODE_FX_GAIN_LOSS));
        $this->assertLedgerBalanced();
    }

    public function test_payment_at_a_lower_rate_books_realized_loss(): void
    {
        $this->rate(1500);
        $this->usdSalesInvoice();

        $this->rate(1480);
        $this->receiveCash(200);

        $this->assertSame(0.0, $this->accountBalance(ChartOfAccount::CODE_ACCOUNTS_RECEIVABLE));
        $this->assertSame(4000.0, $this->accountBalance(ChartOfAccount::CODE_FX_GAIN_LOSS));
        $this->assertLedgerBalanced();
    }

    public function test_iqd_payment_cannot_settle_a_usd_invoice(): void
    {
        $this->rate(1500);
        $invoice = $this->usdSalesInvoice();

        $this->expectException(ValidationException::class);

        app(CustomerPaymentService::class)->record([
            'company_id' => $this->company()->id,
            'customer_id' => $this->customer()->id,
            'payment_date' => now()->toDateString(),
            'amount' => 100,
            'method' => 'cash',
            'received_into_account_id' => $this->account(ChartOfAccount::CODE_CASH)->id,
            'allocations' => [['sales_invoice_id' => $invoice->id, 'amount' => 100]],
        ], $this->admin());
    }

    public function test_usd_purchase_and_supplier_payment_book_fx_difference(): void
    {
        $this->rate(1500);
        $wh = $this->warehouse();
        $product = $this->product();
        $po = app(PurchaseOrderService::class);

        $order = $po->create([
            'company_id' => $this->company()->id,
            'branch_id' => Branch::firstOrFail()->id,
            'warehouse_id' => $wh->id,
            'supplier_id' => $this->supplier()->id,
            'order_date' => now()->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 10, 'unit_price' => 10]],
        ], $this->admin());

        // No goods receipt: the invoice goes straight to inventory.
        $svc = app(PurchaseInvoiceService::class);
        $invoice = $svc->post($svc->create([
            'company_id' => $this->company()->id,
            'supplier_id' => $this->supplier()->id,
            'invoice_date' => now()->toDateString(),
            'currency' => 'USD',
            'items' => [['product_id' => $product->id, 'quantity' => 10, 'unit_price' => 10]], // $100
        ], $this->admin()), $this->admin());

        $this->assertSame(150000.0, $this->accountBalance(ChartOfAccount::CODE_INVENTORY));
        $this->assertSame(-150000.0, $this->accountBalance(ChartOfAccount::CODE_ACCOUNTS_PAYABLE));
        $this->assertSame(150000.0, app(SupplierAccountService::class)->balance($this->supplier())['balance']);

        $this->rate(1450); // dollar weakened: we pay fewer dinars than we owe
        app(SupplierPaymentService::class)->record([
            'company_id' => $this->company()->id,
            'supplier_id' => $this->supplier()->id,
            'payment_date' => now()->toDateString(),
            'amount' => 100,
            'currency' => 'USD',
            'method' => 'cash',
            'paid_from_account_id' => $this->account(ChartOfAccount::CODE_CASH)->id,
        ], $this->admin());

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(0.0, $this->accountBalance(ChartOfAccount::CODE_ACCOUNTS_PAYABLE));
        // Paid 145,000 IQD against 150,000 owed = 5,000 gain.
        $this->assertSame(-5000.0, $this->accountBalance(ChartOfAccount::CODE_FX_GAIN_LOSS));
        $this->assertSame(0.0, app(SupplierAccountService::class)->balance($this->supplier())['balance']);
        $this->assertLedgerBalanced();
    }

    public function test_exchange_rate_api_permissions_and_validation(): void
    {
        $payload = ['company_id' => $this->company()->id, 'currency' => 'USD', 'rate_date' => now()->toDateString(), 'rate' => 1510];

        $this->postJson('/api/v1/exchange-rates', $payload)->assertUnauthorized();

        $this->actingAsAdmin();
        $this->postJson('/api/v1/exchange-rates', $payload)->assertCreated();
        $this->postJson('/api/v1/exchange-rates', array_merge($payload, ['rate' => 1525]))->assertCreated();
        $this->assertDatabaseCount('exchange_rates', 1); // same day = corrected, not duplicated

        $this->postJson('/api/v1/exchange-rates', array_merge($payload, ['rate' => -5]))->assertStatus(422);
        $this->postJson('/api/v1/exchange-rates', array_merge($payload, ['currency' => 'EUR']))->assertStatus(422);
        $this->postJson('/api/v1/exchange-rates', array_merge($payload, ['rate_date' => now()->addDay()->toDateString()]))->assertStatus(422);

        $this->getJson('/api/v1/exchange-rates/current?company_id='.$this->company()->id)
            ->assertOk()->assertJsonPath('data.rate', 1525);
    }

    public function test_iqd_documents_are_unchanged_and_rate_is_one(): void
    {
        $wh = $this->warehouse();
        $product = $this->product();
        $this->receive($product, $wh, 'IQ1', now()->addYear()->toDateString(), 5);
        $svc = app(SalesInvoiceService::class);

        $invoice = $svc->create([
            'company_id' => $this->company()->id,
            'branch_id' => Branch::firstOrFail()->id,
            'warehouse_id' => $wh->id,
            'customer_id' => $this->customer()->id,
            'invoice_date' => now()->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1500]],
        ], $this->admin());

        $this->assertSame('IQD', $invoice->currency);
        $this->assertSame(1.0, (float) $invoice->exchange_rate);
    }
}

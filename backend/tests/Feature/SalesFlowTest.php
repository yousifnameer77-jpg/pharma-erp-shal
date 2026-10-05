<?php

namespace Tests\Feature;

use App\Exceptions\Sales\OverpaymentException;
use App\Exceptions\Sales\OverReturnException;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Services\CustomerAccountService;
use App\Services\CustomerPaymentService;
use App\Services\SalesInvoiceService;
use App\Services\SalesReturnService;
use Tests\Support;
use Tests\TestCase;

class SalesFlowTest extends TestCase
{
    use Support;

    /** Posted invoice: 5 units @ 1500 sold from a batch of 20 costing 1000. */
    private function postedInvoice(): array
    {
        $wh = $this->warehouse();
        $product = $this->product();
        $batch = $this->receive($product, $wh, 'S1', now()->addYear()->toDateString(), 20);
        $svc = app(SalesInvoiceService::class);

        $invoice = $svc->create([
            'company_id' => $this->company()->id,
            'branch_id' => Branch::firstOrFail()->id,
            'warehouse_id' => $wh->id,
            'customer_id' => $this->customer()->id,
            'invoice_date' => now()->toDateString(),
            'items' => [['product_id' => $product->id, 'batch_id' => $batch->id, 'quantity' => 5, 'unit_price' => 1500]],
        ], $this->admin());

        return [$svc->post($invoice, $this->admin()), $wh, $batch];
    }

    public function test_posting_invoice_reduces_stock_and_books_revenue_and_cogs(): void
    {
        [$invoice, $wh, $batch] = $this->postedInvoice();

        $this->assertSame(15.0, $this->onHand($batch, $wh));
        $this->assertSame(7500.0, (float) $invoice->total_amount);
        $this->assertSame(7500.0, $this->accountBalance(ChartOfAccount::CODE_ACCOUNTS_RECEIVABLE));
        $this->assertSame(-7500.0, $this->accountBalance(ChartOfAccount::CODE_REVENUE));
        $this->assertSame(5000.0, $this->accountBalance(ChartOfAccount::CODE_COGS));
        $this->assertLedgerBalanced();
    }

    public function test_cannot_invoice_more_than_stock(): void
    {
        $wh = $this->warehouse();
        $product = $this->product();
        $this->receive($product, $wh, 'S1', now()->addYear()->toDateString(), 2);
        $svc = app(SalesInvoiceService::class);

        $invoice = $svc->create([
            'company_id' => $this->company()->id,
            'branch_id' => Branch::firstOrFail()->id,
            'warehouse_id' => $wh->id,
            'customer_id' => $this->customer()->id,
            'invoice_date' => now()->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 3, 'unit_price' => 1500]],
        ], $this->admin());

        $this->expectException(\App\Exceptions\Inventory\InsufficientStockException::class);
        $svc->post($invoice, $this->admin());
    }

    public function test_customer_payments_settle_balance_and_overpayment_is_rejected(): void
    {
        [$invoice] = $this->postedInvoice();
        $pay = fn (float $amount, ?array $alloc = null) => app(CustomerPaymentService::class)->record(array_filter([
            'company_id' => $this->company()->id,
            'customer_id' => $this->customer()->id,
            'payment_date' => now()->toDateString(),
            'amount' => $amount,
            'method' => 'cash',
            'received_into_account_id' => $this->account(ChartOfAccount::CODE_CASH)->id,
            'allocations' => $alloc,
        ]), $this->admin());

        $pay(3000);
        $this->assertSame('partially_paid', $invoice->fresh()->status);
        $this->assertSame(4500.0, app(CustomerAccountService::class)->balance($this->customer())['balance']);

        $pay(4500);
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(0.0, $this->accountBalance(ChartOfAccount::CODE_ACCOUNTS_RECEIVABLE));
        $this->assertLedgerBalanced();

        $this->expectException(\Throwable::class);
        $pay(100, [['sales_invoice_id' => $invoice->id, 'amount' => 100]]);
    }

    public function test_return_restores_stock_and_reverses_accounting(): void
    {
        [$invoice, $wh, $batch] = $this->postedInvoice();
        $item = $invoice->items->first();

        $svc = app(SalesReturnService::class);
        $return = $svc->create([
            'company_id' => $this->company()->id,
            'warehouse_id' => $wh->id,
            'customer_id' => $this->customer()->id,
            'sales_invoice_id' => $invoice->id,
            'return_date' => now()->toDateString(),
            'items' => [[
                'sales_invoice_item_id' => $item->id, 'product_id' => $item->product_id,
                'batch_id' => $batch->id, 'quantity' => 2, 'unit_price' => 1500,
            ]],
        ], $this->admin());
        $svc->post($return, $this->admin());

        $this->assertSame(17.0, $this->onHand($batch, $wh));
        $this->assertSame(4500.0, $this->accountBalance(ChartOfAccount::CODE_ACCOUNTS_RECEIVABLE));
        $this->assertSame(3000.0, $this->accountBalance(ChartOfAccount::CODE_COGS));
        $this->assertSame(2.0, (float) $item->fresh()->returned_quantity);
        $this->assertLedgerBalanced();
    }

    public function test_cannot_return_more_than_sold(): void
    {
        [$invoice, $wh, $batch] = $this->postedInvoice();
        $item = $invoice->items->first();

        $svc = app(SalesReturnService::class);
        $return = $svc->create([
            'company_id' => $this->company()->id,
            'warehouse_id' => $wh->id,
            'customer_id' => $this->customer()->id,
            'sales_invoice_id' => $invoice->id,
            'return_date' => now()->toDateString(),
            'items' => [[
                'sales_invoice_item_id' => $item->id, 'product_id' => $item->product_id,
                'batch_id' => $batch->id, 'quantity' => 6, 'unit_price' => 1500,
            ]],
        ], $this->admin());

        $this->expectException(OverReturnException::class);
        $svc->post($return, $this->admin());
    }
}

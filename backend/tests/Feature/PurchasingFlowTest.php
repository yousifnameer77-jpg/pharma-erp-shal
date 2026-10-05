<?php

namespace Tests\Feature;

use App\Exceptions\Purchasing\OverpaymentException;
use App\Exceptions\Purchasing\OverReceiptException;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Services\GoodsReceiptService;
use App\Services\PurchaseInvoiceService;
use App\Services\PurchaseOrderService;
use App\Services\SupplierAccountService;
use App\Services\SupplierPaymentService;
use Tests\Support;
use Tests\TestCase;

class PurchasingFlowTest extends TestCase
{
    use Support;

    /** Approved PO of 10 units @ 1000 for one product. */
    private function approvedOrder(): array
    {
        $wh = $this->warehouse();
        $product = $this->product();
        $svc = app(PurchaseOrderService::class);

        $order = $svc->create([
            'company_id' => $this->company()->id,
            'branch_id' => Branch::firstOrFail()->id,
            'warehouse_id' => $wh->id,
            'supplier_id' => $this->supplier()->id,
            'order_date' => now()->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 10, 'unit_price' => 1000]],
        ], $this->admin());

        $svc->submit($order);
        $svc->approve($order->fresh(), $this->admin());

        return [$order->fresh('items'), $wh, $product];
    }

    private function receipt($order, $wh, float $qty)
    {
        return app(GoodsReceiptService::class)->create([
            'company_id' => $this->company()->id,
            'warehouse_id' => $wh->id,
            'purchase_order_id' => $order->id,
            'receipt_date' => now()->toDateString(),
            'items' => [[
                'purchase_order_item_id' => $order->items->first()->id,
                'batch_number' => 'GR-B1',
                'expiry_date' => now()->addYear()->toDateString(),
                'quantity' => $qty,
                'unit_cost' => 1000,
            ]],
        ], $this->admin());
    }

    private function postedInvoice($order, $receipt)
    {
        $svc = app(PurchaseInvoiceService::class);
        $invoice = $svc->create([
            'company_id' => $this->company()->id,
            'supplier_id' => $order->supplier_id,
            'purchase_order_id' => $order->id,
            'goods_receipt_id' => $receipt->id,
            'invoice_date' => now()->toDateString(),
            'items' => [['product_id' => $order->items->first()->product_id, 'quantity' => 10, 'unit_price' => 1000]],
        ], $this->admin());

        return $svc->post($invoice, $this->admin());
    }

    public function test_posting_goods_receipt_adds_stock_and_balanced_journal(): void
    {
        [$order, $wh] = $this->approvedOrder();
        $receipt = $this->receipt($order, $wh, 10);

        app(GoodsReceiptService::class)->post($receipt, $this->admin());

        $this->assertSame(10.0, (float) \App\Models\Stock::where('warehouse_id', $wh->id)->sum('quantity_on_hand'));
        $this->assertSame(10000.0, $this->accountBalance(ChartOfAccount::CODE_INVENTORY));
        $this->assertSame('received', $order->fresh()->status);
        $this->assertLedgerBalanced();
    }

    public function test_cannot_receive_more_than_ordered(): void
    {
        [$order, $wh] = $this->approvedOrder();

        $this->expectException(OverReceiptException::class);
        $this->receipt($order, $wh, 11);
    }

    public function test_partial_receipt_marks_order_partially_received(): void
    {
        [$order, $wh] = $this->approvedOrder();
        $receipt = $this->receipt($order, $wh, 4);
        app(GoodsReceiptService::class)->post($receipt, $this->admin());

        $this->assertSame('partially_received', $order->fresh()->status);
    }

    public function test_invoice_and_payment_settle_supplier_balance(): void
    {
        [$order, $wh] = $this->approvedOrder();
        $receipt = $this->receipt($order, $wh, 10);
        app(GoodsReceiptService::class)->post($receipt, $this->admin());
        $invoice = $this->postedInvoice($order, $receipt);

        $balances = app(SupplierAccountService::class);
        $this->assertSame(10000.0, $balances->balance($this->supplier())['balance']);
        $this->assertSame(-10000.0, $this->accountBalance(ChartOfAccount::CODE_ACCOUNTS_PAYABLE));

        $pay = fn (float $amount) => app(SupplierPaymentService::class)->record([
            'company_id' => $this->company()->id,
            'supplier_id' => $this->supplier()->id,
            'payment_date' => now()->toDateString(),
            'amount' => $amount,
            'method' => 'cash',
            'paid_from_account_id' => $this->account(ChartOfAccount::CODE_CASH)->id,
        ], $this->admin());

        $pay(4000);
        $this->assertSame('partially_paid', $invoice->fresh()->status);
        $this->assertSame(6000.0, $balances->balance($this->supplier())['balance']);

        $pay(6000);
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(0.0, $balances->balance($this->supplier())['balance']);
        $this->assertSame(0.0, $this->accountBalance(ChartOfAccount::CODE_ACCOUNTS_PAYABLE));
        $this->assertSame(0.0, $this->accountBalance(ChartOfAccount::CODE_GRNI));
        $this->assertLedgerBalanced();
    }

    public function test_overpayment_is_rejected(): void
    {
        [$order, $wh] = $this->approvedOrder();
        $receipt = $this->receipt($order, $wh, 10);
        app(GoodsReceiptService::class)->post($receipt, $this->admin());
        $this->postedInvoice($order, $receipt);

        $this->expectException(OverpaymentException::class);

        app(SupplierPaymentService::class)->record([
            'company_id' => $this->company()->id,
            'supplier_id' => $this->supplier()->id,
            'payment_date' => now()->toDateString(),
            'amount' => 10001,
            'method' => 'cash',
            'paid_from_account_id' => $this->account(ChartOfAccount::CODE_CASH)->id,
            'allocations' => [['purchase_invoice_id' => \App\Models\PurchaseInvoice::firstOrFail()->id, 'amount' => 10001]],
        ], $this->admin());
    }

    public function test_posted_invoice_cannot_be_posted_twice(): void
    {
        [$order, $wh] = $this->approvedOrder();
        $receipt = $this->receipt($order, $wh, 10);
        app(GoodsReceiptService::class)->post($receipt, $this->admin());
        $invoice = $this->postedInvoice($order, $receipt);

        $this->expectException(\App\Exceptions\Purchasing\InvalidStatusTransitionException::class);
        app(PurchaseInvoiceService::class)->post($invoice, $this->admin());
    }
}

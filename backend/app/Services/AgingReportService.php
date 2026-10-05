<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\Supplier;
use Carbon\Carbon;

/**
 * Receivables/Payables aging: buckets outstanding (posted/partially_paid)
 * invoices by days overdue, measured from due_date (falling back to
 * invoice_date when no due_date was set), grouped by customer/supplier.
 * "Outstanding" is read straight off invoice.total_amount - paid_amount —
 * the same remaining-due figure SalesInvoice/PurchaseInvoice already expose
 * — never a separately maintained balance.
 */
class AgingReportService
{
    private const OPEN_STATUSES = ['posted', 'partially_paid'];

    private const BUCKETS = ['current', '1_30', '31_60', '61_90', '90_plus'];

    /**
     * @return array{as_of_date: string, customers: array<int, array{customer: Customer, buckets: array<string, float>, total: float}>, totals: array<string, float>, grand_total: float}
     */
    public function receivablesAging(string $companyId, ?string $asOfDate = null): array
    {
        $asOf = $asOfDate ? Carbon::parse($asOfDate) : Carbon::today();

        $invoices = SalesInvoice::with('customer')
            ->where('company_id', $companyId)
            ->whereIn('status', self::OPEN_STATUSES)
            ->whereDate('invoice_date', '<=', $asOf)
            ->get()
            ->filter(fn (SalesInvoice $invoice) => $invoice->remaining_due > 0.0001);

        return $this->bucketBy($invoices, $asOf, fn (SalesInvoice $i) => $i->customer_id, fn (SalesInvoice $i) => $i->customer, 'customers');
    }

    /**
     * @return array{as_of_date: string, suppliers: array<int, array{supplier: Supplier, buckets: array<string, float>, total: float}>, totals: array<string, float>, grand_total: float}
     */
    public function payablesAging(string $companyId, ?string $asOfDate = null): array
    {
        $asOf = $asOfDate ? Carbon::parse($asOfDate) : Carbon::today();

        $invoices = PurchaseInvoice::with('supplier')
            ->where('company_id', $companyId)
            ->whereIn('status', self::OPEN_STATUSES)
            ->whereDate('invoice_date', '<=', $asOf)
            ->get()
            ->filter(fn (PurchaseInvoice $invoice) => $invoice->remaining_due > 0.0001);

        return $this->bucketBy($invoices, $asOf, fn (PurchaseInvoice $i) => $i->supplier_id, fn (PurchaseInvoice $i) => $i->supplier, 'suppliers');
    }

    /**
     * @param \Illuminate\Support\Collection<int, SalesInvoice|PurchaseInvoice> $invoices
     */
    private function bucketBy($invoices, Carbon $asOf, callable $partyIdOf, callable $partyOf, string $partyKey): array
    {
        $groups = [];
        $totals = array_fill_keys(self::BUCKETS, 0.0);
        $grandTotal = 0.0;

        foreach ($invoices as $invoice) {
            $partyId = $partyIdOf($invoice);
            $groups[$partyId] ??= [
                'party' => $partyOf($invoice),
                'buckets' => array_fill_keys(self::BUCKETS, 0.0),
                'total' => 0.0,
            ];

            // Unambiguous sign: positive means the due date is in the past
            // relative to $asOf (i.e. overdue), regardless of Carbon's
            // diffInDays() sign convention for non-absolute diffs.
            $dueDate = $invoice->due_date ?? $invoice->invoice_date;
            $daysOverdue = $dueDate->copy()->startOfDay()->diffInDays($asOf->copy()->startOfDay(), true)
                * ($asOf->copy()->startOfDay()->lt($dueDate->copy()->startOfDay()) ? -1 : 1);
            $bucket = match (true) {
                $daysOverdue <= 0 => 'current',
                $daysOverdue <= 30 => '1_30',
                $daysOverdue <= 60 => '31_60',
                $daysOverdue <= 90 => '61_90',
                default => '90_plus',
            };

            $amount = (float) $invoice->remaining_due;
            $groups[$partyId]['buckets'][$bucket] += $amount;
            $groups[$partyId]['total'] += $amount;
            $totals[$bucket] += $amount;
            $grandTotal += $amount;
        }

        $rows = array_values(array_map(fn ($group) => [
            $partyKey === 'customers' ? 'customer' : 'supplier' => $group['party'],
            'buckets' => $group['buckets'],
            'total' => $group['total'],
        ], $groups));

        return [
            'as_of_date' => $asOf->toDateString(),
            $partyKey => $rows,
            'totals' => $totals,
            'grand_total' => $grandTotal,
        ];
    }
}

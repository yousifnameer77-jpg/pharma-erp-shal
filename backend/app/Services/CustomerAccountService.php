<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;

/**
 * The "Customer Balance" the Sales workflow ends on — computed on demand,
 * never stored, mirroring SupplierAccountService: total invoiced minus total
 * returned minus total paid.
 */
class CustomerAccountService
{
    /**
     * @return array{total_invoiced: float, total_returned: float, total_paid: float, balance: float}
     */
    public function balance(Customer $customer): array
    {
        $invoiceTotals = SalesInvoice::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['posted', 'partially_paid', 'paid'])
            ->selectRaw('COALESCE(SUM(total_amount), 0) AS total_invoiced, COALESCE(SUM(paid_amount), 0) AS total_paid')
            ->first();

        $totalReturned = (float) SalesReturn::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'posted')
            ->sum('total_amount');

        $totalInvoiced = (float) $invoiceTotals->total_invoiced;
        $totalPaid = (float) $invoiceTotals->total_paid;

        return [
            'total_invoiced' => $totalInvoiced,
            'total_returned' => $totalReturned,
            'total_paid' => $totalPaid,
            'balance' => $totalInvoiced - $totalReturned - $totalPaid,
        ];
    }
}

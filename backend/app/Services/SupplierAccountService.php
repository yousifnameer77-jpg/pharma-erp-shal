<?php

namespace App\Services;

use App\Models\PurchaseInvoice;
use App\Models\Supplier;

/**
 * The "Supplier Balance" the workflow ends on — computed on demand from
 * posted invoices rather than stored, so it's never out of sync with the
 * invoices/payments that make it up.
 */
class SupplierAccountService
{
    /**
     * @return array{total_invoiced: float, total_paid: float, balance: float}
     */
    public function balance(Supplier $supplier): array
    {
        $totals = PurchaseInvoice::query()
            ->where('supplier_id', $supplier->id)
            ->whereIn('status', ['posted', 'partially_paid', 'paid'])
            ->selectRaw('COALESCE(SUM(total_amount), 0) AS total_invoiced, COALESCE(SUM(paid_amount), 0) AS total_paid')
            ->first();

        $totalInvoiced = (float) $totals->total_invoiced;
        $totalPaid = (float) $totals->total_paid;

        return [
            'total_invoiced' => $totalInvoiced,
            'total_paid' => $totalPaid,
            'balance' => $totalInvoiced - $totalPaid,
        ];
    }
}

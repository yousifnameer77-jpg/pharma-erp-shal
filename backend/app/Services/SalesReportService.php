<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\SalesInvoice;
use Illuminate\Support\Facades\DB;

/**
 * Sales activity reports — daily / monthly / by-branch breakdowns, all
 * computed on demand from `sales_invoices` (same "never store a report"
 * philosophy as the Accounting reports). Only `posted`, `partially_paid`
 * and `paid` invoices count as real sales: `draft` has no stock/accounting
 * effect yet, and `cancelled` is excluded from the customer's balance, so
 * both are left out of every figure here too.
 */
class SalesReportService
{
    private const REAL_SALE_STATUSES = ['posted', 'partially_paid', 'paid'];

    /** Every real-sale invoice on one date, plus the day's totals. */
    public function daily(string $companyId, string $date): array
    {
        $invoices = SalesInvoice::query()
            ->with(['customer', 'branch'])
            ->where('company_id', $companyId)
            ->whereIn('status', self::REAL_SALE_STATUSES)
            ->whereDate('invoice_date', $date)
            ->orderBy('invoice_number')
            ->get();

        return [
            'date' => $date,
            'invoices' => $invoices,
            'invoice_count' => $invoices->count(),
            'total_subtotal' => (float) $invoices->sum('subtotal'),
            'total_discount' => (float) $invoices->sum('discount_amount'),
            'total_tax' => (float) $invoices->sum('tax_amount'),
            'total_revenue' => (float) $invoices->sum('total_amount_base'),
        ];
    }

    /** Day-by-day breakdown for one calendar month. */
    public function monthly(string $companyId, int $year, int $month): array
    {
        $rows = SalesInvoice::query()
            ->where('company_id', $companyId)
            ->whereIn('status', self::REAL_SALE_STATUSES)
            ->whereYear('invoice_date', $year)
            ->whereMonth('invoice_date', $month)
            ->groupBy('invoice_date')
            ->orderBy('invoice_date')
            ->get([
                'invoice_date',
                DB::raw('COUNT(*) AS invoice_count'),
                DB::raw('COALESCE(SUM(total_amount_base), 0) AS total_revenue'),
            ]);

        $days = $rows->map(fn ($row) => [
            'date' => $row->invoice_date->toDateString(),
            'invoice_count' => (int) $row->invoice_count,
            'total_revenue' => (float) $row->total_revenue,
        ])->values();

        return [
            'year' => $year,
            'month' => $month,
            'days' => $days,
            'invoice_count' => (int) $days->sum('invoice_count'),
            'total_revenue' => (float) $days->sum('total_revenue'),
        ];
    }

    /** Totals grouped by branch over an optional date range (both ends inclusive; omit either for an open range). */
    public function byBranch(string $companyId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $rows = SalesInvoice::query()
            ->where('company_id', $companyId)
            ->whereIn('status', self::REAL_SALE_STATUSES)
            ->when($dateFrom, fn ($q) => $q->whereDate('invoice_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('invoice_date', '<=', $dateTo))
            ->groupBy('branch_id')
            ->get([
                'branch_id',
                DB::raw('COUNT(*) AS invoice_count'),
                DB::raw('COALESCE(SUM(total_amount_base), 0) AS total_revenue'),
            ]);

        // groupBy + a raw aggregate select can't eager-load via with(), so
        // the branch names are fetched separately and matched up here.
        $branches = Branch::whereIn('id', $rows->pluck('branch_id'))->get()->keyBy('id');

        $branchRows = $rows
            ->map(function ($row) use ($branches) {
                $branch = $branches->get($row->branch_id);

                return [
                    // Same minimal {id, name} shape used for every other
                    // embedded party (customer, supplier, ...) across the API.
                    'branch' => $branch ? ['id' => $branch->id, 'name' => $branch->name] : null,
                    'invoice_count' => (int) $row->invoice_count,
                    'total_revenue' => (float) $row->total_revenue,
                ];
            })
            ->sortByDesc('total_revenue')
            ->values();

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'branches' => $branchRows,
            'invoice_count' => (int) $branchRows->sum('invoice_count'),
            'total_revenue' => (float) $branchRows->sum('total_revenue'),
        ];
    }
}

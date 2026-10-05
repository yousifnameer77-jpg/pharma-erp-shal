<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Executive dashboard. Every figure is computed from real documents and the
 * general ledger, in IQD (mixed-currency invoices use their *_base columns).
 * Nothing here is estimated or padded: an empty system shows zeros.
 */
class ExecutiveAnalyticsController extends Controller
{
    private const REAL_STATUSES = ['posted', 'partially_paid', 'paid'];

    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $user?->branch?->company_id ?? \App\Models\Company::first()?->id ?? 'default';

        $data = Cache::remember('executive_analytics_'.$companyId, 30, fn () => $this->compute($companyId));

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    private function compute(string $companyId): array
    {
        $sales = SalesInvoice::where('company_id', $companyId)->whereIn('status', self::REAL_STATUSES);
        $totalSales = (float) (clone $sales)->sum('total_amount_base');
        $totalPaid = (float) (clone $sales)->sum('paid_amount_base');

        $purchases = PurchaseInvoice::where('company_id', $companyId)->whereIn('status', self::REAL_STATUSES);
        $outstandingPayables = max(0, (float) (clone $purchases)->sum('total_amount_base') - (float) (clone $purchases)->sum('paid_amount_base'));
        $outstandingReceivables = max(0, $totalSales - $totalPaid);

        // Profit comes from the ledger (revenue minus cost of goods sold), not an assumed margin.
        $ledger = $this->ledgerProfit($companyId);
        $grossProfit = $ledger['revenue'] - $ledger['cogs'];
        $profitMargin = $ledger['revenue'] > 0 ? round($grossProfit / $ledger['revenue'] * 100, 1) : 0.0;

        $lowStockCount = Product::query()
            ->withSum('stockRecords as total_stock_sum', 'quantity_on_hand')
            ->where('is_active', true)
            ->get()
            ->filter(fn (Product $p) => $p->is_below_min_stock)
            ->count();

        return [
            'kpis' => [
                'total_revenue' => $totalSales,
                'gross_profit' => $grossProfit,
                'profit_margin' => (float) $profitMargin,
                'receivables' => $outstandingReceivables,
                'payables' => $outstandingPayables,
                'low_stock_count' => $lowStockCount,
            ],
            'sales_trend' => $this->trend($companyId),
            'top_products' => $this->topProducts($companyId),
            'payment_split' => $this->paymentSplit($totalSales, $totalPaid),
        ];
    }

    /** @return array{revenue: float, cogs: float} */
    private function ledgerProfit(string $companyId, ?string $from = null, ?string $to = null): array
    {
        $rows = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('e.company_id', $companyId)
            ->whereIn('a.code', [ChartOfAccount::CODE_REVENUE, ChartOfAccount::CODE_COGS])
            ->when($from, fn ($q) => $q->whereDate('e.entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('e.entry_date', '<=', $to))
            ->groupBy('a.code')
            ->selectRaw('a.code, COALESCE(SUM(l.credit), 0) - COALESCE(SUM(l.debit), 0) AS net_credit')
            ->pluck('net_credit', 'code');

        return [
            'revenue' => (float) ($rows[ChartOfAccount::CODE_REVENUE] ?? 0),
            'cogs' => -(float) ($rows[ChartOfAccount::CODE_COGS] ?? 0),
        ];
    }

    private function trend(string $companyId): array
    {
        $trend = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);

            $daySales = (float) SalesInvoice::where('company_id', $companyId)
                ->whereIn('status', self::REAL_STATUSES)
                ->whereDate('invoice_date', $date)
                ->sum('total_amount_base');

            $day = $this->ledgerProfit($companyId, $date->toDateString(), $date->toDateString());

            $trend[] = [
                'day' => $date->format('M d'),
                'day_name' => $date->translatedFormat('D'),
                'sales' => $daySales,
                'profit' => $day['revenue'] - $day['cogs'],
            ];
        }

        return $trend;
    }

    /** Best sellers by actual invoiced revenue (IQD). */
    private function topProducts(string $companyId): array
    {
        return DB::table('sales_invoice_items as i')
            ->join('sales_invoices as s', 's.id', '=', 'i.sales_invoice_id')
            ->join('products as p', 'p.id', '=', 'i.product_id')
            ->where('s.company_id', $companyId)
            ->whereIn('s.status', self::REAL_STATUSES)
            ->groupBy('p.id', 'p.name', 'p.form', 'p.sale_price')
            ->orderByRaw('SUM(i.line_total * s.exchange_rate) DESC')
            ->limit(5)
            ->selectRaw('p.name, p.form, p.sale_price, COUNT(DISTINCT s.id) AS sales_count, ROUND(SUM(i.line_total * s.exchange_rate), 2) AS revenue')
            ->get()
            ->map(fn ($r) => [
                'name' => $r->name,
                'form' => $r->form,
                'sale_price' => (float) $r->sale_price,
                'sales_count' => (int) $r->sales_count,
                'revenue' => (float) $r->revenue,
            ])
            ->all();
    }

    /** Share of invoiced value already collected vs still owed. */
    private function paymentSplit(float $totalSales, float $totalPaid): array
    {
        if ($totalSales <= 0) {
            return [];
        }

        $collected = round(min($totalPaid, $totalSales) / $totalSales * 100, 1);

        return [
            ['name' => 'محصّل (Collected)', 'value' => $collected, 'color' => '#10B981'],
            ['name' => 'آجل / ذمم (Outstanding)', 'value' => round(100 - $collected, 1), 'color' => '#F59E0B'],
        ];
    }
}

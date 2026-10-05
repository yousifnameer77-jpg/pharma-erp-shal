<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `type` (asset/liability/equity/revenue/expense) is the accounting
 * classification; `category` is a finer, optional tag used only to group
 * accounts for the Cash / Banks / Receivables / Payables views (e.g. a
 * company might have three `bank` accounts and one `cash` account, all
 * `type = asset`) — see ChartOfAccount::CATEGORY_* and
 * AccountingReportController.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->string('category', 30)->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};

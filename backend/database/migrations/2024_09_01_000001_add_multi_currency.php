<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-currency: IQD is the base (books) currency, USD is the only foreign
 * currency. Documents (sales/purchase invoices and customer/supplier
 * payments) carry their own `currency` and the `exchange_rate` (IQD per 1
 * unit of that currency) in force on their date; the general ledger is
 * always posted in IQD. Rates are entered manually once a day.
 */
return new class extends Migration
{
    private array $documentTables = ['sales_invoices', 'purchase_invoices', 'customer_payments', 'supplier_payments'];

    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('currency', 3);
            $table->date('rate_date');
            // IQD per 1 unit of `currency`.
            $table->decimal('rate', 14, 4);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'currency', 'rate_date']);
        });

        DB::statement("ALTER TABLE exchange_rates ADD CONSTRAINT exchange_rates_currency_check CHECK (currency = 'USD')");
        DB::statement('ALTER TABLE exchange_rates ADD CONSTRAINT exchange_rates_rate_check CHECK (rate > 0)');

        foreach ($this->documentTables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('currency', 3)->default('IQD');
                $table->decimal('exchange_rate', 14, 4)->default(1);
            });

            DB::statement("ALTER TABLE {$name} ADD CONSTRAINT {$name}_currency_check CHECK (currency IN ('IQD', 'USD'))");
            DB::statement("ALTER TABLE {$name} ADD CONSTRAINT {$name}_rate_check CHECK (exchange_rate > 0 AND (currency <> 'IQD' OR exchange_rate = 1))");
        }

        // IQD-equivalent amounts so reports can add up mixed-currency invoices.
        foreach (['sales_invoices', 'purchase_invoices'] as $name) {
            DB::statement("ALTER TABLE {$name} ADD COLUMN total_amount_base numeric(18,3) GENERATED ALWAYS AS (round(total_amount * exchange_rate, 3)) STORED");
            DB::statement("ALTER TABLE {$name} ADD COLUMN paid_amount_base numeric(18,3) GENERATED ALWAYS AS (round(paid_amount * exchange_rate, 3)) STORED");
        }
    }

    public function down(): void
    {
        foreach (['sales_invoices', 'purchase_invoices'] as $name) {
            DB::statement("ALTER TABLE {$name} DROP COLUMN total_amount_base, DROP COLUMN paid_amount_base");
        }

        foreach ($this->documentTables as $name) {
            DB::statement("ALTER TABLE {$name} DROP CONSTRAINT {$name}_currency_check, DROP CONSTRAINT {$name}_rate_check");
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn(['currency', 'exchange_rate']);
            });
        }

        Schema::dropIfExists('exchange_rates');
    }
};

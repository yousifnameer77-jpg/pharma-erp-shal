<?php

use App\Http\Controllers\Api\AccountingReportController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\BatchController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ChartOfAccountController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CustomerPaymentController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\GoodsReceiptController;
use App\Http\Controllers\Api\JournalEntryController;
use App\Http\Controllers\Api\ManufacturerController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PurchaseInvoiceController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\PurchaseRequestController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SalesInvoiceController;
use App\Http\Controllers\Api\SalesReportController;
use App\Http\Controllers\Api\SalesReturnController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\SupplierPaymentController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\ExpiryRiskController;
use App\Http\Controllers\Api\ReorderController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Controllers\Api\V1\PosController;
use App\Http\Controllers\Api\V1\ControlledDrugController;
use App\Http\Controllers\Api\V1\ExecutiveAnalyticsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Foundation module: Auth, User Management, RBAC
|--------------------------------------------------------------------------
| Every route below /v1 (except login) requires a Sanctum bearer token.
| Business-module routes (inventory, sales, purchasing, finance, ...) are
| added the same way in their own route groups as those modules are built.
*/

Route::prefix('v1')->group(function () {

    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.view');
        Route::post('/users', [UserController::class, 'store'])->middleware('permission:users.manage');
        Route::get('/users/{user}', [UserController::class, 'show'])->middleware('permission:users.view');
        Route::put('/users/{user}', [UserController::class, 'update'])->middleware('permission:users.manage');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('permission:users.manage');
        Route::post('/users/{user}/roles', [UserController::class, 'assignRole'])->middleware('permission:users.manage');
        Route::delete('/users/{user}/roles/{userRoleId}', [UserController::class, 'revokeRole'])->middleware('permission:users.manage');

        Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:roles.view');
        Route::post('/roles', [RoleController::class, 'store'])->middleware('permission:roles.manage');
        Route::get('/roles/{role}', [RoleController::class, 'show'])->middleware('permission:roles.view');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.manage');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.manage');

        Route::get('/permissions', [PermissionController::class, 'index'])->middleware('permission:roles.view');

        // Branches are seeded, not created via the API (BranchController's docblock);
        // Warehouses are the unit this permission was reserved for from day one.
        Route::get('/branches', [BranchController::class, 'index'])->middleware('permission:branches.manage');
        Route::get('/branches/{branch}', [BranchController::class, 'show'])->middleware('permission:branches.manage');

        Route::get('/warehouses', [WarehouseController::class, 'index'])->middleware('permission:branches.manage');
        Route::post('/warehouses', [WarehouseController::class, 'store'])->middleware('permission:branches.manage');
        Route::get('/warehouses/{warehouse}', [WarehouseController::class, 'show'])->middleware('permission:branches.manage');
        Route::put('/warehouses/{warehouse}', [WarehouseController::class, 'update'])->middleware('permission:branches.manage');

        /*
        |----------------------------------------------------------------------
        | Inventory module: catalogue (categories, manufacturers, suppliers,
        | products), batches, on-hand stock, and the stock-movement ledger.
        |----------------------------------------------------------------------
        */

        Route::get('/categories', [CategoryController::class, 'index'])->middleware('permission:products.view');
        Route::post('/categories', [CategoryController::class, 'store'])->middleware('permission:products.manage');
        Route::get('/categories/{category}', [CategoryController::class, 'show'])->middleware('permission:products.view');
        Route::put('/categories/{category}', [CategoryController::class, 'update'])->middleware('permission:products.manage');
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->middleware('permission:products.manage');

        Route::get('/manufacturers', [ManufacturerController::class, 'index'])->middleware('permission:products.view');
        Route::post('/manufacturers', [ManufacturerController::class, 'store'])->middleware('permission:products.manage');
        Route::get('/manufacturers/{manufacturer}', [ManufacturerController::class, 'show'])->middleware('permission:products.view');
        Route::put('/manufacturers/{manufacturer}', [ManufacturerController::class, 'update'])->middleware('permission:products.manage');
        Route::delete('/manufacturers/{manufacturer}', [ManufacturerController::class, 'destroy'])->middleware('permission:products.manage');

        Route::get('/suppliers', [SupplierController::class, 'index'])->middleware('permission:products.view');
        Route::post('/suppliers', [SupplierController::class, 'store'])->middleware('permission:products.manage');
        Route::get('/suppliers/{supplier}', [SupplierController::class, 'show'])->middleware('permission:products.view');
        Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->middleware('permission:products.manage');
        Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->middleware('permission:products.manage');
        // "Supplier Balance": total posted invoices minus total paid — see SupplierAccountService.
        Route::get('/suppliers/{supplier}/balance', [SupplierController::class, 'balance'])->middleware('permission:purchasing.view');

        Route::get('/products/low-stock', [ProductController::class, 'lowStock'])->middleware('permission:products.view');
        Route::get('/products', [ProductController::class, 'index'])->middleware('permission:products.view');
        Route::post('/products', [ProductController::class, 'store'])->middleware('permission:products.manage');
        Route::get('/products/{product}', [ProductController::class, 'show'])->middleware('permission:products.view');
        Route::put('/products/{product}', [ProductController::class, 'update'])->middleware('permission:products.manage');
        Route::delete('/products/{product}', [ProductController::class, 'destroy'])->middleware('permission:products.manage');

        Route::get('/batches', [BatchController::class, 'index'])->middleware('permission:batches.view');
        // Creating a batch also receives its first stock quantity — see BatchService::receive().
        Route::post('/batches', [BatchController::class, 'store'])->middleware('permission:batches.manage');
        Route::get('/batches/{batch}', [BatchController::class, 'show'])->middleware('permission:batches.view');

        Route::get('/stock', [StockController::class, 'index'])->middleware('permission:inventory.view');

        // The stock-movements ledger is append-only: no PUT/PATCH/DELETE route
        // exists here on purpose (see StockMovementController's class docblock).
        Route::get('/stock-movements', [StockMovementController::class, 'index'])->middleware('permission:inventory.view');
        Route::get('/stock-movements/{stockMovement}', [StockMovementController::class, 'show'])->middleware('permission:inventory.view');
        Route::post('/stock-movements/sale', [StockMovementController::class, 'sell'])->middleware('permission:sales.create');
        Route::post('/stock-movements/transfer', [StockMovementController::class, 'transfer'])->middleware('permission:inventory.transfer.create');
        Route::post('/stock-movements/customer-return', [StockMovementController::class, 'customerReturn'])->middleware('permission:sales.create');
        Route::post('/stock-movements/supplier-return', [StockMovementController::class, 'supplierReturn'])->middleware('permission:purchasing.manage');
        Route::post('/stock-movements/damage', [StockMovementController::class, 'damage'])->middleware('permission:inventory.adjust');
        Route::post('/stock-movements/expired', [StockMovementController::class, 'expired'])->middleware('permission:inventory.adjust');
        Route::post('/stock-movements/adjustment', [StockMovementController::class, 'adjustment'])->middleware('permission:inventory.adjust');

        /*
        |----------------------------------------------------------------------
        | Purchasing module: Purchase Request -> Purchase Order -> Receive
        | Goods -> Invoice -> Supplier Balance. Ties into Inventory (goods
        | receipts post batches/stock via BatchService) and Accounting (goods
        | receipts, invoices and payments each post a journal entry).
        |----------------------------------------------------------------------
        */

        Route::get('/purchase-requests', [PurchaseRequestController::class, 'index'])->middleware('permission:purchasing.view');
        Route::post('/purchase-requests', [PurchaseRequestController::class, 'store'])->middleware('permission:purchasing.manage');
        Route::get('/purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'show'])->middleware('permission:purchasing.view');
        Route::put('/purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'update'])->middleware('permission:purchasing.manage');
        Route::post('/purchase-requests/{purchaseRequest}/submit', [PurchaseRequestController::class, 'submit'])->middleware('permission:purchasing.manage');
        Route::post('/purchase-requests/{purchaseRequest}/approve', [PurchaseRequestController::class, 'approve'])->middleware('permission:purchasing.approve');
        Route::post('/purchase-requests/{purchaseRequest}/reject', [PurchaseRequestController::class, 'reject'])->middleware('permission:purchasing.approve');
        Route::post('/purchase-requests/{purchaseRequest}/cancel', [PurchaseRequestController::class, 'cancel'])->middleware('permission:purchasing.manage');
        // Purchase Request -> Purchase Order.
        Route::post('/purchase-requests/{purchaseRequest}/convert-to-order', [PurchaseRequestController::class, 'convertToOrder'])->middleware('permission:purchasing.manage');

        Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->middleware('permission:purchasing.view');
        Route::post('/purchase-orders', [PurchaseOrderController::class, 'store'])->middleware('permission:purchasing.manage');
        Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->middleware('permission:purchasing.view');
        Route::put('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->middleware('permission:purchasing.manage');
        Route::post('/purchase-orders/{purchaseOrder}/submit', [PurchaseOrderController::class, 'submit'])->middleware('permission:purchasing.manage');
        // Approval is what unlocks receiving goods against this order.
        Route::post('/purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])->middleware('permission:purchasing.approve');
        Route::post('/purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->middleware('permission:purchasing.manage');

        // Receive Goods. No PUT/DELETE for a posted receipt — see GoodsReceiptController's docblock.
        Route::get('/goods-receipts', [GoodsReceiptController::class, 'index'])->middleware('permission:purchasing.view');
        Route::post('/goods-receipts', [GoodsReceiptController::class, 'store'])->middleware('permission:purchasing.manage');
        Route::get('/goods-receipts/{goodsReceipt}', [GoodsReceiptController::class, 'show'])->middleware('permission:purchasing.view');
        Route::post('/goods-receipts/{goodsReceipt}/post', [GoodsReceiptController::class, 'post'])->middleware('permission:purchasing.manage');

        // Invoice.
        Route::get('/purchase-invoices', [PurchaseInvoiceController::class, 'index'])->middleware('permission:purchasing.view');
        Route::post('/purchase-invoices', [PurchaseInvoiceController::class, 'store'])->middleware('permission:purchasing.manage');
        Route::get('/purchase-invoices/{purchaseInvoice}', [PurchaseInvoiceController::class, 'show'])->middleware('permission:purchasing.view');
        Route::put('/purchase-invoices/{purchaseInvoice}', [PurchaseInvoiceController::class, 'update'])->middleware('permission:purchasing.manage');
        Route::post('/purchase-invoices/{purchaseInvoice}/post', [PurchaseInvoiceController::class, 'post'])->middleware('permission:purchasing.manage');
        Route::post('/purchase-invoices/{purchaseInvoice}/cancel', [PurchaseInvoiceController::class, 'cancel'])->middleware('permission:purchasing.manage');

        // Payments settle the Supplier Balance. Append-only — no update/destroy route.
        Route::get('/supplier-payments', [SupplierPaymentController::class, 'index'])->middleware('permission:finance.journal.view');
        Route::post('/supplier-payments', [SupplierPaymentController::class, 'store'])->middleware('permission:finance.manage');
        Route::get('/supplier-payments/{supplierPayment}', [SupplierPaymentController::class, 'show'])->middleware('permission:finance.journal.view');

        /*
        |----------------------------------------------------------------------
        | Sales module: Customers, Sales Invoice (stock debited FEFO + journal
        | entry on post), Returns (reverses both), Payments, Customer Balance.
        |----------------------------------------------------------------------
        */

        Route::get('/customers', [CustomerController::class, 'index'])->middleware('permission:sales.view');
        Route::post('/customers', [CustomerController::class, 'store'])->middleware('permission:sales.manage');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->middleware('permission:sales.view');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->middleware('permission:sales.manage');
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->middleware('permission:sales.manage');
        // "Customer Balance": total posted invoices minus total returned minus total paid.
        Route::get('/customers/{customer}/balance', [CustomerController::class, 'balance'])->middleware('permission:sales.view');

        // Sales Invoice. No PUT/DELETE once posted — see SalesInvoiceService's guards.
        Route::get('/sales-invoices', [SalesInvoiceController::class, 'index'])->middleware('permission:sales.view');
        Route::post('/sales-invoices', [SalesInvoiceController::class, 'store'])->middleware('permission:sales.manage');
        Route::get('/sales-invoices/{salesInvoice}', [SalesInvoiceController::class, 'show'])->middleware('permission:sales.view');
        Route::put('/sales-invoices/{salesInvoice}', [SalesInvoiceController::class, 'update'])->middleware('permission:sales.manage');
        // Posting debits stock (FEFO) and posts the revenue/tax/receivable/COGS journal entry.
        Route::post('/sales-invoices/{salesInvoice}/post', [SalesInvoiceController::class, 'post'])->middleware('permission:sales.manage');
        Route::post('/sales-invoices/{salesInvoice}/cancel', [SalesInvoiceController::class, 'cancel'])->middleware('permission:sales.manage');

        // Returns — reverses stock (back into a named batch) and the sale's journal entry.
        Route::get('/sales-returns', [SalesReturnController::class, 'index'])->middleware('permission:sales.view');
        Route::post('/sales-returns', [SalesReturnController::class, 'store'])->middleware('permission:sales.manage');
        Route::get('/sales-returns/{salesReturn}', [SalesReturnController::class, 'show'])->middleware('permission:sales.view');
        Route::post('/sales-returns/{salesReturn}/post', [SalesReturnController::class, 'post'])->middleware('permission:sales.manage');
        Route::post('/sales-returns/{salesReturn}/cancel', [SalesReturnController::class, 'cancel'])->middleware('permission:sales.manage');

        // Payments settle the Customer Balance. Append-only — no update/destroy route.
        Route::get('/customer-payments', [CustomerPaymentController::class, 'index'])->middleware('permission:finance.journal.view');
        Route::post('/customer-payments', [CustomerPaymentController::class, 'store'])->middleware('permission:finance.manage');
        Route::get('/customer-payments/{customerPayment}', [CustomerPaymentController::class, 'show'])->middleware('permission:finance.journal.view');

        // Sales reports: daily / monthly / by-branch breakdowns of posted sales — see SalesReportService.
        Route::get('/reports/sales/daily', [SalesReportController::class, 'daily'])->middleware('permission:sales.view');
        Route::get('/reports/sales/monthly', [SalesReportController::class, 'monthly'])->middleware('permission:sales.view');
        Route::get('/reports/sales/by-branch', [SalesReportController::class, 'byBranch'])->middleware('permission:sales.view');

        /*
        |----------------------------------------------------------------------
        | Accounting module: chart of accounts + the append-only journal
        | ledger every posting action above writes to via JournalEntryService,
        | plus Expenses (the last thing that needed to auto-post) and the
        | General Ledger / Cash / Banks / Receivables / Payables / P&L /
        | Balance Sheet reports built on top of that ledger.
        |----------------------------------------------------------------------
        */

        Route::get('/chart-of-accounts', [ChartOfAccountController::class, 'index'])->middleware('permission:finance.journal.view');
        Route::post('/chart-of-accounts', [ChartOfAccountController::class, 'store'])->middleware('permission:finance.manage');
        Route::get('/chart-of-accounts/{chartOfAccount}', [ChartOfAccountController::class, 'show'])->middleware('permission:finance.journal.view');
        Route::put('/chart-of-accounts/{chartOfAccount}', [ChartOfAccountController::class, 'update'])->middleware('permission:finance.manage');

        // Read-only ledger, except store() for manual adjustment/opening-balance entries — see JournalEntryController's docblock.
        Route::get('/journal-entries', [JournalEntryController::class, 'index'])->middleware('permission:finance.journal.view');
        Route::post('/journal-entries', [JournalEntryController::class, 'store'])->middleware('permission:finance.manage');
        Route::get('/journal-entries/{journalEntry}', [JournalEntryController::class, 'show'])->middleware('permission:finance.journal.view');

        // Expenses: the fourth (and last) thing, alongside Sales/Purchases/Payments, that auto-posts a journal entry.
        Route::get('/expenses', [ExpenseController::class, 'index'])->middleware('permission:finance.journal.view');
        Route::post('/expenses', [ExpenseController::class, 'store'])->middleware('permission:finance.manage');
        Route::get('/expenses/{expense}', [ExpenseController::class, 'show'])->middleware('permission:finance.journal.view');
        Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])->middleware('permission:finance.manage');
        Route::post('/expenses/{expense}/post', [ExpenseController::class, 'post'])->middleware('permission:finance.manage');
        Route::post('/expenses/{expense}/cancel', [ExpenseController::class, 'cancel'])->middleware('permission:finance.manage');

        // Reports — all read-only, all computed on demand (see AccountingReportController's docblock).
        Route::get('/accounting/general-ledger', [AccountingReportController::class, 'generalLedger'])->middleware('permission:finance.journal.view');
        Route::get('/accounting/general-ledger/{chartOfAccount}', [AccountingReportController::class, 'accountLedger'])->middleware('permission:finance.journal.view');
        Route::get('/accounting/cash', [AccountingReportController::class, 'cash'])->middleware('permission:finance.journal.view');
        Route::get('/accounting/banks', [AccountingReportController::class, 'banks'])->middleware('permission:finance.journal.view');
        Route::get('/accounting/receivables', [AccountingReportController::class, 'receivables'])->middleware('permission:finance.journal.view');
        Route::get('/accounting/payables', [AccountingReportController::class, 'payables'])->middleware('permission:finance.journal.view');
        Route::get('/accounting/profit-and-loss', [AccountingReportController::class, 'profitAndLoss'])->middleware('permission:finance.journal.view');
        Route::get('/accounting/balance-sheet', [AccountingReportController::class, 'balanceSheet'])->middleware('permission:finance.journal.view');

        /*
        |----------------------------------------------------------------------
        | Expiry Risk & Quarantine Control
        |----------------------------------------------------------------------
        */
        Route::get('/inventory/expiry-risk', [ExpiryRiskController::class, 'index'])->middleware('permission:inventory.view');
        Route::post('/inventory/batches/{batch}/quarantine', [ExpiryRiskController::class, 'quarantine'])->middleware('permission:inventory.adjust');

        /*
        |----------------------------------------------------------------------
        | Auto Re-order Suggestions
        |----------------------------------------------------------------------
        */
        Route::get('/purchasing/reorder-suggestions', [ReorderController::class, 'suggestions'])->middleware('permission:purchasing.view');
        Route::post('/purchasing/reorder-suggestions/create-request', [ReorderController::class, 'createRequest'])->middleware('permission:purchasing.manage');

        /*
        |----------------------------------------------------------------------
        | Audit Logs & Governance
        |----------------------------------------------------------------------
        */
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->middleware('permission:users.view');
        Route::get('/audit-logs/{auditLog}', [AuditLogController::class, 'show'])->middleware('permission:users.view');

        /*
        |----------------------------------------------------------------------
        | Point of Sale (POS) & Multi-Tier Pricing
        |----------------------------------------------------------------------
        */
        Route::get('/pos/products', [PosController::class, 'products'])->middleware('permission:sales.create');
        Route::post('/pos/checkout', [PosController::class, 'checkout'])->middleware('permission:sales.create');
        Route::put('/pos/products/{id}/price', [PosController::class, 'updatePrice'])->middleware('permission:products.manage');

        /*
        |----------------------------------------------------------------------
        | Controlled Drugs & Prescription Compliance Registry
        |----------------------------------------------------------------------
        */
        Route::get('/controlled-drugs', [ControlledDrugController::class, 'index'])->middleware('permission:products.manage');

        /*
        |----------------------------------------------------------------------
        | Executive Financial & Sales Analytics
        |----------------------------------------------------------------------
        */
        Route::get('/analytics/executive', [ExecutiveAnalyticsController::class, 'dashboard']);
    });
});

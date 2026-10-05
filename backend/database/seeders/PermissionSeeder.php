<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['code' => 'users.view', 'module' => 'users', 'description' => 'View users'],
            ['code' => 'users.manage', 'module' => 'users', 'description' => 'Create/update/deactivate users and assign roles'],
            ['code' => 'roles.view', 'module' => 'roles', 'description' => 'View roles and the permission catalogue'],
            ['code' => 'roles.manage', 'module' => 'roles', 'description' => 'Create/update/delete roles'],
            ['code' => 'branches.manage', 'module' => 'org', 'description' => 'Manage branches and warehouses'],

            // Permission codes for modules built later reserve their place here so
            // RoleSeeder and the frontend permission picker have a stable catalogue
            // to work against from day one.
            ['code' => 'inventory.view', 'module' => 'inventory', 'description' => 'View stock levels and movements'],
            ['code' => 'inventory.transfer.create', 'module' => 'inventory', 'description' => 'Create stock transfer requests'],
            ['code' => 'inventory.transfer.approve', 'module' => 'inventory', 'description' => 'Approve stock transfers'],
            ['code' => 'inventory.adjust', 'module' => 'inventory', 'description' => 'Record damage/expired write-offs and manual stock adjustments'],
            ['code' => 'products.view', 'module' => 'inventory', 'description' => 'View products, categories, manufacturers and suppliers'],
            ['code' => 'products.manage', 'module' => 'inventory', 'description' => 'Create/update/deactivate products, categories, manufacturers and suppliers'],
            ['code' => 'batches.view', 'module' => 'inventory', 'description' => 'View batches and their expiry/stock detail'],
            ['code' => 'batches.manage', 'module' => 'inventory', 'description' => 'Receive stock by creating/topping up batches'],
            ['code' => 'sales.view', 'module' => 'sales', 'description' => 'View customers, sales invoices and returns'],
            ['code' => 'sales.create', 'module' => 'sales', 'description' => 'Record a stock sale/customer return directly via the Inventory stock-movements API'],
            ['code' => 'sales.manage', 'module' => 'sales', 'description' => 'Create/edit customers, sales invoices and returns, and post them'],
            ['code' => 'purchasing.view', 'module' => 'purchasing', 'description' => 'View purchase requests, orders, goods receipts and invoices'],
            ['code' => 'purchasing.manage', 'module' => 'purchasing', 'description' => 'Create/edit purchase requests, orders, goods receipts and invoices, and post them'],
            ['code' => 'purchasing.approve', 'module' => 'purchasing', 'description' => 'Approve purchase requests and purchase orders'],
            ['code' => 'finance.journal.view', 'module' => 'finance', 'description' => 'View the chart of accounts and journal entries'],
            ['code' => 'finance.manage', 'module' => 'finance', 'description' => 'Manage the chart of accounts and record supplier/customer payments'],
            ['code' => 'reports.view', 'module' => 'reports', 'description' => 'View reports and dashboards'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['code' => $permission['code']], $permission);
        }
    }
}

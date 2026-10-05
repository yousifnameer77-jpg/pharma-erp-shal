<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = Role::updateOrCreate(
            ['name' => 'Super Admin'],
            ['description' => 'Full access across all branches and warehouses', 'is_system_role' => true],
        );
        // User::hasPermission() already short-circuits to true for this role;
        // syncing every permission too keeps /roles/{id} honest for the UI.
        $superAdmin->permissions()->sync(Permission::pluck('id'));

        $branchManager = Role::updateOrCreate(
            ['name' => 'Branch Manager'],
            ['description' => 'Manages a single branch: staff, stock, sales, purchasing', 'is_system_role' => true],
        );
        $branchManager->permissions()->sync(
            Permission::whereIn('code', [
                'users.view', 'branches.manage',
                'inventory.view', 'inventory.transfer.approve', 'inventory.adjust',
                'products.view', 'products.manage',
                'batches.view', 'batches.manage',
                'sales.view', 'sales.create', 'sales.manage',
                'purchasing.view', 'purchasing.manage', 'purchasing.approve',
                'finance.journal.view', 'finance.manage',
                'reports.view',
            ])->pluck('id')
        );

        $warehouseKeeper = Role::updateOrCreate(
            ['name' => 'Warehouse Keeper'],
            ['description' => 'Manages stock for one warehouse', 'is_system_role' => true],
        );
        $warehouseKeeper->permissions()->sync(
            Permission::whereIn('code', [
                'inventory.view', 'inventory.transfer.create', 'inventory.adjust',
                'products.view',
                'batches.view', 'batches.manage',
                // Needs to see incoming orders and record what physically arrives —
                // not to approve orders, invoice, or pay suppliers.
                'purchasing.view', 'purchasing.manage',
            ])->pluck('id')
        );

        $cashier = Role::updateOrCreate(
            ['name' => 'Cashier'],
            ['description' => 'Point-of-sale operator', 'is_system_role' => true],
        );
        $cashier->permissions()->sync(
            Permission::whereIn('code', [
                'sales.create', 'sales.view', 'sales.manage', 'inventory.view',
                'products.view', 'batches.view',
                // Coarse-grained like Warehouse Keeper's purchasing grant above:
                // finance.manage also covers the chart of accounts, but recording
                // a customer payment at checkout is core cashier duty and there's
                // no finer-grained code for "payments only" yet. finance.journal.view
                // lets them see the payment record they just created.
                'finance.manage', 'finance.journal.view',
            ])->pluck('id')
        );

        $auditor = Role::updateOrCreate(
            ['name' => 'Auditor'],
            ['description' => 'Read-only access for compliance review', 'is_system_role' => true],
        );
        $auditor->permissions()->sync(
            Permission::where('code', 'like', '%.view')->pluck('id')
        );

        $accountant = Role::updateOrCreate(
            ['name' => 'Accountant'],
            ['description' => 'Financial accounting, journal entries, ledgers, and financial statements', 'is_system_role' => true],
        );
        $accountant->permissions()->sync(
            Permission::whereIn('code', [
                'finance.journal.view',
                'finance.manage',
            ])->pluck('id')
        );

        $warehouseEmployee = Role::updateOrCreate(
            ['name' => 'Warehouse Employee'],
            ['description' => 'Receiving, dispatching, inventory counting, and batch expiry tracking', 'is_system_role' => true],
        );
        $warehouseEmployee->permissions()->sync(
            Permission::whereIn('code', [
                'inventory.view', 'inventory.transfer.create', 'inventory.adjust',
                'products.view',
                'batches.view', 'batches.manage',
                'purchasing.view',
            ])->pluck('id')
        );
    }
}

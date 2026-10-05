<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
        ]);

        // Minimal bootstrap data so a freshly migrated environment has a
        // working login without hand-writing SQL. Safe to remove in production.
        $company = Company::firstOrCreate(
            ['tax_number' => '000000000'],
            ['name' => 'Demo Pharma Company', 'is_active' => true],
        );

        $branch = Branch::firstOrCreate(
            ['code' => 'HQ'],
            ['company_id' => $company->id, 'name' => 'Head Office', 'is_active' => true],
        );

        $admin = User::firstOrCreate(
            ['username' => 'admin'],
            [
                'branch_id' => $branch->id,
                'email' => 'admin@example.com',
                'password' => 'Passw0rd!',
                'full_name' => 'System Administrator',
                'is_active' => true,
            ],
        );

        UserRole::firstOrCreate([
            'user_id' => $admin->id,
            'role_id' => Role::where('name', 'Super Admin')->value('id'),
            'branch_id' => null,
            'warehouse_id' => null,
        ]);

        // Purchasing's posting logic (goods receipts, invoices, payments) depends
        // on every company having its default chart of accounts — see AccountingSeeder.
        $this->call([AccountingSeeder::class]);
    }
}

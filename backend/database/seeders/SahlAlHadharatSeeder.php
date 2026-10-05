<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Manufacturer;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\User;
use App\Models\UserRole;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SahlAlHadharatSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Permissions and Roles
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
        ]);

        $roleSuperAdmin = Role::where('name', 'Super Admin')->firstOrFail();
        $roleAccountant = Role::where('name', 'Accountant')->firstOrFail();
        $roleBranchManager = Role::where('name', 'Branch Manager')->firstOrFail();
        $roleWarehouseEmployee = Role::where('name', 'Warehouse Employee')->firstOrFail();

        // 2. Company: شركة سهل الحضارات للأدوية البيطرية
        $company = Company::updateOrCreate(
            ['tax_number' => 'VET-SAHL-2024'],
            [
                'name' => 'شركة سهل الحضارات للأدوية البيطرية',
                'is_active' => true,
            ]
        );

        // Also update any previous default company name so UI matches everywhere
        Company::where('id', '!=', $company->id)->update([
            'name' => 'شركة سهل الحضارات للأدوية البيطرية',
        ]);

        // 3. Chart of Accounts for Sahl Al-Hadharat
        AccountingSeeder::seedForCompany($company);

        // 4. Offices (Branches): Office A, Office B, Office C
        $officeA = Branch::updateOrCreate(
            ['code' => 'OFFICE-A'],
            [
                'company_id' => $company->id,
                'name' => 'Office A (مكتب A - بغداد)',
                'address' => 'بغداد - الكرخ / شارع الكندي',
                'phone' => '07701112233',
                'is_active' => true,
            ]
        );

        $officeB = Branch::updateOrCreate(
            ['code' => 'OFFICE-B'],
            [
                'company_id' => $company->id,
                'name' => 'Office B (مكتب B - البصرة)',
                'address' => 'البصرة - العشار / شارع الكويت',
                'phone' => '07801112233',
                'is_active' => true,
            ]
        );

        $officeC = Branch::updateOrCreate(
            ['code' => 'OFFICE-C'],
            [
                'company_id' => $company->id,
                'name' => 'Office C (مكتب C - أربيل)',
                'address' => 'أربيل - شارع 100 متري',
                'phone' => '07501112233',
                'is_active' => true,
            ]
        );

        // 5. Warehouses: Warehouse A, Warehouse B, Warehouse C
        $warehouseA = Warehouse::updateOrCreate(
            ['code' => 'WH-A'],
            [
                'branch_id' => $officeA->id,
                'name' => 'Warehouse A (مخزن A - بغداد)',
                'type' => 'main',
                'location' => 'مستودع الكرخ المركزي',
                'is_active' => true,
            ]
        );

        $warehouseB = Warehouse::updateOrCreate(
            ['code' => 'WH-B'],
            [
                'branch_id' => $officeB->id,
                'name' => 'Warehouse B (مخزن B - البصرة)',
                'type' => 'main',
                'location' => 'مستودع البصرة اللوجستي',
                'is_active' => true,
            ]
        );

        $warehouseC = Warehouse::updateOrCreate(
            ['code' => 'WH-C'],
            [
                'branch_id' => $officeC->id,
                'name' => 'Warehouse C (مخزن C - أربيل)',
                'type' => 'main',
                'location' => 'مستودع أربيل التجاري',
                'is_active' => true,
            ]
        );

        // 6. Users & Scoped RBAC
        $password = SeedPassword::resolve();

        // 6.1 Super Admin (المدير العام)
        $admin = User::updateOrCreate(
            ['username' => 'admin'],
            [
                'branch_id' => $officeA->id,
                'email' => 'admin@sahl-hadharat.com',
                'password' => $password,
                'full_name' => 'د. ياسين (المدير العام)',
                'phone' => '07700000001',
                'is_active' => true,
            ]
        );
        UserRole::updateOrCreate(
            ['user_id' => $admin->id, 'role_id' => $roleSuperAdmin->id],
            ['branch_id' => null, 'warehouse_id' => null]
        );

        // 6.2 Accountant (المحاسب المالي)
        $accountant = User::updateOrCreate(
            ['username' => 'accountant'],
            [
                'branch_id' => $officeA->id,
                'email' => 'finance@sahl-hadharat.com',
                'password' => $password,
                'full_name' => 'أ. أحمد (المحاسب المالي)',
                'phone' => '07700000002',
                'is_active' => true,
            ]
        );
        UserRole::updateOrCreate(
            ['user_id' => $accountant->id, 'role_id' => $roleAccountant->id],
            ['branch_id' => null, 'warehouse_id' => null]
        );

        // 6.3 Branch Managers (Office A, B, C)
        $managerA = User::updateOrCreate(
            ['username' => 'manager_a'],
            [
                'branch_id' => $officeA->id,
                'email' => 'manager.a@sahl-hadharat.com',
                'password' => $password,
                'full_name' => 'م. علي (مدير مكتب A)',
                'phone' => '07700000011',
                'is_active' => true,
            ]
        );
        UserRole::updateOrCreate(
            ['user_id' => $managerA->id, 'role_id' => $roleBranchManager->id],
            ['branch_id' => $officeA->id, 'warehouse_id' => $warehouseA->id]
        );

        $managerB = User::updateOrCreate(
            ['username' => 'manager_b'],
            [
                'branch_id' => $officeB->id,
                'email' => 'manager.b@sahl-hadharat.com',
                'password' => $password,
                'full_name' => 'م. حيدر (مدير مكتب B)',
                'phone' => '07800000022',
                'is_active' => true,
            ]
        );
        UserRole::updateOrCreate(
            ['user_id' => $managerB->id, 'role_id' => $roleBranchManager->id],
            ['branch_id' => $officeB->id, 'warehouse_id' => $warehouseB->id]
        );

        $managerC = User::updateOrCreate(
            ['username' => 'manager_c'],
            [
                'branch_id' => $officeC->id,
                'email' => 'manager.c@sahl-hadharat.com',
                'password' => $password,
                'full_name' => 'م. سركيس (مدير مكتب C)',
                'phone' => '07500000033',
                'is_active' => true,
            ]
        );
        UserRole::updateOrCreate(
            ['user_id' => $managerC->id, 'role_id' => $roleBranchManager->id],
            ['branch_id' => $officeC->id, 'warehouse_id' => $warehouseC->id]
        );

        // 6.4 Warehouse Employees (Warehouse A, B, C)
        $whEmpA = User::updateOrCreate(
            ['username' => 'warehouse_a'],
            [
                'branch_id' => $officeA->id,
                'email' => 'warehouse.a@sahl-hadharat.com',
                'password' => $password,
                'full_name' => 'عمار (أمين مخزن A)',
                'phone' => '07700000088',
                'is_active' => true,
            ]
        );
        UserRole::updateOrCreate(
            ['user_id' => $whEmpA->id, 'role_id' => $roleWarehouseEmployee->id],
            ['branch_id' => $officeA->id, 'warehouse_id' => $warehouseA->id]
        );

        $whEmpB = User::updateOrCreate(
            ['username' => 'warehouse_b'],
            [
                'branch_id' => $officeB->id,
                'email' => 'warehouse.b@sahl-hadharat.com',
                'password' => $password,
                'full_name' => 'كرار (أمين مخزن B)',
                'phone' => '07800000099',
                'is_active' => true,
            ]
        );
        UserRole::updateOrCreate(
            ['user_id' => $whEmpB->id, 'role_id' => $roleWarehouseEmployee->id],
            ['branch_id' => $officeB->id, 'warehouse_id' => $warehouseB->id]
        );

        // 7. Veterinary Categories
        $catAntibiotics = Category::firstOrCreate(['name' => 'مضادات حيوية بيطرية (Veterinary Antibiotics)']);
        $catParasites = Category::firstOrCreate(['name' => 'مضادات طفيليات وديدان (Antiparasitics & Dewormers)']);
        $catVitamins = Category::firstOrCreate(['name' => 'فيتامينات ومكملات غذائية وأحماض (Vitamins & Supplements)']);
        $catAntiInflammatory = Category::firstOrCreate(['name' => 'مضادات التهاب وخافضات حرارة (Anti-inflammatory & NSAIDs)']);
        $catDisinfectants = Category::firstOrCreate(['name' => 'مطهرات ومستلزمات وقاية بيطرية (Disinfectants & Antiseptics)']);

        // 8. Manufacturers
        $mfgNorbrook = Manufacturer::firstOrCreate(['name' => 'Norbrook Laboratories'], ['country' => 'United Kingdom', 'is_active' => true]);
        $mfgVetoquinol = Manufacturer::firstOrCreate(['name' => 'Vetoquinol'], ['country' => 'France', 'is_active' => true]);
        $mfgInterchemie = Manufacturer::firstOrCreate(['name' => 'Interchemie'], ['country' => 'Netherlands', 'is_active' => true]);
        $mfgCeva = Manufacturer::firstOrCreate(['name' => 'Ceva Santé Animale'], ['country' => 'France', 'is_active' => true]);
        $mfgZoetis = Manufacturer::firstOrCreate(['name' => 'Zoetis'], ['country' => 'USA', 'is_active' => true]);
        $mfgPioneer = Manufacturer::firstOrCreate(['name' => 'Pioneer Veterinary'], ['country' => 'Iraq', 'is_active' => true]);

        // 9. Suppliers
        $supAlMoroj = Supplier::firstOrCreate(
            ['company_id' => $company->id, 'name' => 'شركة المروج للاستيراد البيطري'],
            ['phone' => '07705551122', 'email' => 'almoroj.vet@example.com', 'is_active' => true]
        );
        $supRafidain = Supplier::firstOrCreate(
            ['company_id' => $company->id, 'name' => 'مذخر الرافدين الدوائي البيطري'],
            ['phone' => '07804443322', 'email' => 'rafidain.vet@example.com', 'is_active' => true]
        );

        // 10. Customers (Clinics & Livestock Farms)
        $custAlShifa = Customer::firstOrCreate(
            ['company_id' => $company->id, 'name' => 'عيادة الشفاء البيطرية - د. عمار'],
            ['phone' => '07708889900', 'address' => 'بغداد - الغزالية', 'is_active' => true]
        );
        $custBasraPoultry = Customer::firstOrCreate(
            ['company_id' => $company->id, 'name' => 'حقول دواجن النور - البصرة'],
            ['phone' => '07802223344', 'address' => 'البصرة - الزبير', 'is_active' => true]
        );
        $custDijlaCattle = Customer::firstOrCreate(
            ['company_id' => $company->id, 'name' => 'مشروع أبقار ومزرعة دجلة'],
            ['phone' => '07712345678', 'address' => 'واسط - الكوت', 'is_active' => true]
        );
        $custErbilVet = Customer::firstOrCreate(
            ['company_id' => $company->id, 'name' => 'عيادة أربيل النموذجية للخيول والحيوانات الكبيرة'],
            ['phone' => '07503332211', 'address' => 'أربيل - عينكاوة', 'is_active' => true]
        );

        // 11. Veterinary Products & Batches & Stocks
        $productsData = [
            [
                'name' => 'Oxytetracycline 20% L.A. (100ml)',
                'generic_name' => 'Oxytetracycline Base 200mg/ml',
                'code' => 'VET-OXY-20',
                'barcode' => '628100990001',
                'category_id' => $catAntibiotics->id,
                'manufacturer_id' => $mfgNorbrook->id,
                'form' => 'injectable',
                'strength' => '200mg/ml',
                'pack_size' => 100,
                'min_stock_level' => 30,
                'reorder_point' => 50,
                'purchase_price' => 7500,
                'wholesale_price' => 9500,
                'sale_price' => 11000,
                'batches' => [
                    [
                        'batch_number' => 'OXY-2024-B1',
                        'manufacture_date' => Carbon::now()->subMonths(3),
                        'expiry_date' => Carbon::now()->addMonths(18),
                        'supplier_id' => $supAlMoroj->id,
                        'purchase_price' => 7500,
                        'stocks' => [
                            $warehouseA->id => 120,
                            $warehouseB->id => 60,
                            $warehouseC->id => 40,
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Ivermectin 1% Super Injection (50ml)',
                'generic_name' => 'Ivermectin 10mg + Clorsulon 100mg',
                'code' => 'VET-IVM-01',
                'barcode' => '628100990002',
                'category_id' => $catParasites->id,
                'manufacturer_id' => $mfgVetoquinol->id,
                'form' => 'injectable',
                'strength' => '1%',
                'pack_size' => 50,
                'min_stock_level' => 40,
                'reorder_point' => 60,
                'purchase_price' => 6000,
                'wholesale_price' => 7500,
                'sale_price' => 9000,
                'batches' => [
                    [
                        'batch_number' => 'IVM-2024-V2',
                        'manufacture_date' => Carbon::now()->subMonths(2),
                        'expiry_date' => Carbon::now()->addMonths(14),
                        'supplier_id' => $supRafidain->id,
                        'purchase_price' => 6000,
                        'stocks' => [
                            $warehouseA->id => 150,
                            $warehouseB->id => 80,
                            $warehouseC->id => 50,
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Enrofloxacin 10% Oral Solution (1000ml)',
                'generic_name' => 'Enrofloxacin 100mg/ml',
                'code' => 'VET-ENR-10',
                'barcode' => '628100990003',
                'category_id' => $catAntibiotics->id,
                'manufacturer_id' => $mfgInterchemie->id,
                'form' => 'oral_liquid',
                'strength' => '10%',
                'pack_size' => 1000,
                'min_stock_level' => 20,
                'reorder_point' => 35,
                'purchase_price' => 14000,
                'wholesale_price' => 17500,
                'sale_price' => 20000,
                'batches' => [
                    [
                        'batch_number' => 'ENR-2023-EXP25',
                        'manufacture_date' => Carbon::now()->subMonths(11),
                        'expiry_date' => Carbon::now()->addDays(25), // Critical expiry alert!
                        'supplier_id' => $supAlMoroj->id,
                        'purchase_price' => 14000,
                        'stocks' => [
                            $warehouseA->id => 25,
                            $warehouseB->id => 10,
                        ],
                    ],
                    [
                        'batch_number' => 'ENR-2024-FRESH',
                        'manufacture_date' => Carbon::now()->subMonths(1),
                        'expiry_date' => Carbon::now()->addMonths(16),
                        'supplier_id' => $supAlMoroj->id,
                        'purchase_price' => 14000,
                        'stocks' => [
                            $warehouseA->id => 80,
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Tylosin Tartrate 20% Powder (100g)',
                'generic_name' => 'Tylosin Tartrate 200mg/g',
                'code' => 'VET-TYL-20',
                'barcode' => '628100990004',
                'category_id' => $catAntibiotics->id,
                'manufacturer_id' => $mfgCeva->id,
                'form' => 'powder',
                'strength' => '20%',
                'pack_size' => 100,
                'min_stock_level' => 25,
                'reorder_point' => 45,
                'purchase_price' => 5000,
                'wholesale_price' => 6500,
                'sale_price' => 7500,
                'batches' => [
                    [
                        'batch_number' => 'TYL-2024-C1',
                        'manufacture_date' => Carbon::now()->subMonths(4),
                        'expiry_date' => Carbon::now()->addMonths(20),
                        'supplier_id' => $supRafidain->id,
                        'purchase_price' => 5000,
                        'stocks' => [
                            $warehouseA->id => 90,
                            $warehouseB->id => 45,
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Amoxicillin 15% L.A. (100ml)',
                'generic_name' => 'Amoxicillin Trihydrate 150mg/ml',
                'code' => 'VET-AMX-15',
                'barcode' => '628100990005',
                'category_id' => $catAntibiotics->id,
                'manufacturer_id' => $mfgPioneer->id,
                'form' => 'injectable',
                'strength' => '150mg/ml',
                'pack_size' => 100,
                'min_stock_level' => 30,
                'reorder_point' => 50,
                'purchase_price' => 8000,
                'wholesale_price' => 10000,
                'sale_price' => 12000,
                'batches' => [
                    [
                        'batch_number' => 'AMX-2022-EXPIRED',
                        'manufacture_date' => Carbon::now()->subYears(2),
                        'expiry_date' => Carbon::now()->subDays(15), // Expired demo!
                        'supplier_id' => $supAlMoroj->id,
                        'purchase_price' => 8000,
                        'stocks' => [
                            $warehouseA->id => 15,
                        ],
                    ],
                    [
                        'batch_number' => 'AMX-2024-FRESH',
                        'manufacture_date' => Carbon::now()->subMonths(1),
                        'expiry_date' => Carbon::now()->addMonths(15),
                        'supplier_id' => $supAlMoroj->id,
                        'purchase_price' => 8000,
                        'stocks' => [
                            $warehouseA->id => 70,
                            $warehouseB->id => 50,
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Multivitamin + Amino Acids Oral (1000ml)',
                'generic_name' => 'Vit A, D3, E, K3, B-Complex + 18 Amino Acids',
                'code' => 'VET-VIT-AA',
                'barcode' => '628100990006',
                'category_id' => $catVitamins->id,
                'manufacturer_id' => $mfgInterchemie->id,
                'form' => 'oral_liquid',
                'strength' => 'Concentrated Solution',
                'pack_size' => 1000,
                'min_stock_level' => 30,
                'reorder_point' => 50,
                'purchase_price' => 12000,
                'wholesale_price' => 15000,
                'sale_price' => 18000,
                'batches' => [
                    [
                        'batch_number' => 'VIT-2024-I1',
                        'manufacture_date' => Carbon::now()->subMonths(2),
                        'expiry_date' => Carbon::now()->addMonths(12),
                        'supplier_id' => $supAlMoroj->id,
                        'purchase_price' => 12000,
                        'stocks' => [
                            $warehouseA->id => 110,
                            $warehouseC->id => 60,
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Flunixin Meglumine 5% Injection (100ml)',
                'generic_name' => 'Flunixin Meglumine 50mg/ml',
                'code' => 'VET-FLU-05',
                'barcode' => '628100990007',
                'category_id' => $catAntiInflammatory->id,
                'manufacturer_id' => $mfgNorbrook->id,
                'form' => 'injectable',
                'strength' => '50mg/ml',
                'pack_size' => 100,
                'min_stock_level' => 15,
                'reorder_point' => 30,
                'purchase_price' => 16000,
                'wholesale_price' => 20000,
                'sale_price' => 23000,
                'batches' => [
                    [
                        'batch_number' => 'FLU-2024-N1',
                        'manufacture_date' => Carbon::now()->subMonths(3),
                        'expiry_date' => Carbon::now()->addMonths(18),
                        'supplier_id' => $supRafidain->id,
                        'purchase_price' => 16000,
                        'stocks' => [
                            $warehouseA->id => 50,
                            $warehouseB->id => 30,
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Virkon-S Broad Spectrum Disinfectant (1kg)',
                'generic_name' => 'Potassium Peroxymonosulfate 50%',
                'code' => 'VET-VIR-01',
                'barcode' => '628100990008',
                'category_id' => $catDisinfectants->id,
                'manufacturer_id' => $mfgZoetis->id,
                'form' => 'powder',
                'strength' => 'Standard Granules',
                'pack_size' => 1000,
                'min_stock_level' => 10,
                'reorder_point' => 25,
                'purchase_price' => 22000,
                'wholesale_price' => 27000,
                'sale_price' => 30000,
                'batches' => [
                    [
                        'batch_number' => 'VIR-2024-Z1',
                        'manufacture_date' => Carbon::now()->subMonths(5),
                        'expiry_date' => Carbon::now()->addMonths(24),
                        'supplier_id' => $supAlMoroj->id,
                        'purchase_price' => 22000,
                        'stocks' => [
                            $warehouseA->id => 40,
                            $warehouseB->id => 20,
                            $warehouseC->id => 25,
                        ],
                    ],
                ],
            ],
        ];

        foreach ($productsData as $pData) {
            $batches = $pData['batches'];
            unset($pData['batches']);

            $product = Product::updateOrCreate(
                ['code' => $pData['code']],
                array_merge($pData, ['is_active' => true])
            );

            foreach ($batches as $bData) {
                $stocks = $bData['stocks'];
                unset($bData['stocks']);

                $batch = Batch::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'batch_number' => $bData['batch_number'],
                    ],
                    $bData
                );

                foreach ($stocks as $warehouseId => $qty) {
                    Stock::updateOrCreate(
                        [
                            'warehouse_id' => $warehouseId,
                            'batch_id' => $batch->id,
                        ],
                        [
                            'quantity_on_hand' => $qty,
                            'reserved_quantity' => 0,
                        ]
                    );
                }
            }
        }

        // 12. Audit Log record to establish official audit trail
        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'system_seed',
            'auditable_type' => Company::class,
            'auditable_id' => $company->id,
            'old_values' => null,
            'new_values' => [
                'company' => $company->name,
                'system' => 'Sahl Al-Hadharat Veterinary ERP Initialized',
                'offices' => 3,
                'warehouses' => 3,
                'users' => 7,
            ],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'SystemSeeder/1.0',
        ]);
    }
}


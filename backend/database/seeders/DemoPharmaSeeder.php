<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Company;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DemoPharmaSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        $branch = Branch::first();
        $admin = User::where('username', 'admin')->first();

        // 1. Warehouses
        $mainWarehouse = Warehouse::firstOrCreate(
            ['branch_id' => $branch->id, 'code' => 'MAIN-01'],
            ['name' => 'المستودع الرئيسي (Main Store)', 'type' => 'main', 'is_active' => true]
        );

        $quarantineWarehouse = Warehouse::firstOrCreate(
            ['branch_id' => $branch->id, 'code' => 'QRN-01'],
            ['name' => 'مستودع الحجر والتوالف (Quarantine)', 'type' => 'quarantine', 'is_active' => true]
        );

        // 2. Categories
        $catAntibiotics = Category::firstOrCreate(['name' => 'مضادات حيوية (Antibiotics)']);
        $catAnalgesics = Category::firstOrCreate(['name' => 'مسكنات وخافضات حرارة (Analgesics)']);
        $catVitamins = Category::firstOrCreate(['name' => 'فيتامينات ومكملات (Vitamins)']);
        $catGastro = Category::firstOrCreate(['name' => 'أدوية الجهاز الهضمي (Gastrointestinal)']);

        // 3. Manufacturers
        $mfgPfizer = Manufacturer::firstOrCreate(['name' => 'Pfizer Pharmaceuticals'], ['country' => 'USA', 'is_active' => true]);
        $mfgNovartis = Manufacturer::firstOrCreate(['name' => 'Novartis'], ['country' => 'Switzerland', 'is_active' => true]);
        $mfgPioneer = Manufacturer::firstOrCreate(['name' => 'Pioneer Pharma Iraq'], ['country' => 'Iraq', 'is_active' => true]);

        // 4. Suppliers
        $supRafidain = Supplier::firstOrCreate(
            ['company_id' => $company->id, 'name' => 'مذخر الرافدين الدوائي'],
            ['phone' => '07701234567', 'email' => 'rafidain@example.com', 'is_active' => true]
        );
        $supBabylon = Supplier::firstOrCreate(
            ['company_id' => $company->id, 'name' => 'شركة بابل للمستلزمات الطبية'],
            ['phone' => '07809876543', 'email' => 'babylon@example.com', 'is_active' => true]
        );

        // 5. Products
        $productsData = [
            [
                'name' => 'Panadol Extra 500mg',
                'generic_name' => 'Paracetamol + Caffeine',
                'code' => 'PAN-EXT-500',
                'barcode' => '6281001234567',
                'category_id' => $catAnalgesics->id,
                'manufacturer_id' => $mfgPfizer->id,
                'form' => 'tablet',
                'strength' => '500mg',
                'min_stock_level' => 30,
                'reorder_point' => 50,
                'purchase_price' => 1500,
                'sale_price' => 2500,
                'expiry_days' => 20, // Critical <= 30 days
                'stock_qty' => 45,
            ],
            [
                'name' => 'Amoxil 500mg Capsules',
                'generic_name' => 'Amoxicillin Trihydrate',
                'code' => 'AMX-500-CAP',
                'barcode' => '6281001234568',
                'category_id' => $catAntibiotics->id,
                'manufacturer_id' => $mfgPioneer->id,
                'form' => 'capsule',
                'strength' => '500mg',
                'min_stock_level' => 40,
                'reorder_point' => 60,
                'purchase_price' => 3000,
                'sale_price' => 4500,
                'expiry_days' => -15, // Expired
                'stock_qty' => 12,
            ],
            [
                'name' => 'Augmentin 1g Tablets',
                'generic_name' => 'Amoxicillin + Clavulanic Acid',
                'code' => 'AUG-1G-TAB',
                'barcode' => '6281001234569',
                'category_id' => $catAntibiotics->id,
                'manufacturer_id' => $mfgNovartis->id,
                'form' => 'tablet',
                'strength' => '1000mg',
                'min_stock_level' => 50,
                'reorder_point' => 80,
                'purchase_price' => 6500,
                'sale_price' => 9000,
                'expiry_days' => 75, // Warning <= 90 days
                'stock_qty' => 18, // Below min stock -> Needs Reorder!
            ],
            [
                'name' => 'Vitamin D3 50,000 IU',
                'generic_name' => 'Cholecalciferol',
                'code' => 'VIT-D3-50K',
                'barcode' => '6281001234570',
                'category_id' => $catVitamins->id,
                'manufacturer_id' => $mfgPfizer->id,
                'form' => 'capsule',
                'strength' => '50000 IU',
                'min_stock_level' => 20,
                'reorder_point' => 40,
                'purchase_price' => 4000,
                'sale_price' => 6000,
                'expiry_days' => 140, // Notice <= 180 days
                'stock_qty' => 8, // Below min stock -> Needs Reorder!
            ],
            [
                'name' => 'Losec 20mg Capsules',
                'generic_name' => 'Omeprazole',
                'code' => 'LOS-20-CAP',
                'barcode' => '6281001234571',
                'category_id' => $catGastro->id,
                'manufacturer_id' => $mfgPioneer->id,
                'form' => 'capsule',
                'strength' => '20mg',
                'min_stock_level' => 25,
                'reorder_point' => 45,
                'purchase_price' => 2200,
                'sale_price' => 3500,
                'expiry_days' => 365, // Safe
                'stock_qty' => 5, // Severely below min stock -> Needs Reorder!
            ],
        ];

        foreach ($productsData as $data) {
            $product = Product::firstOrCreate(
                ['code' => $data['code']],
                [
                    'name' => $data['name'],
                    'generic_name' => $data['generic_name'],
                    'barcode' => $data['barcode'],
                    'category_id' => $data['category_id'],
                    'manufacturer_id' => $data['manufacturer_id'],
                    'form' => $data['form'],
                    'strength' => $data['strength'],
                    'min_stock_level' => $data['min_stock_level'],
                    'reorder_point' => $data['reorder_point'],
                    'purchase_price' => $data['purchase_price'],
                    'sale_price' => $data['sale_price'],
                    'is_active' => true,
                ]
            );

            $batchNumber = 'BAT-' . date('Ym') . '-' . substr($product->code, 0, 4);
            $expiryDate = Carbon::today()->addDays($data['expiry_days']);

            $batch = Batch::firstOrCreate(
                ['product_id' => $product->id, 'batch_number' => $batchNumber],
                [
                    'supplier_id' => $supRafidain->id,
                    'expiry_date' => $expiryDate,
                    'manufacture_date' => Carbon::today()->subMonths(6),
                    'purchase_price' => $data['purchase_price'],
                ]
            );

            Stock::firstOrCreate(
                ['warehouse_id' => $mainWarehouse->id, 'batch_id' => $batch->id],
                [
                    'quantity_on_hand' => $data['stock_qty'],
                    'reserved_quantity' => 0,
                ]
            );
        }

        // 6. Sample Audit Logs
        AuditLog::create([
            'user_id' => $admin?->id,
            'action' => 'login',
            'auditable_type' => 'App\Models\User',
            'auditable_id' => $admin?->id,
            'new_values' => ['ip' => '127.0.0.1', 'status' => 'success'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0',
            'created_at' => Carbon::now()->subHours(2),
        ]);

        AuditLog::create([
            'user_id' => $admin?->id,
            'action' => 'created',
            'auditable_type' => 'App\Models\Product',
            'auditable_id' => Product::first()->id,
            'new_values' => ['name' => 'Panadol Extra 500mg', 'sale_price' => 2500],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0',
            'created_at' => Carbon::now()->subHour(),
        ]);

        AuditLog::create([
            'user_id' => $admin?->id,
            'action' => 'posted',
            'auditable_type' => 'App\Models\GoodsReceipt',
            'auditable_id' => 'gr-sample-01',
            'new_values' => ['receipt_number' => 'GR-000001', 'total_items' => 5],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0',
            'created_at' => Carbon::now()->subMinutes(30),
        ]);
    }
}


<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Obat;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PharmacyMasterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Suppliers
        DB::table('suppliers')->insertOrIgnore([
            [
                'code' => 'SUP-001',
                'name' => 'PT Kimia Farma Trading',
                'contact_person' => 'Budi Santoso',
                'phone' => '021-5551234',
                'email' => 'sales@kimiafarma.co.id',
                'address' => 'Jl. Veteran No. 9, Jakarta Pusat',
                'npwp' => '01.234.567.8-012.000',
                'bank_name' => 'BCA',
                'bank_account' => '1234567890',
                'payment_terms_days' => 30,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'SUP-002',
                'name' => 'PT Kalbe Farma Tbk',
                'contact_person' => 'Siti Rahma',
                'phone' => '021-8889999',
                'email' => 'order@kalbe.co.id',
                'address' => 'Kawasan Industri Pulogadung, Jakarta',
                'npwp' => '01.987.654.3-043.000',
                'bank_name' => 'Mandiri',
                'bank_account' => '0987654321',
                'payment_terms_days' => 14,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);

        // 2. Customers
        DB::table('customers')->insertOrIgnore([
            [
                'code' => 'CUST-001',
                'name' => 'Ahmad Subagyo',
                'gender' => 'L',
                'date_of_birth' => '1988-05-12',
                'phone' => '081299887766',
                'email' => 'ahmad@gmail.com',
                'address' => 'Jl. Merdeka No. 45, Bandung',
                'medical_notes' => 'Riwayat hipertensi',
                'allergies' => 'Alergi Penisilin',
                'membership_level' => 'silver',
                'points' => 120,
                'total_spending' => 650000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'CUST-002',
                'name' => 'Dewi Lestari',
                'gender' => 'P',
                'date_of_birth' => '1995-11-23',
                'phone' => '085711223344',
                'email' => 'dewi@gmail.com',
                'address' => 'Jl. Mawar No. 12, Jakarta',
                'medical_notes' => 'Sehat',
                'allergies' => 'Tidak ada',
                'membership_level' => 'gold',
                'points' => 350,
                'total_spending' => 1850000,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);

        // 3. Doctors
        DB::table('doctors')->insertOrIgnore([
            [
                'code' => 'DOC-001',
                'name' => 'dr. Hendra Wijaya, Sp.PD',
                'license_number' => 'SIP/503/001/2024',
                'specialty' => 'Spesialis Penyakit Dalam',
                'phone' => '081122334455',
                'email' => 'dr.hendra@hospital.com',
                'address' => 'RS Medika Sejahtera, Jakarta',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'DOC-002',
                'name' => 'dr. Siska Putri, Sp.A',
                'license_number' => 'SIP/503/002/2024',
                'specialty' => 'Spesialis Anak',
                'phone' => '081166778899',
                'email' => 'dr.siska@pediatric.com',
                'address' => 'Klinik Ibu & Anak Ceria',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);

        // 4. Categories & Units
        DB::table('medicine_categories')->insertOrIgnore([
            ['name' => 'Antibiotik', 'slug' => 'antibiotik', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Antipiretik', 'slug' => 'antipiretik', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Analgesik', 'slug' => 'analgesik', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Vitamin', 'slug' => 'vitamin', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('medicine_units')->insertOrIgnore([
            ['name' => 'Tablet', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Strip', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Botol', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Ampul', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 5. Dummy Medicines with FEFO Batches
        $obats = [
            [
                'kode' => 'MED-001',
                'nama' => 'Paracetamol 500mg',
                'gambar' => 'paracetamol.jpg',
                'jenis_obat' => 'Tablet',
                'kategori' => 'Antipiretik',
                'harga' => 12000.00,
                'stok' => 150,
            ],
            [
                'kode' => 'MED-002',
                'nama' => 'Amoxicillin 500mg',
                'gambar' => 'amoxicillin.jpg',
                'jenis_obat' => 'Kapsul',
                'kategori' => 'Antibiotik',
                'harga' => 25000.00,
                'stok' => 80,
            ],
            [
                'kode' => 'MED-003',
                'nama' => 'Vitamin C 1000mg',
                'gambar' => 'vitaminc.jpg',
                'jenis_obat' => 'Tablet',
                'kategori' => 'Vitamin',
                'harga' => 45000.00,
                'stok' => 200,
            ]
        ];

        foreach ($obats as $o) {
            Obat::updateOrCreate(['kode' => $o['kode']], $o);
        }

        // FEFO Batches for MED-001 (Paracetamol)
        DB::table('medicine_batches')->insertOrIgnore([
            [
                'medicine_id' => 'MED-001',
                'batch_number' => 'PCT-2026-A',
                'expired_date' => Carbon::now()->addMonths(3)->toDateString(), // FEFO First Out
                'stock' => 50,
                'buy_price' => 8000.00,
                'sell_price' => 12000.00,
                'supplier_id' => 1,
                'received_date' => Carbon::now()->subDays(10)->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'medicine_id' => 'MED-001',
                'batch_number' => 'PCT-2027-B',
                'expired_date' => Carbon::now()->addYear()->toDateString(),
                'stock' => 100,
                'buy_price' => 8500.00,
                'sell_price' => 12000.00,
                'supplier_id' => 1,
                'received_date' => Carbon::now()->subDays(2)->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'medicine_id' => 'MED-002',
                'batch_number' => 'AMX-2026-X',
                'expired_date' => Carbon::now()->addDays(20)->toDateString(), // Warning Expired < 30 days!
                'stock' => 80,
                'buy_price' => 18000.00,
                'sell_price' => 25000.00,
                'supplier_id' => 2,
                'received_date' => Carbon::now()->subDays(15)->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}

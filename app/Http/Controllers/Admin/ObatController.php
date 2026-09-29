<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Obat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

use Inertia\Inertia;

class ObatController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $conversionService = app(\App\Services\UnitConversionService::class);

        $obats = Obat::with(['supplier', 'satuanDasar', 'satuanPembelian', 'satuanPenjualan', 'prices.unit'])
            ->when($search, function ($query, $search) {
                return $query->where('nama', 'like', "%{$search}%")
                             ->orWhere('kode', 'like', "%{$search}%")
                             ->orWhere('merk', 'like', "%{$search}%")
                             ->orWhere('supplier_name', 'like', "%{$search}%");
            })
            ->paginate(10)
            ->withQueryString();

        // Attach formatted prices & margins per product
        $obats->getCollection()->transform(function ($product) use ($conversionService) {
            $priceData = $conversionService->getCalculatedPrices($product->kode);
            
            $purchasePriceInfo = '-';
            $sellingPriceInfo = '-';
            $marginInfo = '-';

            if (!empty($priceData['unit_prices'])) {
                // Default purchase unit price info
                $purchaseUnitObj = collect($priceData['unit_prices'])->firstWhere('is_purchase_unit', true) 
                    ?? collect($priceData['unit_prices'])->first();

                // Default selling unit price info
                $sellingUnitObj = collect($priceData['unit_prices'])->firstWhere('is_selling_unit', true) 
                    ?? collect($priceData['unit_prices'])->last();

                if ($purchaseUnitObj) {
                    $purchasePriceInfo = $purchaseUnitObj['purchase_price_formatted'];
                }
                if ($sellingUnitObj) {
                    $sellingPriceInfo = $sellingUnitObj['selling_price_formatted'];
                    $marginInfo = $sellingUnitObj['margin_formatted'] . ' (' . $sellingUnitObj['margin_percent'] . '%)';
                }
            }

            $product->purchase_price_formatted = $purchasePriceInfo;
            $product->selling_price_formatted = $sellingPriceInfo;
            $product->margin_formatted = $marginInfo;
            $product->price_details = $priceData;

            return $product;
        });

        $suppliers = \App\Models\Supplier::select('id', 'name', 'code')->get();
        $units = \App\Models\Unit::where('is_active', true)->get();

        return Inertia::render('Admin/Obat/Index', [
            'obats' => $obats,
            'suppliers' => $suppliers,
            'units' => $units,
            'filters' => ['search' => $search]
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Not used as we use modal
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (empty($request->kode)) {
            $request->merge(['kode' => 'OBT-' . rand(1000, 9999)]);
        }

        $request->validate([
            'kode' => 'required|unique:obats,kode|max:10',
            'nama' => 'required|max:100',
            'gambar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            'jenis_obat' => 'required',
            'kategori' => 'required',
            'harga' => 'required|numeric',
            'stok' => 'nullable|integer|min:0',
            'min_stok' => 'nullable|integer|min:0',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'supplier_name' => 'nullable|string|max:150',
            'merk' => 'nullable|string|max:100',
            'satuan_dasar_id' => 'nullable|exists:units,id',
            'satuan_pembelian_id' => 'nullable|exists:units,id',
            'satuan_penjualan_id' => 'nullable|exists:units,id',
        ]);

        $data = $request->all();
        $data['stok'] = $request->input('stok', 0);
        $data['min_stok'] = $request->input('min_stok', 10);

        if ($request->supplier_id && empty($data['supplier_name'])) {
            $sup = \App\Models\Supplier::find($request->supplier_id);
            if ($sup) $data['supplier_name'] = $sup->name;
        }

        if ($request->hasFile('gambar')) {
            $imageName = time().'.'.$request->gambar->extension();  
            $request->gambar->move(public_path('Assets/Obat'), $imageName);
            $data['gambar'] = $imageName;
        }

        $obat = Obat::create($data);

        // Auto-link primary units to product_units & product_prices
        $unitIds = array_filter(array_unique([
            $request->satuan_dasar_id,
            $request->satuan_pembelian_id,
            $request->satuan_penjualan_id,
        ]));

        foreach ($unitIds as $uId) {
            \App\Models\ProductUnit::firstOrCreate(
                ['product_id' => $obat->kode, 'unit_id' => $uId],
                [
                    'is_base_unit' => ($uId == $request->satuan_dasar_id),
                    'is_purchase_unit' => ($uId == $request->satuan_pembelian_id),
                    'is_selling_unit' => ($uId == $request->satuan_penjualan_id),
                    'conversion_factor' => 1.0000,
                ]
            );

            // Default selling price
            \App\Models\ProductPrice::firstOrCreate(
                ['product_id' => $obat->kode, 'unit_id' => $uId, 'price_type' => 'SELLING'],
                ['price' => $request->harga, 'created_by' => auth()->id()]
            );

            // Default purchase price
            \App\Models\ProductPrice::firstOrCreate(
                ['product_id' => $obat->kode, 'unit_id' => $uId, 'price_type' => 'PURCHASE'],
                ['price' => $request->harga * 0.8, 'created_by' => auth()->id()]
            );
        }

        return redirect()->route('admin.obat.index')->with('success', 'Obat berhasil ditambahkan!');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $obat = Obat::findOrFail($id);

        $request->validate([
            'nama' => 'required|max:100',
            'gambar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'jenis_obat' => 'required',
            'kategori' => 'required',
            'harga' => 'required|numeric',
            'stok' => 'nullable|integer|min:0',
            'min_stok' => 'nullable|integer|min:0',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'supplier_name' => 'nullable|string|max:150',
            'merk' => 'nullable|string|max:100',
            'satuan_dasar_id' => 'nullable|exists:units,id',
            'satuan_pembelian_id' => 'nullable|exists:units,id',
            'satuan_penjualan_id' => 'nullable|exists:units,id',
        ]);

        $data = $request->all();
        if ($request->has('min_stok')) {
            $data['min_stok'] = $request->input('min_stok', 10);
        }

        if ($request->supplier_id && empty($data['supplier_name'])) {
            $sup = \App\Models\Supplier::find($request->supplier_id);
            if ($sup) $data['supplier_name'] = $sup->name;
        }

        if ($request->hasFile('gambar')) {
            $imageName = time().'.'.$request->gambar->extension();  
            $request->gambar->move(public_path('Assets/Obat'), $imageName);
            $data['gambar'] = $imageName;
        }

        $obat->update($data);

        // Ensure product units exist
        $unitIds = array_filter(array_unique([
            $request->satuan_dasar_id,
            $request->satuan_pembelian_id,
            $request->satuan_penjualan_id,
        ]));

        foreach ($unitIds as $uId) {
            \App\Models\ProductUnit::firstOrCreate(
                ['product_id' => $obat->kode, 'unit_id' => $uId],
                [
                    'is_base_unit' => ($uId == $request->satuan_dasar_id),
                    'is_purchase_unit' => ($uId == $request->satuan_pembelian_id),
                    'is_selling_unit' => ($uId == $request->satuan_penjualan_id),
                    'conversion_factor' => 1.0000,
                ]
            );
        }

        return redirect()->route('admin.obat.index')->with('success', 'Obat berhasil diupdate!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $obat = Obat::findOrFail($id);
        $obat->update(['deleted_by' => auth()->id()]);
        $obat->delete();

        app(\App\Services\AuditLogService::class)->log(
            'DELETE',
            'Produk',
            ['kode' => $obat->kode, 'nama' => $obat->nama],
            ['status' => 'SOFT_DELETED']
        );

        return redirect()->route('admin.obat.index')->with('success', 'Obat berhasil di-soft delete! Data dapat dipulihkan melalui menu Trash.');
    }

    /**
     * Download template CSV for import master obat.
     */
    public function downloadTemplate()
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="template_import_obat.csv"',
        ];

        $callback = function () {
            $file = fopen('php://output', 'w');
            // Write CSV Header
            fputcsv($file, ['kode', 'nama', 'jenis_obat', 'kategori', 'harga', 'min_stok', 'merk', 'supplier_name']);
            // Write Sample Rows
            fputcsv($file, ['OBT-1001', 'Amoxicillin 500mg', 'Kapsul', 'Antibiotik', '25000', '10', 'Kalbe', 'PT Kalbe Farma']);
            fputcsv($file, ['OBT-1002', 'Bodrex Extra', 'Tablet', 'Analgesik', '8000', '15', 'Tempo Scan', 'PT Tempo Scan']);
            fputcsv($file, ['OBT-1003', 'Promag Tablet', 'Tablet', 'Antasida', '10000', '20', 'Kalbe', 'PT Kalbe Farma']);
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Import Master Obat from CSV or Excel CSV.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240',
            'mode' => 'nullable|in:update,skip',
        ]);

        $mode = $request->input('mode', 'update');
        $file = $request->file('file');

        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['csv', 'txt', 'xls', 'xlsx'])) {
            return redirect()->back()->withErrors(['error' => 'Format file tidak didukung. Harap gunakan file .csv']);
        }

        $filePath = $file->getRealPath();
        $sample = file_get_contents($filePath, false, null, 0, 4096);
        if (!$sample) {
            return redirect()->back()->withErrors(['error' => 'File kosomg atau tidak dapat dibaca.']);
        }

        // Auto-detect delimiter
        $delimiter = ',';
        if (substr_count($sample, ';') > substr_count($sample, ',')) {
            $delimiter = ';';
        } elseif (substr_count($sample, "\t") > substr_count($sample, ',')) {
            $delimiter = "\t";
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return redirect()->back()->withErrors(['error' => 'Gagal membuka file.']);
        }

        $header = fgetcsv($handle, 2000, $delimiter);
        if (!$header) {
            fclose($handle);
            return redirect()->back()->withErrors(['error' => 'File CSV kosong atau format header tidak valid.']);
        }

        // Clean header BOM, whitespace, and replace spaces with underscores
        $cleanHeader = array_map(function ($h) {
            $h = strtolower(trim(preg_replace('/[\x00-\x1F\x7F\xEF\xBB\xBF]/', '', $h)));
            $h = str_replace(['"', "'"], '', $h);
            return str_replace([' ', '-'], '_', $h);
        }, $header);

        $getValue = function($rowMap, $aliases, $default = '') {
            foreach ($aliases as $alias) {
                if (isset($rowMap[$alias]) && trim((string)$rowMap[$alias]) !== '') {
                    return trim((string)$rowMap[$alias]);
                }
            }
            return $default;
        };

        $insertedCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;

        // Fetch or create default unit
        $defaultUnit = \App\Models\Unit::firstOrCreate(['name' => 'Tablet'], ['is_active' => true]);
        $defaultUnitId = $defaultUnit->id;
        $userId = auth()->id();
        $now = now();

        $rowsToProcess = [];
        $collectedKodes = [];

        while (($row = fgetcsv($handle, 4000, $delimiter)) !== false) {
            if (empty($row) || (count($row) === 1 && empty($row[0]))) {
                continue;
            }

            $rowData = [];
            foreach ($cleanHeader as $idx => $colName) {
                $rowData[$colName] = isset($row[$idx]) ? trim($row[$idx]) : '';
            }

            $nama = $getValue($rowData, ['nama', 'nama_obat', 'name', 'medicine_name', 'product']);
            if (empty($nama)) {
                continue; // Skip rows without name
            }

            $kode = strtoupper($getValue($rowData, ['kode', 'kode_obat', 'code', 'medicine_code', 'id']));
            if (empty($kode)) {
                $kode = 'OBT-' . rand(10000, 99999);
            }

            $jenisObat = $getValue($rowData, ['jenis_obat', 'jenis', 'satuan', 'unit'], 'Tablet');
            $kategori = $getValue($rowData, ['kategori', 'category'], 'Antibiotik');
            $rawHarga = $getValue($rowData, ['harga', 'harga_jual', 'price', 'sell_price'], '0');
            $harga = (float)preg_replace('/[^0-9.]/', '', $rawHarga);

            $rawMinStok = $getValue($rowData, ['min_stok', 'stok_minimum', 'min_stock'], '10');
            $minStok = (int)preg_replace('/[^0-9]/', '', $rawMinStok);
            if ($minStok <= 0) $minStok = 10;

            $merk = $getValue($rowData, ['merk', 'brand', 'factory'], '');
            $supplierName = $getValue($rowData, ['supplier_name', 'supplier', 'pbf'], '');

            $collectedKodes[] = $kode;
            $rowsToProcess[] = [
                'kode' => $kode,
                'nama' => $nama,
                'jenis_obat' => $jenisObat,
                'kategori' => $kategori,
                'harga' => $harga,
                'min_stok' => $minStok,
                'merk' => $merk,
                'supplier_name' => $supplierName,
            ];
        }
        fclose($handle);

        if (empty($rowsToProcess)) {
            return redirect()->back()->withErrors(['error' => 'Tidak ada baris data obat yang valid untuk di-import.']);
        }

        // Single query lookup for all codes in memory
        $existingMap = Obat::whereIn('kode', array_unique($collectedKodes))->pluck('kode', 'kode')->toArray();

        $insertedCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;

        $obatsToInsert = [];
        $productUnitsToInsert = [];
        $productPricesToInsert = [];

        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            foreach ($rowsToProcess as $r) {
                $k = $r['kode'];
                $isExisting = isset($existingMap[$k]);

                if ($isExisting) {
                    if ($mode === 'skip') {
                        $skippedCount++;
                        continue;
                    }

                    // Update existing record
                    DB::table('obats')->where('kode', $k)->update([
                        'nama' => $r['nama'],
                        'jenis_obat' => $r['jenis_obat'],
                        'kategori' => $r['kategori'],
                        'harga' => $r['harga'] > 0 ? $r['harga'] : DB::raw('harga'),
                        'min_stok' => $r['min_stok'],
                        'merk' => $r['merk'] ?: DB::raw('merk'),
                        'supplier_name' => $r['supplier_name'] ?: DB::raw('supplier_name'),
                        'updated_at' => $now,
                    ]);
                    $updatedCount++;
                } else {
                    // Prepare insert
                    $existingMap[$k] = $k; // Prevent duplicates inside same CSV

                    $obatsToInsert[] = [
                        'kode' => $k,
                        'nama' => $r['nama'],
                        'gambar' => 'default.png',
                        'stok' => 0,
                        'jenis_obat' => $r['jenis_obat'],
                        'kategori' => $r['kategori'],
                        'harga' => $r['harga'],
                        'min_stok' => $r['min_stok'],
                        'merk' => $r['merk'],
                        'supplier_name' => $r['supplier_name'],
                        'satuan_dasar_id' => $defaultUnitId,
                        'satuan_pembelian_id' => $defaultUnitId,
                        'satuan_penjualan_id' => $defaultUnitId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $productUnitsToInsert[] = [
                        'product_id' => $k,
                        'unit_id' => $defaultUnitId,
                        'is_base_unit' => true,
                        'is_purchase_unit' => true,
                        'is_selling_unit' => true,
                        'conversion_factor' => 1.0000,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $productPricesToInsert[] = [
                        'product_id' => $k,
                        'unit_id' => $defaultUnitId,
                        'price_type' => 'SELLING',
                        'price' => $r['harga'],
                        'created_by' => $userId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $productPricesToInsert[] = [
                        'product_id' => $k,
                        'unit_id' => $defaultUnitId,
                        'price_type' => 'PURCHASE',
                        'price' => $r['harga'] * 0.8,
                        'created_by' => $userId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $insertedCount++;
                }
            }

            // High-speed chunked batch insertion
            foreach (array_chunk($obatsToInsert, 500) as $chunk) {
                DB::table('obats')->insert($chunk);
            }
            foreach (array_chunk($productUnitsToInsert, 500) as $chunk) {
                DB::table('product_units')->insert($chunk);
            }
            foreach (array_chunk($productPricesToInsert, 500) as $chunk) {
                DB::table('product_prices')->insert($chunk);
            }

            DB::commit();

            app(\App\Services\AuditLogService::class)->log(
                'IMPORT_OBAT',
                'Produk',
                null,
                ['inserted' => $insertedCount, 'updated' => $updatedCount, 'skipped' => $skippedCount]
            );

            $msg = "Import Selesai! {$insertedCount} Obat Baru Ditambahkan, {$updatedCount} Diupdate";
            if ($skippedCount > 0) {
                $msg .= ", {$skippedCount} Dilewati";
            }
            $msg .= ".";

            return redirect()->route('admin.obat.index')
                ->with('success', $msg)
                ->with('import_summary', [
                    'inserted' => $insertedCount,
                    'updated' => $updatedCount,
                    'skipped' => $skippedCount,
                    'total' => $insertedCount + $updatedCount + $skippedCount,
                ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => 'Gagal mengimpor data obat: ' . $e->getMessage()]);
        }
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\Obat;
use App\Models\MedicineBatch;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Exception;

class StockOpnameManualController extends Controller
{
    /**
     * Riwayat Stok Opname List View
     */
    public function index(Request $request)
    {
        $query = StockOpname::with(['user', 'approvedBy', 'outlet'])
            ->orderBy('id', 'desc');

        if ($request->filled('search')) {
            $query->where('opname_number', 'like', "%{$request->search}%");
        }
        if ($request->filled('status')) {
            $query->where('status', strtoupper($request->status));
        }
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('opname_date', [$request->start_date, $request->end_date]);
        }

        $opnames = $query->paginate(10)->withQueryString();

        // Metrics for summary cards
        $totalOpnames = StockOpname::count();
        $draftCount = StockOpname::whereIn(DB::raw('UPPER(status)'), ['DRAFT', 'COUNTING'])->count();
        $completedCount = StockOpname::whereIn(DB::raw('UPPER(status)'), ['COMPLETED', 'APPROVED'])->count();

        $user = auth()->user();
        $outlets = \App\Services\OutletService::getUserOutlets($user);
        $activeOutletId = \App\Services\OutletService::getActiveOutletId($user);

        return Inertia::render('Opname/Index', [
            'opnames' => $opnames,
            'outlets' => $outlets,
            'activeOutletId' => $activeOutletId,
            'metrics' => [
                'total' => $totalOpnames,
                'draft' => $draftCount,
                'completed' => $completedCount,
            ],
            'filters' => $request->only(['search', 'status', 'start_date', 'end_date'])
        ]);
    }

    /**
     * Create New SO Session (Status DRAFT)
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $date = now()->format('Ymd');
            $prefix = 'SO-' . $date . '-';

            // Get the highest existing SO number with today's date prefix
            $lastToday = StockOpname::withTrashed()
                ->where('opname_number', 'like', $prefix . '%')
                ->orderByRaw("CAST(SUBSTRING_INDEX(opname_number, '-', -1) AS UNSIGNED) DESC")
                ->first();

            $nextNum = 1;
            if ($lastToday) {
                $parts = explode('-', $lastToday->opname_number);
                $lastSeq = (int) end($parts);
                $nextNum = $lastSeq + 1;
            }

            // Loop until we find an unused number (safeguard)
            $soNumber = $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
            while (StockOpname::withTrashed()->where('opname_number', $soNumber)->exists()) {
                $nextNum++;
                $soNumber = $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
            }

            $userId = auth()->id();
            $activeOutletId = \App\Services\OutletService::getActiveOutletId();

            $opname = StockOpname::create([
                'opname_number' => $soNumber,
                'opname_date' => now()->toDateString(),
                'user_id' => $userId,
                'outlet_id' => $activeOutletId,
                'status' => 'DRAFT',
                'notes' => $request->input('notes', 'Stok Opname Manual'),
            ]);

            // Copy snapshot of all medicines for this active outlet
            $medicines = Obat::with('supplier')->get();

            $totalSystemValue = 0;
            $totalItemsCount = 0;

            foreach ($medicines as $med) {
                $unitName = $med->jenis_obat ? $med->jenis_obat : 'Unit';
                $supplierName = $med->supplier_name ? $med->supplier_name : ($med->supplier ? $med->supplier->name : null);
                $stok = (int) \App\Services\OutletService::getStock($med->kode, $activeOutletId);
                $harga = (float) ($med->harga ?? 0);
                $itemValue = $stok * $harga;
                $totalSystemValue += $itemValue;
                $totalItemsCount++;

                StockOpnameItem::create([
                    'stock_opname_id' => $opname->id,
                    'medicine_id' => $med->kode,
                    'batch_id' => null,
                    'unit_name' => $unitName,
                    'unit_price' => $harga,
                    'supplier_name' => $supplierName,
                    'merk' => $med->merk,
                    'system_stock' => $stok,
                    'physical_stock' => 0,
                    'difference' => -$stok,
                    'difference_value' => -$itemValue,
                    'is_counted' => false,
                ]);
            }

            $opname->update([
                'total_items' => $totalItemsCount,
                'system_total_value' => $totalSystemValue,
            ]);

            app(AuditLogService::class)->log(
                'CREATE_SO',
                'STOK_OPNAME',
                null,
                [
                    'opname_number' => $soNumber,
                    'total_items' => $totalItemsCount,
                    'system_total_value' => $totalSystemValue,
                ]
            );

            DB::commit();

            return redirect()->route('opname.show', $opname->id)->with('success', "Stok Opname {$soNumber} berhasil dibuat!");
        } catch (\Throwable $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error("SO store error: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return back()->with('error', 'Gagal membuat Stok Opname: ' . $e->getMessage());
        }
    }

    /**
     * Show Manual SO Workspace with Filters & Pagination
     */
    public function show(Request $request, $id)
    {
        $opname = StockOpname::with(['user', 'approvedBy', 'outlet'])->findOrFail($id);
        if (in_array(strtolower($opname->status), ['draft', 'counting'])) {
            $opname->update(['status' => 'DRAFT']);
            $opname->status = 'DRAFT';
        }

        $query = StockOpnameItem::with(['medicine.supplier', 'batch'])
            ->where('stock_opname_id', $id);

        // Product search (code / name)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('medicine', function($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('kode', 'like', "%{$search}%");
            });
        }

        // Category filter
        if ($request->filled('category')) {
            $category = $request->category;
            $query->whereHas('medicine', function($q) use ($category) {
                $q->where('kategori', $category);
            });
        }

        // Count status filter (counted vs pending)
        if ($request->filled('count_status')) {
            if ($request->count_status === 'counted') {
                $query->where('is_counted', true);
            } elseif ($request->count_status === 'pending') {
                $query->where('is_counted', false);
            }
        }

        // Difference filter (diff, match, surplus, shortage)
        if ($request->filled('diff_status')) {
            if ($request->diff_status === 'diff') {
                $query->where('difference', '!=', 0);
            } elseif ($request->diff_status === 'match') {
                $query->where('difference', 0);
            } elseif ($request->diff_status === 'surplus') {
                $query->where('difference', '>', 0);
            } elseif ($request->diff_status === 'shortage') {
                $query->where('difference', '<', 0);
            }
        }

        $items = $query->orderBy('id', 'asc')->paginate(20)->withQueryString();

        $categories = Obat::select('kategori')->distinct()->whereNotNull('kategori')->pluck('kategori');

        return Inertia::render('Opname/Show', [
            'opname' => $opname,
            'items' => $items,
            'categories' => $categories,
            'summary' => $this->getSummaryData($id),
            'filters' => $request->only(['search', 'category', 'count_status', 'diff_status'])
        ]);
    }

    /**
     * Update Single Item Physical Stock & Reason (AJAX/API Endpoint)
     */
    public function updateItem(Request $request, $id)
    {
        $request->validate([
            'item_id' => 'required|exists:stock_opname_items,id',
            'physical_stock' => 'required|integer|min:0',
            'reason' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $opname = StockOpname::findOrFail($id);
        if (!in_array(strtoupper($opname->status), ['DRAFT', 'COUNTING'])) {
            return response()->json(['success' => false, 'message' => 'SO sudah difinalisasi atau dibatalkan.'], 422);
        }

        $item = StockOpnameItem::where('id', $request->item_id)->where('stock_opname_id', $id)->firstOrFail();

        $physical = (int)$request->physical_stock;
        $diff = $physical - $item->system_stock;
        $diffVal = $diff * $item->unit_price;

        $item->update([
            'physical_stock' => $physical,
            'difference' => $diff,
            'difference_value' => $diffVal,
            'is_counted' => true,
            'reason' => $request->reason,
            'notes' => $request->notes,
        ]);

        // Recalculate SO total metrics
        $this->recalculateSO($id);
        $updatedSummary = $this->getSummaryData($id);

        return response()->json([
            'success' => true,
            'message' => 'Stok fisik berhasil diupdate',
            'data' => [
                'physical_stock' => $physical,
                'difference' => $diff,
                'difference_value' => $diffVal,
                'is_counted' => true,
            ],
            'summary' => $updatedSummary
        ]);
    }

    /**
     * Mass Action: Tandai Semua Sudah Dihitung (Set Physical = System)
     */
    public function markAllCounted($id)
    {
        $opname = StockOpname::findOrFail($id);
        if ($opname->status !== 'DRAFT') {
            return back()->with('error', 'SO ini sudah tidak aktif.');
        }

        // Set all uncounted items physical_stock = system_stock
        StockOpnameItem::where('stock_opname_id', $id)
            ->where('is_counted', false)
            ->get()
            ->each(function($item) {
                $item->update([
                    'physical_stock' => $item->system_stock,
                    'difference' => 0,
                    'difference_value' => 0,
                    'is_counted' => true,
                ]);
            });

        $this->recalculateSO($id);

        return back()->with('success', 'Semua barang berhasil ditandai sudah dihitung!');
    }

    /**
     * Finalize Stock Opname (Adjust stock, record stock card & audit log)
     */
    public function finalize(Request $request, $id)
    {
        $opname = StockOpname::findOrFail($id);
        if (!in_array(strtoupper($opname->status), ['DRAFT', 'COUNTING'])) {
            return back()->with('error', 'SO ini sudah difinalisasi atau dibatalkan sebelumnya.');
        }

        DB::beginTransaction();
        try {
            $userId = auth()->id();
            $items = StockOpnameItem::where('stock_opname_id', $id)->get();

            foreach ($items as $item) {
                // If item physical stock was entered, or default to system_stock if uncounted
                $physical = $item->is_counted ? $item->physical_stock : $item->system_stock;
                $diff = $physical - $item->system_stock;
                $diffVal = $diff * $item->unit_price;

                if (!$item->is_counted) {
                    $item->update([
                        'physical_stock' => $physical,
                        'difference' => $diff,
                        'difference_value' => $diffVal,
                        'is_counted' => true,
                    ]);
                }

                // ─────────────────────────────────────────────────────────────
                // UPDATE STOK OBAT BERDASARKAN HASIL OPNAME
                // ─────────────────────────────────────────────────────────────
                if ($item->batch_id) {
                    // === Mode Per-Batch: update stok batch spesifik ===
                    $batch = MedicineBatch::find($item->batch_id);
                    if ($batch) {
                        $batch->update(['stock' => $physical]);
                    }
                    // Recalculate total stok obat dari semua batch aktif
                    $totalMedStock = MedicineBatch::where('medicine_id', $item->medicine_id)
                        ->where('is_active', true)
                        ->sum('stock');
                    Obat::where('kode', $item->medicine_id)->update(['stok' => $totalMedStock]);

                } else {
                    // === Mode Master (tanpa batch): langsung set stok obat ===
                    Obat::where('kode', $item->medicine_id)->update(['stok' => $physical]);

                    // Jika obat ini punya batch aktif, proporsikan stok batch
                    // agar total batch == physical (mencegah desync)
                    $activeBatches = MedicineBatch::where('medicine_id', $item->medicine_id)
                        ->where('is_active', true)
                        ->orderBy('id', 'asc')
                        ->get();

                    if ($activeBatches->count() > 0) {
                        $oldTotal = $activeBatches->sum('stock');
                        $remaining = $physical;

                        foreach ($activeBatches as $idx => $ab) {
                            if ($idx === $activeBatches->count() - 1) {
                                // Sisa stok masuk ke batch terakhir
                                $ab->update(['stock' => max(0, $remaining)]);
                            } else {
                                // Proporsional berdasarkan stok lama
                                if ($oldTotal > 0) {
                                    $portion = (int) round(($ab->stock / $oldTotal) * $physical);
                                } else {
                                    $portion = 0;
                                }
                                $ab->update(['stock' => max(0, $portion)]);
                                $remaining -= max(0, $portion);
                            }
                        }
                    }
                }

                // ─────────────────────────────────────────────────────────────
                // CATAT KARTU STOK (STOCK MOVEMENT) — semua item counted
                // ─────────────────────────────────────────────────────────────
                $reasonStr = $item->reason ?: 'Penyesuaian Stok Opname';
                $movNum = 'SM-SO-' . now()->format('YmdHis') . '-' . $item->id;

                if ($diff > 0) {
                    // SURPLUS: stok fisik lebih dari sistem → catat sebagai MASUK (+)
                    DB::table('stock_movements')->insert([
                        'movement_number'  => $movNum,
                        'medicine_id'      => $item->medicine_id,
                        'batch_id'         => $item->batch_id,
                        'type'             => 'in',
                        'movement_type'    => 'STOCK_OPNAME',
                        'quantity'         => $diff,
                        'stock_before'     => $item->system_stock,
                        'stock_after'      => $physical,
                        'reference_number' => $opname->opname_number,
                        'warehouse_name'   => 'Gudang Utama',
                        'unit_cost'        => $item->unit_price ?? 0,
                        'user_id'          => $userId,
                        'status'           => 'POSTED',
                        'notes'            => "FINAL SO | Surplus +{$diff} | {$reasonStr}",
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ]);
                } elseif ($diff < 0) {
                    // DEFICIT: stok fisik kurang dari sistem → catat sebagai KELUAR (-)
                    DB::table('stock_movements')->insert([
                        'movement_number'  => $movNum,
                        'medicine_id'      => $item->medicine_id,
                        'batch_id'         => $item->batch_id,
                        'type'             => 'out',
                        'movement_type'    => 'STOCK_OPNAME',
                        'quantity'         => abs($diff),
                        'stock_before'     => $item->system_stock,
                        'stock_after'      => $physical,
                        'reference_number' => $opname->opname_number,
                        'warehouse_name'   => 'Gudang Utama',
                        'unit_cost'        => $item->unit_price ?? 0,
                        'user_id'          => $userId,
                        'status'           => 'POSTED',
                        'notes'            => "FINAL SO | Defisit {$diff} | {$reasonStr}",
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ]);
                } else {
                    // MATCH: selisih nol → catat sebagai audit/konfirmasi stok (in=0, type=in)
                    DB::table('stock_movements')->insert([
                        'movement_number'  => $movNum,
                        'medicine_id'      => $item->medicine_id,
                        'batch_id'         => $item->batch_id,
                        'type'             => 'in',
                        'movement_type'    => 'STOCK_OPNAME',
                        'quantity'         => 0,
                        'stock_before'     => $item->system_stock,
                        'stock_after'      => $physical,
                        'reference_number' => $opname->opname_number,
                        'warehouse_name'   => 'Gudang Utama',
                        'unit_cost'        => $item->unit_price ?? 0,
                        'user_id'          => $userId,
                        'status'           => 'POSTED',
                        'notes'            => "FINAL SO | Stok Sesuai (Konfirmasi) | {$opname->opname_number}",
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ]);
                }

            }

            // Recalculate and update SO Status to COMPLETED
            $this->recalculateSO($id);

            $opname->update([
                'status' => 'COMPLETED',
                'approved_by' => $userId,
                'completed_at' => now(),
            ]);

            app(AuditLogService::class)->log(
                'FINALIZE_SO',
                'STOK_OPNAME',
                ['status' => $opname->status],
                [
                    'opname_number' => $opname->opname_number,
                    'total_items' => $opname->total_items,
                    'difference_value' => $opname->difference_value,
                    'status' => 'COMPLETED',
                ]
            );

            DB::commit();

            return redirect()->route('opname.index')->with('success', "Stok Opname {$opname->opname_number} berhasil difinalisasi! Stok sistem dan kartu stok telah diperbarui.");
        } catch (Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Cancel SO session
     */
    public function cancel($id)
    {
        $opname = StockOpname::findOrFail($id);
        if ($opname->status !== 'DRAFT') {
            return back()->with('error', 'Hanya SO DRAFT yang bisa dibatalkan.');
        }

        $opname->update(['status' => 'CANCELLED']);

        return redirect()->route('opname.index')->with('success', "Dokumen SO {$opname->opname_number} dibatalkan.");
    }

    /**
     * Delete SO Session
     */
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $opname = StockOpname::findOrFail($id);
            $opnameNumber = $opname->opname_number;

            // Delete associated items
            StockOpnameItem::where('stock_opname_id', $id)->delete();

            // Soft delete SO document with deleted_by
            $opname->update(['deleted_by' => auth()->id()]);
            $opname->delete();

            app(AuditLogService::class)->log(
                'SOFT_DELETE',
                'StokOpname',
                null,
                ['opname_number' => $opnameNumber, 'status' => 'SOFT_DELETED']
            );

            DB::commit();

            return redirect()->route('opname.index')->with('success', "Dokumen Stok Opname {$opnameNumber} berhasil dihapus.");
        } catch (Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Recalculate SO summary values
     */
    private function recalculateSO($id)
    {
        $opname = StockOpname::find($id);
        if (!$opname) return;

        $items = StockOpnameItem::where('stock_opname_id', $id)->get();

        $totalItems = $items->count();
        $matchedCount = $items->where('is_counted', true)->where('difference', 0)->count();
        $surplusCount = $items->where('is_counted', true)->where('difference', '>', 0)->count();
        $deficitCount = $items->where('is_counted', true)->where('difference', '<', 0)->count();

        $systemValue = $items->sum(fn($i) => $i->system_stock * $i->unit_price);
        $physicalValue = $items->where('is_counted', true)->sum(fn($i) => $i->physical_stock * $i->unit_price);
        $diffValue = $physicalValue - $systemValue;

        $opname->update([
            'total_items' => $totalItems,
            'items_matched' => $matchedCount,
            'items_surplus' => $surplusCount,
            'items_deficit' => $deficitCount,
            'system_total_value' => $systemValue,
            'physical_total_value' => $physicalValue,
            'difference_value' => $diffValue,
        ]);
    }

    /**
     * Get summary metrics array
     */
    private function getSummaryData($id)
    {
        $allItems = StockOpnameItem::where('stock_opname_id', $id)->get();

        $totalItemsCount = $allItems->count();
        $countedItemsCount = $allItems->where('is_counted', true)->count();
        $matchedCount = $allItems->where('is_counted', true)->where('difference', 0)->count();
        $surplusCount = $allItems->where('is_counted', true)->where('difference', '>', 0)->count();
        $deficitCount = $allItems->where('is_counted', true)->where('difference', '<', 0)->count();

        $totalQtySystem = $allItems->sum('system_stock');
        $totalQtyPhysical = $allItems->where('is_counted', true)->sum('physical_stock');

        $systemTotalValue = $allItems->sum(fn($i) => $i->system_stock * $i->unit_price);
        $physicalTotalValue = $allItems->where('is_counted', true)->sum(fn($i) => $i->physical_stock * $i->unit_price);
        $differenceValue = $physicalTotalValue - $systemTotalValue;

        $surplusQtyTotal = $allItems->where('is_counted', true)->where('difference', '>', 0)->sum('difference');
        $deficitQtyTotal = $allItems->where('is_counted', true)->where('difference', '<', 0)->sum('difference');

        return [
            'total_items' => $totalItemsCount,
            'counted_items' => $countedItemsCount,
            'items_matched' => $matchedCount,
            'items_surplus' => $surplusCount,
            'items_deficit' => $deficitCount,
            'total_qty_system' => $totalQtySystem,
            'total_qty_physical' => $totalQtyPhysical,
            'system_total_value' => $systemTotalValue,
            'physical_total_value' => $physicalTotalValue,
            'difference_value' => $differenceValue,
            'surplus_qty_total' => $surplusQtyTotal,
            'deficit_qty_total' => $deficitQtyTotal,
        ];
    }

    /**
     * Print Official Stock Opname Report Document
     */
    public function print($id)
    {
        $opname = StockOpname::with(['user', 'approvedBy', 'outlet'])->findOrFail($id);
        $items = StockOpnameItem::with(['medicine.supplier'])
            ->where('stock_opname_id', $id)
            ->orderBy('id', 'asc')
            ->get();

        $outletName = $opname->outlet ? $opname->outlet->name : 'Apotek Utama';
        $outletAddress = $opname->outlet ? ($opname->outlet->address ?: 'Alamat Outlet belum diatur') : '';

        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Laporan Stok Opname #{$opname->opname_number}</title>
            <style>
                body { font-family: 'Segoe UI', Helvetica, Arial, sans-serif; font-size: 12px; color: #1e293b; margin: 0; padding: 20px; }
                .header { border-bottom: 2px solid #3b6bff; padding-bottom: 12px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
                .title { font-size: 20px; font-weight: bold; color: #1e3c72; margin: 0; }
                .subtitle { color: #64748b; font-size: 12px; margin-top: 4px; }
                .info-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 20px; background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; }
                .info-label { font-size: 10px; text-transform: uppercase; color: #64748b; font-weight: bold; }
                .info-val { font-size: 13px; font-weight: bold; color: #0f172a; margin-top: 2px; }
                table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                th { background: #1e293b; color: #ffffff; padding: 8px 10px; text-align: left; font-size: 11px; }
                td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; font-size: 11px; }
                .text-end { text-align: right; }
                .text-center { text-align: center; }
                .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; }
                .badge-success { background: #dcfce7; color: #15803d; }
                .badge-danger { background: #fee2e2; color: #b91c1c; }
                .badge-warning { background: #fef3c7; color: #b45309; }
                .footer { margin-top: 40px; display: flex; justify-content: space-between; font-size: 11px; }
                .sig-box { text-align: center; width: 180px; }
                .sig-line { margin-top: 50px; border-bottom: 1px dashed #94a3b8; }
                @media print {
                    .no-print { display: none !important; }
                    body { padding: 0; }
                }
            </style>
        </head>
        <body>
            <div class='no-print' style='margin-bottom: 15px; text-align: right;'>
                <button onclick='window.print()' style='background: #3b6bff; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: bold;'>🖨️ Cetak Dokumen</button>
            </div>

            <div class='header'>
                <div>
                    <h1 class='title'>DOKUMEN HASIL STOK OPNAME</h1>
                    <div class='subtitle'>{$outletName} — {$outletAddress}</div>
                </div>
                <div style='text-align: right;'>
                    <div style='font-size: 16px; font-weight: bold; color: #3b6bff;'>{$opname->opname_number}</div>
                    <div class='subtitle'>Status: {$opname->status}</div>
                </div>
            </div>

            <div class='info-grid'>
                <div>
                    <div class='info-label'>Tanggal SO</div>
                    <div class='info-val'>{$opname->opname_date->format('d/m/Y')}</div>
                </div>
                <div>
                    <div class='info-label'>Petugas / User</div>
                    <div class='info-val'>" . ($opname->user ? $opname->user->name : '-') . "</div>
                </div>
                <div>
                    <div class='info-label'>Total Items</div>
                    <div class='info-val'>{$opname->total_items} Produk</div>
                </div>
                <div>
                    <div class='info-label'>Selisih Nilai (HPP)</div>
                    <div class='info-val' style='color: " . ($opname->difference_value < 0 ? '#dc2626' : '#16a34a') . ";'>Rp " . number_format($opname->difference_value, 0, ',', '.') . "</div>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th style='width: 30px;'>#</th>
                        <th>Kode</th>
                        <th>Nama Produk</th>
                        <th class='text-center'>Satuan</th>
                        <th class='text-center'>Stok Sistem</th>
                        <th class='text-center'>Stok Fisik</th>
                        <th class='text-center'>Selisih</th>
                        <th class='text-end'>Harga Beli (HPP)</th>
                        <th class='text-end'>Nilai Selisih</th>
                        <th>Alasan / Keterangan</th>
                    </tr>
                </thead>
                <tbody>";

        $no = 1;
        foreach ($items as $item) {
            $diff = $item->difference;
            $diffBadge = $diff === 0 ? "<span class='badge badge-success'>0</span>" : ($diff > 0 ? "<span class='badge badge-warning'>+{$diff}</span>" : "<span class='badge badge-danger'>{$diff}</span>");
            $medName = $item->medicine ? $item->medicine->nama : $item->medicine_id;
            $unitPriceStr = "Rp " . number_format($item->unit_price, 0, ',', '.');
            $diffValStr = "Rp " . number_format($item->difference_value, 0, ',', '.');
            $reason = $item->reason ?: ($item->notes ?: '-');

            $html .= "
                    <tr>
                        <td class='text-center'>{$no}</td>
                        <td><code>{$item->medicine_id}</code></td>
                        <td><strong>{$medName}</strong></td>
                        <td class='text-center'>{$item->unit_name}</td>
                        <td class='text-center'>{$item->system_stock}</td>
                        <td class='text-center'><strong>{$item->physical_stock}</strong></td>
                        <td class='text-center'>{$diffBadge}</td>
                        <td class='text-end'>{$unitPriceStr}</td>
                        <td class='text-end'>{$diffValStr}</td>
                        <td>{$reason}</td>
                    </tr>";
            $no++;
        }

        $html .= "
                </tbody>
            </table>

            <div class='footer'>
                <div class='sig-box'>
                    <div>Petugas Stok Opname</div>
                    <div class='sig-line'></div>
                    <div style='margin-top: 4px; font-weight: bold;'>" . ($opname->user ? $opname->user->name : 'Petugas') . "</div>
                </div>
                <div class='sig-box'>
                    <div>Apoteker / Head Outlet</div>
                    <div class='sig-line'></div>
                    <div style='margin-top: 4px; font-weight: bold;'>" . ($opname->approvedBy ? $opname->approvedBy->name : 'Penanggung Jawab') . "</div>
                </div>
            </div>
            <script>window.onload = function() { setTimeout(function(){ window.print(); }, 500); }</script>
        </body>
        </html>";

        return response($html)->header('Content-Type', 'text/html');
    }

    /**
     * Export Stock Opname Report to Excel / CSV
     */
    public function exportExcel($id)
    {
        $opname = StockOpname::with(['user', 'outlet'])->findOrFail($id);
        $items = StockOpnameItem::with('medicine')
            ->where('stock_opname_id', $id)
            ->orderBy('id', 'asc')
            ->get();

        $filename = "Stok_Opname_{$opname->opname_number}_" . date('Ymd_His') . ".csv";

        $headers = [
            "Content-type" => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename={$filename}",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ];

        $callback = function() use ($opname, $items) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, ['DOKUMEN STOK OPNAME']);
            fputcsv($file, ['No SO', $opname->opname_number]);
            fputcsv($file, ['Outlet', $opname->outlet ? $opname->outlet->name : 'Apotek Utama']);
            fputcsv($file, ['Tanggal', $opname->opname_date->format('Y-m-d')]);
            fputcsv($file, ['Petugas', $opname->user ? $opname->user->name : '-']);
            fputcsv($file, ['Status', $opname->status]);
            fputcsv($file, []);

            fputcsv($file, [
                'No',
                'Kode Produk',
                'Nama Produk',
                'Satuan',
                'Stok Sistem',
                'Stok Fisik',
                'Selisih',
                'Harga Beli HPP (Rp)',
                'Nilai Selisih HPP (Rp)',
                'Alasan Selisih',
                'Keterangan'
            ]);

            $no = 1;
            foreach ($items as $item) {
                fputcsv($file, [
                    $no++,
                    $item->medicine_id,
                    $item->medicine ? $item->medicine->nama : $item->medicine_id,
                    $item->unit_name,
                    $item->system_stock,
                    $item->physical_stock,
                    $item->difference,
                    $item->unit_price,
                    $item->difference_value,
                    $item->reason ?: '',
                    $item->notes ?: '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}

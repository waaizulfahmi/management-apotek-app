<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Obat;
use App\Models\Outlet;
use App\Models\ProductStock;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Services\OutletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Exception;

class StockTransferController extends Controller
{
    /**
     * Stok Per Outlet Overview & Filter Page
     */
    public function stockPerOutlet(Request $request)
    {
        $outletId = $request->input('outlet_id', OutletService::getActiveOutletId());
        $search = $request->input('search');
        $kategori = $request->input('kategori');
        $stockFilter = $request->input('stock_filter'); // low, empty, all

        $outlets = OutletService::getUserOutlets();
        $selectedOutlet = Outlet::find($outletId) ?: $outlets->first();

        $query = DB::table('obats')
            ->leftJoin('product_stocks', function ($join) use ($outletId) {
                $join->on('obats.kode', '=', 'product_stocks.obat_id')
                     ->where('product_stocks.outlet_id', '=', $outletId);
            })
            ->select(
                'obats.kode',
                'obats.nama',
                'obats.jenis_obat',
                'obats.kategori',
                'obats.harga',
                'obats.min_stok as main_min_stok',
                'obats.stok as main_stok',
                DB::raw('COALESCE(product_stocks.stock, 0) as current_stock'),
                DB::raw('COALESCE(product_stocks.min_stock, obats.min_stok, 5) as min_stock'),
                'product_stocks.rack_location'
            )
            ->whereNull('obats.deleted_at');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('obats.nama', 'like', "%{$search}%")
                  ->orWhere('obats.kode', 'like', "%{$search}%");
            });
        }

        if ($kategori) {
            $query->where('obats.kategori', $kategori);
        }

        if ($stockFilter === 'low') {
            $query->whereRaw('COALESCE(product_stocks.stock, 0) <= COALESCE(product_stocks.min_stock, obats.min_stok, 5)')
                  ->whereRaw('COALESCE(product_stocks.stock, 0) > 0');
        } elseif ($stockFilter === 'empty') {
            $query->whereRaw('COALESCE(product_stocks.stock, 0) <= 0');
        }

        $stocks = $query->paginate(20)->withQueryString();
        $categories = DB::table('obats')->whereNotNull('kategori')->distinct()->pluck('kategori');

        return Inertia::render('Inventory/StockPerOutlet', [
            'stocks' => $stocks,
            'outlets' => $outlets,
            'selectedOutlet' => $selectedOutlet,
            'categories' => $categories,
            'filters' => [
                'outlet_id' => $outletId,
                'search' => $search ?? '',
                'kategori' => $kategori ?? '',
                'stock_filter' => $stockFilter ?? 'all',
            ]
        ]);
    }

    /**
     * List Transfer Stok Antar Outlet
     */
    public function index(Request $request)
    {
        $status = $request->input('status');
        $fromOutlet = $request->input('from_outlet_id');
        $toOutlet = $request->input('to_outlet_id');

        $query = StockTransfer::with(['fromOutlet', 'toOutlet', 'creator', 'receiver', 'items.obat'])
            ->orderBy('id', 'desc');

        if ($status) {
            $query->where('status', $status);
        }
        if ($fromOutlet) {
            $query->where('from_outlet_id', $fromOutlet);
        }
        if ($toOutlet) {
            $query->where('to_outlet_id', $toOutlet);
        }

        $transfers = $query->paginate(15)->withQueryString();
        $outlets = OutletService::getUserOutlets();

        return Inertia::render('Inventory/StockTransfers/Index', [
            'transfers' => $transfers,
            'outlets' => $outlets,
            'filters' => [
                'status' => $status ?? '',
                'from_outlet_id' => $fromOutlet ?? '',
                'to_outlet_id' => $toOutlet ?? '',
            ]
        ]);
    }

    /**
     * Form Buat Transfer Stok
     */
    public function create()
    {
        $outlets = OutletService::getUserOutlets();
        $activeOutletId = OutletService::getActiveOutletId();

        $obats = Obat::select('kode', 'nama', 'jenis_obat', 'kategori', 'stok')
            ->whereNull('deleted_at')
            ->get();

        // Attach current active outlet stock to each obat
        foreach ($obats as $o) {
            $o->current_stock = OutletService::getStock($o->kode, $activeOutletId);
        }

        return Inertia::render('Inventory/StockTransfers/Create', [
            'outlets' => $outlets,
            'activeOutletId' => $activeOutletId,
            'obats' => $obats,
        ]);
    }

    /**
     * Simpan Transfer Stok (Status DRAFT / SENT)
     */
    public function store(Request $request)
    {
        $request->validate([
            'from_outlet_id' => 'required|exists:outlets,id',
            'to_outlet_id' => 'required|exists:outlets,id|different:from_outlet_id',
            'items' => 'required|array|min:1',
            'items.*.obat_id' => 'required|exists:obats,kode',
            'items.*.qty' => 'required|numeric|min:0.01',
            'submit_type' => 'required|in:DRAFT,SENT',
        ]);

        DB::beginTransaction();
        try {
            $code = 'TRF-' . date('Ymd') . '-' . str_pad(StockTransfer::count() + 1, 4, '0', STR_PAD_LEFT);
            $status = $request->submit_type === 'SENT' ? 'IN_TRANSIT' : 'DRAFT';

            $transfer = StockTransfer::create([
                'transfer_code' => $code,
                'from_outlet_id' => $request->from_outlet_id,
                'to_outlet_id' => $request->to_outlet_id,
                'status' => $status,
                'created_by' => auth()->id(),
                'sent_at' => $status === 'IN_TRANSIT' ? now() : null,
                'notes' => $request->notes,
            ]);

            foreach ($request->items as $item) {
                // Validate source stock if sending immediately
                if ($status === 'IN_TRANSIT') {
                    $availableStock = OutletService::getStock($item['obat_id'], $request->from_outlet_id);
                    if ($availableStock < $item['qty']) {
                        throw new Exception("Stok produk {$item['obat_id']} di outlet asal tidak mencukupi! (Tersedia: {$availableStock})");
                    }

                    // Deduct stock from source outlet
                    OutletService::adjustStock(
                        $item['obat_id'],
                        $request->from_outlet_id,
                        -$item['qty'],
                        'TRANSFER_OUT',
                        "Transfer Keluar #{$code} ke Outlet #{$request->to_outlet_id}"
                    );
                }

                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'obat_id' => $item['obat_id'],
                    'qty_requested' => $item['qty'],
                    'qty_sent' => $status === 'IN_TRANSIT' ? $item['qty'] : 0,
                    'unit' => $item['unit'] ?? 'PCS',
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            DB::commit();
            return redirect()->route('stock-transfers.index')->with('success', "Transfer stok #{$code} berhasil dibuat (" . ($status === 'IN_TRANSIT' ? 'Dalam Pengiriman' : 'Draft') . ")!");
        } catch (Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Kirim Transfer Stok yang statusnya DRAFT
     */
    public function send($id)
    {
        $transfer = StockTransfer::with('items')->findOrFail($id);

        if ($transfer->status !== 'DRAFT') {
            return redirect()->back()->with('error', 'Hanya transfer berstatus DRAFT yang dapat dikirim.');
        }

        DB::beginTransaction();
        try {
            foreach ($transfer->items as $item) {
                $availableStock = OutletService::getStock($item->obat_id, $transfer->from_outlet_id);
                if ($availableStock < $item->qty_requested) {
                    throw new Exception("Stok {$item->obat_id} di outlet asal tidak cukup! (Tersedia: {$availableStock})");
                }

                // Deduct stock from source outlet
                OutletService::adjustStock(
                    $item->obat_id,
                    $transfer->from_outlet_id,
                    -$item->qty_requested,
                    'TRANSFER_OUT',
                    "Transfer Keluar #{$transfer->transfer_code}"
                );

                $item->update(['qty_sent' => $item->qty_requested]);
            }

            $transfer->update([
                'status' => 'IN_TRANSIT',
                'sent_at' => now(),
            ]);

            DB::commit();
            return redirect()->back()->with('success', "Transfer stok #{$transfer->transfer_code} berhasil dikirim!");
        } catch (Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Terima Transfer Stok di Outlet Tujuan
     */
    public function receive(Request $request, $id)
    {
        $transfer = StockTransfer::with('items')->findOrFail($id);

        if (!in_array($transfer->status, ['SENT', 'IN_TRANSIT'])) {
            return redirect()->back()->with('error', 'Transfer tidak dalam status pengiriman.');
        }

        DB::beginTransaction();
        try {
            $receivedItems = $request->input('received_items', []);

            foreach ($transfer->items as $item) {
                $qtyReceived = isset($receivedItems[$item->id]) 
                    ? (float)$receivedItems[$item->id] 
                    : (float)$item->qty_sent;

                // Auto-attach product to destination outlet if not already registered
                OutletService::attachProductToOutlet($item->obat_id, $transfer->to_outlet_id);

                // Add stock to destination outlet
                OutletService::adjustStock(
                    $item->obat_id,
                    $transfer->to_outlet_id,
                    +$qtyReceived,
                    'TRANSFER_IN',
                    "Transfer Masuk #{$transfer->transfer_code} dari Outlet #{$transfer->from_outlet_id}"
                );

                $item->update(['qty_received' => $qtyReceived]);
            }

            $transfer->update([
                'status' => 'RECEIVED',
                'received_by' => auth()->id(),
                'received_at' => now(),
            ]);

            DB::commit();
            return redirect()->back()->with('success', "Transfer stok #{$transfer->transfer_code} telah diterima & stok outlet tujuan berhasil diperbarui!");
        } catch (Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Batalkan Transfer Stok
     */
    public function cancel($id)
    {
        $transfer = StockTransfer::with('items')->findOrFail($id);

        if ($transfer->status === 'RECEIVED') {
            return redirect()->back()->with('error', 'Transfer yang sudah diterima tidak dapat dibatalkan.');
        }

        DB::beginTransaction();
        try {
            // If was SENT / IN_TRANSIT, return deducted stock back to source outlet
            if (in_array($transfer->status, ['SENT', 'IN_TRANSIT'])) {
                foreach ($transfer->items as $item) {
                    if ($item->qty_sent > 0) {
                        OutletService::adjustStock(
                            $item->obat_id,
                            $transfer->from_outlet_id,
                            +$item->qty_sent,
                            'TRANSFER_CANCELLED',
                            "Pembatalan Transfer #{$transfer->transfer_code}"
                        );
                    }
                }
            }

            $transfer->update(['status' => 'CANCELLED']);

            DB::commit();
            return redirect()->back()->with('success', "Transfer stok #{$transfer->transfer_code} berhasil dibatalkan.");
        } catch (Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}

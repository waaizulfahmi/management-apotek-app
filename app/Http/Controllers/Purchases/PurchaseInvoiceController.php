<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePurchaseInvoiceRequest;
use App\Models\Obat;
use App\Models\Outlet;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\OutletService;
use App\Services\PurchaseInvoiceService;
use App\Services\UnitConversionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Exception;

class PurchaseInvoiceController extends Controller
{
    protected PurchaseInvoiceService $invoiceService;
    protected UnitConversionService $conversionService;

    public function __construct(
        PurchaseInvoiceService $invoiceService,
        UnitConversionService $conversionService
    ) {
        $this->invoiceService = $invoiceService;
        $this->conversionService = $conversionService;
    }

    /**
     * Menampilkan daftar Faktur Pembelian Farmasi dengan filter dan metrik
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status');
        $paymentStatus = $request->input('payment_status');
        $supplierId = $request->input('supplier_id');
        $outletId = $request->input('outlet_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $query = Purchase::with(['supplier', 'outlet', 'finalizedBy', 'cancelledBy'])
            ->withCount('items');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhere('receipt_number', 'like', "%{$search}%")
                  ->orWhere('po_number', 'like', "%{$search}%")
                  ->orWhere('delivery_order_number', 'like', "%{$search}%")
                  ->orWhereHas('supplier', function ($sq) use ($search) {
                      $sq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($status) {
            $query->where('status', strtoupper($status));
        }

        if ($paymentStatus) {
            $query->where('payment_status', $paymentStatus);
        }

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        if ($dateFrom) {
            $query->whereDate('received_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('received_date', '<=', $dateTo);
        }

        $invoices = $query->orderBy('id', 'desc')->paginate(15)->withQueryString();

        // Metrik Ringkasan Faktur
        $metrics = [
            'total_invoices' => Purchase::count(),
            'total_draft' => Purchase::where('status', 'DRAFT')->count(),
            'total_final' => Purchase::where('status', 'FINAL')->count(),
            'total_cancelled' => Purchase::where('status', 'CANCELLED')->count(),
            'total_final_amount' => (float)Purchase::where('status', 'FINAL')->sum('grand_total'),
            'total_unpaid_amount' => (float)Purchase::where('status', 'FINAL')->whereIn('payment_status', ['unpaid', 'partial'])->sum('remaining_amount'),
        ];

        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'phone']);
        $outlets = Outlet::orderBy('name')->get(['id', 'name', 'code']);

        return Inertia::render('Purchases/Invoices/Index', [
            'invoices' => $invoices,
            'metrics' => $metrics,
            'suppliers' => $suppliers,
            'outlets' => $outlets,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'payment_status' => $paymentStatus,
                'supplier_id' => $supplierId,
                'outlet_id' => $outletId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    /**
     * Form Buat Faktur Pembelian Baru
     */
    public function create(Request $request)
    {
        $poId = $request->input('po_id');
        $prefilledPo = null;

        if ($poId) {
            $po = DB::table('purchase_orders')->where('id', $poId)->first();
            if ($po) {
                $poItems = DB::table('purchase_order_items')
                    ->join('obats', 'purchase_order_items.medicine_id', '=', 'obats.kode')
                    ->where('purchase_order_items.purchase_order_id', $poId)
                    ->select('purchase_order_items.*', 'obats.nama as medicine_name', 'obats.kode as medicine_code', 'obats.barcode')
                    ->get();

                $prefilledPo = [
                    'id' => $po->id,
                    'po_number' => $po->po_number,
                    'supplier_id' => $po->supplier_id,
                    'outlet_id' => $po->outlet_id ?? null,
                    'items' => $poItems,
                ];
            }
        }

        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();
        $outlets = Outlet::orderBy('name')->get();
        $userOutletId = OutletService::getActiveOutletId($request->user());

        // Ambil daftar obat beserta data konversi satuan dan harga
        $medicines = $this->getMedicinesWithConversion();

        $generatedReceiptNumber = $this->invoiceService->generateReceiptNumber();

        return Inertia::render('Purchases/Invoices/Create', [
            'suppliers' => $suppliers,
            'outlets' => $outlets,
            'medicines' => $medicines,
            'default_outlet_id' => $userOutletId,
            'generated_receipt_number' => $generatedReceiptNumber,
            'prefilled_po' => $prefilledPo,
        ]);
    }

    /**
     * Simpan Faktur Pembelian (Draft atau Langsung Final)
     */
    public function store(StorePurchaseInvoiceRequest $request)
    {
        try {
            $validated = $request->validated();

            // Tangani upload dokumen scan faktur jika ada
            if ($request->hasFile('document')) {
                $file = $request->file('document');
                $path = $file->store('purchase_documents', 'public');
                $validated['document_path'] = $path;
            }

            $purchase = $this->invoiceService->saveInvoice(
                $validated,
                $validated['items'],
                null,
                $request->user()
            );

            // Jika user memilih untuk langsung difinalisasi
            if ($request->boolean('finalize_immediately')) {
                $purchase = $this->invoiceService->finalizeInvoice($purchase->id, $request->user());
                return redirect()->route('purchases.invoices.show', $purchase->id)
                    ->with('success', "Faktur pembelian {$purchase->receipt_number} berhasil dibuat dan LANGSUNG DIFINALISASI. Stok otomatis bertambah.");
            }

            return redirect()->route('purchases.invoices.show', $purchase->id)
                ->with('success', "Faktur pembelian {$purchase->receipt_number} berhasil disimpan sebagai DRAFT.");
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Tampilan Detail dan Preview Faktur
     */
    public function show(int $id)
    {
        $purchase = Purchase::with([
            'supplier',
            'outlet',
            'purchaseOrder',
            'finalizedBy',
            'cancelledBy',
            'items.medicine',
            'items.unit',
        ])->findOrFail($id);

        return Inertia::render('Purchases/Invoices/Show', [
            'invoice' => $purchase,
        ]);
    }

    /**
     * Form Edit Faktur Pembelian (Khusus Status DRAFT)
     */
    public function edit(int $id, Request $request)
    {
        $purchase = Purchase::with(['items.medicine', 'supplier', 'outlet'])->findOrFail($id);

        if (!$purchase->isDraft()) {
            return redirect()->route('purchases.invoices.show', $id)
                ->with('error', 'Faktur yang sudah FINAL atau DIBATALKAN tidak dapat diedit kembali.');
        }

        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();
        $outlets = Outlet::orderBy('name')->get();
        $medicines = $this->getMedicinesWithConversion();

        return Inertia::render('Purchases/Invoices/Edit', [
            'invoice' => $purchase,
            'suppliers' => $suppliers,
            'outlets' => $outlets,
            'medicines' => $medicines,
        ]);
    }

    /**
     * Update Faktur Pembelian DRAFT
     */
    public function update(StorePurchaseInvoiceRequest $request, int $id)
    {
        try {
            $validated = $request->validated();

            if ($request->hasFile('document')) {
                $file = $request->file('document');
                $path = $file->store('purchase_documents', 'public');
                $validated['document_path'] = $path;
            }

            $purchase = $this->invoiceService->saveInvoice(
                $validated,
                $validated['items'],
                $id,
                $request->user()
            );

            if ($request->boolean('finalize_immediately')) {
                $purchase = $this->invoiceService->finalizeInvoice($purchase->id, $request->user());
                return redirect()->route('purchases.invoices.show', $purchase->id)
                    ->with('success', "Faktur pembelian {$purchase->receipt_number} berhasil diperbarui dan DIFINALISASI. Stok otomatis bertambah.");
            }

            return redirect()->route('purchases.invoices.show', $purchase->id)
                ->with('success', "Draft faktur pembelian {$purchase->receipt_number} berhasil diperbarui.");
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Hapus Faktur (Hanya Draft)
     */
    public function destroy(int $id)
    {
        try {
            $purchase = Purchase::findOrFail($id);
            if (!$purchase->isDraft()) {
                return back()->with('error', 'Hanya faktur berstatus DRAFT yang dapat dihapus. Faktur FINAL harus melalui prosedur pembatalan.');
            }

            DB::transaction(function () use ($purchase) {
                $purchase->items()->delete();
                $purchase->delete();
            });

            return redirect()->route('purchases.invoices.index')
                ->with('success', 'Draft faktur pembelian berhasil dihapus.');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * FINALISASI FAKTUR PEMBELIAN
     * Menambah stok obat di outlet, mencatat mutasi stok, kartu stok, batch FEFO, dan hutang
     */
    public function finalize(int $id, Request $request)
    {
        try {
            $purchase = $this->invoiceService->finalizeInvoice($id, $request->user());

            return redirect()->route('purchases.invoices.show', $id)
                ->with('success', "Faktur pembelian {$purchase->receipt_number} BERHASIL DIFINALISASI. Seluruh item obat telah masuk ke stok persediaan dan kartu stok tercatat.");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * PEMBATALAN FAKTUR PEMBELIAN
     * Melakukan mutasi stok pembalik (reversal) dan membatalkan catatan hutang
     */
    public function cancel(Request $request, int $id)
    {
        $request->validate([
            'cancellation_reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'cancellation_reason.required' => 'Alasan pembatalan wajib diisi.',
            'cancellation_reason.min' => 'Alasan pembatalan minimal 5 karakter.',
        ]);

        try {
            $purchase = $this->invoiceService->cancelInvoice(
                $id,
                $request->input('cancellation_reason'),
                $request->user()
            );

            return redirect()->route('purchases.invoices.show', $id)
                ->with('success', "Faktur pembelian {$purchase->receipt_number} berhasil DIBATALKAN. Transaksi pembalik mutasi stok telah dieksekusi.");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Cetak Faktur atau Bukti Penerimaan Barang
     */
    public function print(int $id, Request $request)
    {
        $purchase = Purchase::with([
            'supplier',
            'outlet',
            'purchaseOrder',
            'finalizedBy',
            'cancelledBy',
            'items.medicine',
            'items.unit',
        ])->findOrFail($id);

        $type = $request->input('type', 'invoice'); // 'invoice' or 'receiving_slip'

        return Inertia::render('Purchases/Invoices/Print', [
            'invoice' => $purchase,
            'print_type' => $type,
        ]);
    }

    /**
     * Helper untuk mengambil seluruh obat beserta data konversi satuannya
     */
    protected function getMedicinesWithConversion()
    {
        $medicines = Obat::with(['satuanDasar', 'satuanPembelian'])->get();

        return $medicines->map(function ($med) {
            $priceData = $this->conversionService->getCalculatedPrices($med->kode);
            $units = $priceData['unit_prices'] ?? [];

            // Jika obat belum punya settingan product_units, buat unit default berdasarkan satuan dasarnya
            if (empty($units)) {
                $units = [
                    [
                        'unit_id' => $med->satuan_dasar_id,
                        'unit_name' => $med->satuanDasar ? $med->satuanDasar->name : ($med->jenis_obat ?: 'Unit'),
                        'is_base_unit' => true,
                        'is_purchase_unit' => true,
                        'conversion_factor' => 1.0,
                        'purchase_price' => (float)$med->harga_beli ?: (float)$med->harga * 0.8,
                        'purchase_price_formatted' => 'Rp ' . number_format((float)$med->harga_beli ?: (float)$med->harga * 0.8, 0, ',', '.'),
                    ]
                ];
            }

            return [
                'kode' => $med->kode,
                'nama' => $med->nama,
                'barcode' => $med->barcode,
                'kategori' => $med->kategori,
                'stok' => (float)$med->stok,
                'satuan_dasar_id' => $med->satuan_dasar_id,
                'satuan_dasar_name' => $med->satuanDasar ? $med->satuanDasar->name : ($med->jenis_obat ?: 'Unit'),
                'satuan_pembelian_id' => $med->satuan_pembelian_id,
                'harga_beli' => (float)$med->harga_beli ?: (float)$med->harga * 0.8,
                'units' => $units,
            ];
        });
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseInvoiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'receipt_number' => ['nullable', 'string', 'max:100'],
            'delivery_order_number' => ['nullable', 'string', 'max:100'],
            'purchase_order_id' => ['nullable', 'integer'],
            'purchase_date' => ['required', 'date'],
            'received_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'outlet_id' => ['nullable', 'integer'],
            'payment_type' => ['required', 'in:cash,credit,termin'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_type' => ['nullable', 'in:nominal,percentage'],
            'discount_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_type' => ['nullable', 'in:none,ppn_11,ppn_12,manual,custom'],
            'tax_rate' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'document' => ['nullable', 'file', 'mimes:jpeg,png,jpg,pdf', 'max:10240'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.medicine_id' => ['required', 'string', 'exists:obats,kode'],
            'items.*.batch_number' => ['nullable', 'string', 'max:100'],
            'items.*.expired_date' => ['nullable', 'date'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_id' => ['nullable', 'integer'],
            'items.*.unit_name' => ['nullable', 'string', 'max:50'],
            'items.*.conversion_factor' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Custom messages
     */
    public function messages(): array
    {
        return [
            'supplier_id.required' => 'Supplier / PBF wajib dipilih.',
            'supplier_id.exists' => 'Supplier / PBF yang dipilih tidak valid.',
            'purchase_date.required' => 'Tanggal faktur wajib diisi.',
            'received_date.required' => 'Tanggal penerimaan barang wajib diisi.',
            'items.required' => 'Minimal harus ada 1 item obat dalam faktur pembelian.',
            'items.min' => 'Minimal harus ada 1 item obat dalam faktur pembelian.',
            'items.*.medicine_id.required' => 'Obat wajib dipilih pada setiap baris item.',
            'items.*.quantity.required' => 'Jumlah barang wajib diisi.',
            'items.*.quantity.gt' => 'Jumlah barang harus lebih besar dari 0.',
            'items.*.unit_price.required' => 'Harga satuan beli wajib diisi.',
            'items.*.unit_price.min' => 'Harga satuan beli tidak boleh negatif.',
            'items.*.conversion_factor.required' => 'Faktor konversi satuan wajib diisi.',
            'items.*.conversion_factor.gt' => 'Faktor konversi satuan harus lebih besar dari 0.',
        ];
    }
}

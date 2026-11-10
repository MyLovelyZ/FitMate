<?php

namespace App\Http\Requests;

/**
 * Validasi form "Tambah Produk" admin (`admin/create_product`). Aturannya sama
 * dengan Seller Center (tipe & ukuran foto, harga, varian), ditambah toko
 * pemilik produk karena admin tidak punya toko sendiri.
 *
 * Atribut `#[ErrorBag]` milik induk tidak diwariskan PHP, jadi error form ini
 * masuk bag `default` — cocok untuk view controller biasa.
 */
class AdminProductRequest extends SellerProductRequest
{
    /**
     * Route-nya sudah di balik middleware `admin` (admin & superadmin).
     */
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'superadmin'], true);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            ...parent::rules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'store_id.required' => 'Pilih toko pemilik produk.',
            'store_id.exists' => 'Toko yang dipilih tidak ditemukan.',
        ];
    }
}

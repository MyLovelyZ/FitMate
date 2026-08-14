<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ongkos Kirim
    |--------------------------------------------------------------------------
    |
    | KEP-4 belum diputuskan (API kurir atau tarif flat). Sementara dipakai tarif
    | flat per toko: satu tarif dasar, ditambah biaya per kilogram di atas berat
    | dasar. Saat integrasi kurir masuk, ganti implementasi ShippingService —
    | pemanggilnya tidak perlu ikut berubah.
    |
    */

    'shipping' => [
        'flat_rate' => 20000,
        'base_weight_gram' => 1000,
        'per_extra_kg' => 8000,
        'couriers' => [
            'jne' => 'JNE Reguler',
            'jnt' => 'J&T Express',
            'sicepat' => 'SiCepat Reguler',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pembayaran
    |--------------------------------------------------------------------------
    |
    | KEP-3 belum diputuskan (Midtrans, Xendit, atau transfer manual). Sementara
    | hanya transfer manual dan COD yang dibuka; keduanya tidak butuh gateway.
    | Metode lain akan menyusul setelah provider dipilih.
    |
    */

    'payment' => [
        'enabled_methods' => ['bank_transfer', 'cod'],
        'expires_after_hours' => 24,
    ],

    /*
    |--------------------------------------------------------------------------
    | Katalog
    |--------------------------------------------------------------------------
    */

    'catalog' => [
        'per_page' => 12,
    ],

];

<?php

/**
 * Menjaga jalur uang FitMate: pembeli bayar sekali, dananya ditahan platform per
 * toko, baru cair ke seller setelah barang diterima.
 *
 * Yang diuji di sini bukan logika aplikasinya (belum ada), tapi janji-janji yang
 * ditegakkan skema — karena itulah pengaman terakhir kalau kode salah.
 */

use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * Satu pesanan yang sudah dibayar, lengkap sampai wallet penjualnya.
 *
 * @return array{store: Store, order_id: int, store_order_id: int, payment_id: int, seller_wallet_id: int}
 */
function paidOrderFixture(): array
{
    $buyer = User::factory()->create();
    $store = Store::factory()->create();

    $orderId = DB::table('orders')->insertGetId([
        'user_id' => $buyer->id,
        'order_number' => 'ORD-'.fake()->unique()->numerify('########'),
        'recipient_name' => $buyer->name,
        'recipient_phone' => '081234567890',
        'shipping_street' => 'Jl. Merdeka No. 1',
        'shipping_city' => 'Jakarta Pusat',
        'shipping_province' => 'DKI Jakarta',
        'shipping_postal_code' => '10110',
        'subtotal' => 500000,
        'grand_total' => 500000,
        'status' => 'paid',
        'paid_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $storeOrderId = DB::table('store_orders')->insertGetId([
        'order_id' => $orderId,
        'store_id' => $store->id,
        'store_order_number' => 'SO-'.fake()->unique()->numerify('########'),
        'subtotal' => 500000,
        'total' => 500000,
        'platform_fee_rate' => 0.05,
        'platform_fee_amount' => 25000,
        'seller_earning' => 475000,
        'status' => 'delivered',
        'delivered_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $paymentId = DB::table('payments')->insertGetId([
        'order_id' => $orderId,
        'method' => 'qris',
        'provider' => 'midtrans',
        'reference' => 'TRX-'.fake()->unique()->numerify('##########'),
        'amount' => 500000,
        'status' => 'paid',
        'paid_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $walletId = DB::table('wallets')->insertGetId([
        'user_id' => $store->user_id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [
        'store' => $store,
        'order_id' => $orderId,
        'store_order_id' => $storeOrderId,
        'payment_id' => $paymentId,
        'seller_wallet_id' => $walletId,
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 */
function insertEscrowHold(array $fixture, array $overrides = []): int
{
    return DB::table('escrow_holds')->insertGetId(array_merge([
        'store_order_id' => $fixture['store_order_id'],
        'payment_id' => $fixture['payment_id'],
        'seller_wallet_id' => $fixture['seller_wallet_id'],
        'gross_amount' => 500000,
        'platform_fee_amount' => 25000,
        'net_amount' => 475000,
        'status' => 'held',
        'held_at' => now(),
        'auto_release_at' => now()->addDays(7),
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

it('menyediakan seluruh tabel jalur uang', function () {
    $tables = [
        'stores', 'carts', 'cart_items', 'orders', 'store_orders', 'order_items',
        'shipments', 'payments', 'payment_events', 'wallets', 'wallet_transactions',
        'escrow_holds', 'payout_accounts', 'payouts', 'refunds', 'disputes',
    ];

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue("tabel {$table} belum ada");
    }
});

it('menahan dana per pesanan-toko, bukan per order', function () {
    $fixture = paidOrderFixture();
    insertEscrowHold($fixture);

    $hold = DB::table('escrow_holds')->where('store_order_id', $fixture['store_order_id'])->first();

    expect($hold->status)->toBe('held')
        ->and((float) $hold->net_amount)->toBe((float) $hold->gross_amount - (float) $hold->platform_fee_amount)
        ->and($hold->released_at)->toBeNull()
        ->and($hold->auto_release_at)->not->toBeNull();
});

it('menolak dua tahanan dana untuk pesanan-toko yang sama', function () {
    $fixture = paidOrderFixture();

    insertEscrowHold($fixture);
    insertEscrowHold($fixture);
})->throws(QueryException::class);

it('menolak satu order punya dua pesanan untuk toko yang sama', function () {
    $fixture = paidOrderFixture();

    DB::table('store_orders')->insert([
        'order_id' => $fixture['order_id'],
        'store_id' => $fixture['store']->id,
        'store_order_number' => 'SO-DUPLIKAT',
        'subtotal' => 1000,
        'total' => 1000,
        'seller_earning' => 1000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class);

it('menolak webhook gateway yang sama dicatat dua kali', function () {
    $fixture = paidOrderFixture();

    $event = [
        'payment_id' => $fixture['payment_id'],
        'provider' => 'midtrans',
        'event_id' => 'evt_abc123',
        'event_type' => 'payment.settled',
        'payload' => json_encode(['status' => 'settlement']),
        'created_at' => now(),
        'updated_at' => now(),
    ];

    DB::table('payment_events')->insert($event);
    DB::table('payment_events')->insert($event);
})->throws(QueryException::class);

it('menolak referensi transaksi gateway yang dobel', function () {
    $fixture = paidOrderFixture();
    $reference = DB::table('payments')->where('id', $fixture['payment_id'])->value('reference');

    DB::table('payments')->insert([
        'order_id' => $fixture['order_id'],
        'method' => 'qris',
        'reference' => $reference,
        'amount' => 500000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class);

it('menolak dua mutasi wallet dengan idempotency key yang sama', function () {
    $fixture = paidOrderFixture();

    $entry = [
        'wallet_id' => $fixture['seller_wallet_id'],
        'type' => 'escrow_release',
        'direction' => 'credit',
        'amount' => 475000,
        'balance_after' => 475000,
        'idempotency_key' => 'escrow-release:'.$fixture['store_order_id'],
        'created_at' => now(),
        'updated_at' => now(),
    ];

    DB::table('wallet_transactions')->insert($entry);
    DB::table('wallet_transactions')->insert($entry);
})->throws(QueryException::class);

it('mencatat rilis escrow sebagai kredit ke wallet seller', function () {
    $fixture = paidOrderFixture();
    $holdId = insertEscrowHold($fixture);

    DB::table('escrow_holds')->where('id', $holdId)->update([
        'status' => 'released',
        'release_reason' => 'buyer_confirmed',
        'released_at' => now(),
    ]);

    DB::table('wallet_transactions')->insert([
        'wallet_id' => $fixture['seller_wallet_id'],
        'type' => 'escrow_release',
        'direction' => 'credit',
        'amount' => 475000,
        'balance_after' => 475000,
        'reference_type' => 'escrow_hold',
        'reference_id' => $holdId,
        'idempotency_key' => 'escrow-release:'.$holdId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('wallets')->where('id', $fixture['seller_wallet_id'])->update([
        'balance_available' => 475000,
        'balance_pending' => 0,
    ]);

    $wallet = DB::table('wallets')->where('id', $fixture['seller_wallet_id'])->first();
    $ledgerTotal = DB::table('wallet_transactions')
        ->where('wallet_id', $wallet->id)
        ->where('direction', 'credit')
        ->sum('amount');

    // Saldo di wallets cuma cache; buku besar tetap sumber kebenarannya.
    expect((float) $wallet->balance_available)->toBe((float) $ledgerTotal)
        ->and((float) $wallet->balance_pending)->toBe(0.0);
});

it('menyimpan nota tetap utuh walau produknya dihapus permanen', function () {
    $fixture = paidOrderFixture();
    $product = Product::factory()->create(['store_id' => $fixture['store']->id]);

    $itemId = DB::table('order_items')->insertGetId([
        'store_order_id' => $fixture['store_order_id'],
        'product_id' => $product->id,
        'product_name' => $product->name,
        'size_label' => 'XL',
        'color_name' => 'Navy',
        'unit_price' => 250000,
        'quantity' => 2,
        'subtotal' => 500000,
        'recommended_size_label' => 'L',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $product->forceDelete();

    $item = DB::table('order_items')->where('id', $itemId)->first();

    expect($item->product_id)->toBeNull()
        ->and($item->product_name)->toBe($product->name)
        ->and($item->size_label)->toBe('XL')
        ->and((float) $item->unit_price)->toBe(250000.0);
});

it('menolak menghapus toko yang masih punya pesanan', function () {
    $fixture = paidOrderFixture();

    DB::table('stores')->where('id', $fixture['store']->id)->delete();
})->throws(QueryException::class);

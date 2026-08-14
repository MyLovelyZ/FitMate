<?php

/**
 * BE-070, BE-073, BE-075: alur checkout, kupon, dan stok.
 *
 * Ini bagian yang menyentuh uang dan stok, jadi yang diuji bukan hanya
 * "berhasil", tapi juga apa yang tersimpan dan apa yang berubah.
 */

use App\Enums\Gender;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\SizeType;
use App\Enums\StoreOrderStatus;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SizeChart;
use App\Models\Store;
use App\Models\User;
use App\Models\UserAddress;
use App\Services\CheckoutService;
use Database\Seeders\BodyMeasurementSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\SizeChartSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed([BodyMeasurementSeeder::class, CategorySeeder::class, SizeChartSeeder::class]);

    $this->checkout = app(CheckoutService::class);
    $this->buyer = User::factory()->create(['gender' => Gender::Male]);
    $this->address = UserAddress::factory()->isDefault()->for($this->buyer)->create();
});

function variantFor(?Store $store = null, int $stock = 10, float $price = 100000): ProductVariant
{
    $chart = SizeChart::firstWhere(['size_type' => SizeType::Top, 'gender' => Gender::Male]);

    $product = Product::factory()->active()->create([
        'store_id' => ($store ?? Store::factory()->active()->create())->id,
        'size_chart_id' => $chart->id,
    ]);

    return ProductVariant::factory()->for($product)->create([
        'size_chart_entry_id' => $chart->entries->first()->id,
        'stock' => $stock,
        'price' => $price,
    ]);
}

it('memecah keranjang jadi satu order dan beberapa store_order', function () {
    $cart = $this->buyer->currentCart();
    $first = variantFor();
    $second = variantFor();

    $cart->items()->create(['product_variant_id' => $first->id, 'quantity' => 2]);
    $cart->items()->create(['product_variant_id' => $second->id, 'quantity' => 1]);

    $order = $this->checkout->place($this->buyer, $cart, $this->address);

    expect($order->storeOrders)->toHaveCount(2)
        ->and($order->status)->toBe(OrderStatus::PendingPayment)
        ->and($order->subtotal)->toEqual('300000.00')
        ->and($order->items()->count())->toBe(2);
});

it('menyalin data produk ke order_items, bukan mereferensikannya', function () {
    $cart = $this->buyer->currentCart();
    $variant = variantFor(price: 150000);

    $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 1]);

    $order = $this->checkout->place($this->buyer, $cart, $this->address);
    $item = $order->items()->first();

    $originalName = $variant->product->name;

    // Harga dan nama produk berubah setelah pembelian; nota lama harus tetap
    // menunjukkan angka yang dulu.
    $variant->product->update(['name' => 'Nama Baru']);
    $variant->update(['price' => 999000]);

    expect($item->product_name)->toBe($originalName)
        ->and($item->unit_price)->toEqual('150000.00')
        ->and($item->size_label)->not->toBeNull();
});

it('menyalin alamat ke kolom shipping, bukan menyimpan referensi', function () {
    $cart = $this->buyer->currentCart();
    $cart->items()->create(['product_variant_id' => variantFor()->id, 'quantity' => 1]);

    $order = $this->checkout->place($this->buyer, $cart, $this->address);
    $originalCity = $this->address->city;

    $this->address->delete();

    expect($order->fresh()->shipping_city)->toBe($originalCity)
        ->and($order->fresh()->recipient_name)->not->toBeNull();
});

it('mengurangi stok varian dan mengosongkan keranjang', function () {
    $cart = $this->buyer->currentCart();
    $variant = variantFor(stock: 10);

    $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 3]);

    $this->checkout->place($this->buyer, $cart, $this->address);

    expect($variant->fresh()->stock)->toBe(7)
        ->and($cart->fresh()->items()->count())->toBe(0);
});

it('menolak checkout ketika stoknya tidak cukup', function () {
    $cart = $this->buyer->currentCart();
    $variant = variantFor(stock: 1);

    $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 5]);

    expect(fn () => $this->checkout->place($this->buyer, $cart, $this->address))
        ->toThrow(ValidationException::class);

    expect($variant->fresh()->stock)->toBe(1)
        ->and(Order::count())->toBe(0);
});

it('membuat catatan pembayaran yang masih menunggu', function () {
    $cart = $this->buyer->currentCart();
    $cart->items()->create(['product_variant_id' => variantFor()->id, 'quantity' => 1]);

    $order = $this->checkout->place($this->buyer, $cart, $this->address);
    $payment = $order->latestPayment();

    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->amount)->toEqual($order->grand_total)
        ->and($payment->expires_at)->not->toBeNull();
});

it('menerapkan kupon persentase dengan batas potongan maksimum', function () {
    $cart = $this->buyer->currentCart();
    $cart->items()->create(['product_variant_id' => variantFor(price: 1000000)->id, 'quantity' => 1]);

    $coupon = Coupon::factory()->create(['code' => 'DISKON10', 'value' => 10, 'max_discount' => 50000]);

    $order = $this->checkout->place($this->buyer, $cart, $this->address, ['coupon_code' => 'DISKON10']);

    // 10% dari 1.000.000 adalah 100.000, tapi dibatasi max_discount 50.000.
    expect($order->discount_total)->toEqual('50000.00')
        ->and($coupon->fresh()->used_count)->toBe(1)
        ->and($coupon->usages()->where('order_id', $order->id)->exists())->toBeTrue();
});

it('menolak kupon yang sudah pernah dipakai pengguna yang sama', function () {
    Coupon::factory()->create(['code' => 'SEKALI', 'min_purchase' => 0]);

    foreach (range(1, 2) as $attempt) {
        $cart = $this->buyer->currentCart();
        $cart->items()->create(['product_variant_id' => variantFor()->id, 'quantity' => 1]);

        if ($attempt === 1) {
            $this->checkout->place($this->buyer, $cart, $this->address, ['coupon_code' => 'SEKALI']);

            continue;
        }

        expect(fn () => $this->checkout->place($this->buyer, $cart, $this->address, ['coupon_code' => 'SEKALI']))
            ->toThrow(ValidationException::class);
    }
});

it('menolak kupon ketika belanjaan belum memenuhi minimum', function () {
    Coupon::factory()->create(['code' => 'MINIMAL', 'min_purchase' => 500000]);

    $cart = $this->buyer->currentCart();
    $cart->items()->create(['product_variant_id' => variantFor(price: 50000)->id, 'quantity' => 1]);

    expect(fn () => $this->checkout->place($this->buyer, $cart, $this->address, ['coupon_code' => 'MINIMAL']))
        ->toThrow(ValidationException::class);
});

it('mengembalikan stok saat pesanan dibatalkan', function () {
    $cart = $this->buyer->currentCart();
    $variant = variantFor(stock: 10);

    $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 4]);

    $order = $this->checkout->place($this->buyer, $cart, $this->address);
    expect($variant->fresh()->stock)->toBe(6);

    $this->checkout->cancel($order);

    expect($variant->fresh()->stock)->toBe(10)
        ->and($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($order->storeOrders->first()->fresh()->status)->toBe(StoreOrderStatus::Cancelled);
});

it('mencatat ukuran yang disarankan FitMate saat pembelian', function () {
    $chart = SizeChart::firstWhere(['size_type' => SizeType::Top, 'gender' => Gender::Male]);
    $profile = profileWith(['lingkar_dada' => 99, 'lebar_bahu' => 46, 'panjang_badan' => 71, 'tinggi_badan' => 172]);
    $profile->update(['user_id' => $this->buyer->id]);

    $cart = $this->buyer->currentCart();
    $variant = variantFor();
    $variant->update(['size_chart_entry_id' => $chart->entries->firstWhere('label', 'S')->id]);

    $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 1]);

    $order = $this->checkout->place($this->buyer, $cart, $this->address);
    $item = $order->items()->first();

    expect($item->recommended_size_label)->toBe('L')
        ->and($item->size_label)->toBe('S')
        ->and($item->followedRecommendation())->toBeFalse();
});

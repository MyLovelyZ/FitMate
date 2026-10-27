<?php

/**
 * Admin membuat dan mengelola kode promo: persentase, potongan rupiah, atau
 * persentase dengan batas maksimal, dengan kuota "N orang pertama".
 */

use App\Models\Cart;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

/**
 * Pembeli dengan keranjang satu barang Rp200.000, langsung checkout memakai kode.
 */
function orderWithCode(string $code): Order
{
    $buyer = User::factory()->create();
    Cart::forUser($buyer)->items()->create([
        'product_variant_id' => ProductVariant::factory()->create(['stock' => 5, 'price' => 200000])->id,
        'quantity' => 1,
    ]);

    return Order::placeFromCart($buyer, [
        'recipient_name' => 'Zahir',
        'recipient_phone' => '081200000000',
        'street' => 'Jl. Merdeka No. 1',
        'city' => 'Bandung',
        'state' => 'Jawa Barat',
        'postal_code' => '40111',
    ], 'jne-reg', 'qris', voucherCode: $code);
}

it('creates a percentage code capped at a rupiah maximum for the first 100 buyers', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin-vouchers')
        ->set('code', ' hemat10 ')
        ->set('name', 'Diskon 10% hingga Rp5.000')
        ->set('type', 'percentage')
        ->set('value', '10')
        ->set('maxDiscount', '5000')
        ->set('usageLimit', '100')
        ->assertSee('Ringkasan:')
        ->assertSee('Contoh: belanja Rp200.000 hemat')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('notify')
        ->assertSet('code', '')
        ->assertSee('HEMAT10')
        ->assertSee('10% (maks. Rp5.000)')
        ->assertSee('0 / 100');

    expect(Voucher::sole())
        ->code->toBe('HEMAT10')
        ->type->toBe('percentage')
        ->max_discount->toBe('5000.00')
        ->usage_limit->toBe(100)
        ->per_user_limit->toBe(1)
        ->is_active->toBeTrue()
        ->and(Voucher::sole()->discountFor(200000))->toBe(5000.0);
});

it('creates a fixed rupiah code and drops the maximum that only applies to percentages', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin-vouchers')
        ->set('maxDiscount', '5000')
        ->set('code', 'POTONG25')
        ->set('name', 'Potongan Rp25.000')
        ->set('type', 'fixed')
        ->set('value', '25000')
        ->set('minSubtotal', '150000')
        ->set('perUserLimit', '')
        ->call('save')
        ->assertHasNoErrors();

    expect(Voucher::sole())
        ->type->toBe('fixed')
        ->max_discount->toBeNull()
        ->min_subtotal->toBe('150000.00')
        ->usage_limit->toBeNull()
        ->per_user_limit->toBeNull()
        ->and(Voucher::sole()->benefitLabel())->toBe('Rp25.000');
});

it('validates the code form', function () {
    Voucher::factory()->create(['code' => 'SUDAHADA']);

    Livewire::actingAs($this->admin)
        ->test('pages::admin-vouchers')
        ->set('code', 'sudahada')
        ->set('name', 'Duplikat')
        ->set('value', '150')
        ->set('usageLimit', '0')
        ->set('startsAt', '2026-12-10T00:00')
        ->set('expiresAt', '2026-12-01T00:00')
        ->call('save')
        ->assertHasErrors([
            'code' => 'unique',
            'value' => 'max',
            'usageLimit' => 'min',
            'expiresAt' => 'after',
        ])
        ->set('code', 'ada spasi!')
        ->call('save')
        ->assertHasErrors(['code' => 'regex']);

    expect(Voucher::count())->toBe(1);
});

it('edits, deactivates, and reactivates a code', function () {
    $voucher = Voucher::factory()->percentage(10)->create(['code' => 'UBAHAKU', 'usage_limit' => 50]);

    Livewire::actingAs($this->admin)
        ->test('pages::admin-vouchers')
        ->call('edit', $voucher->id)
        ->assertSet('editingId', $voucher->id)
        ->assertSet('code', 'UBAHAKU')
        ->assertSet('value', '10')
        ->assertSet('usageLimit', '50')
        ->set('value', '15')
        ->set('maxDiscount', '20000')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('editingId', null)
        ->call('toggle', $voucher->id)
        ->assertSee('Nonaktif');

    // `->value` di rantai expect() adalah nilai expectation-nya sendiri, jadi dibaca langsung.
    expect($voucher->fresh()->value)->toBe('15.00')
        ->and($voucher->fresh())
        ->max_discount->toBe('20000.00')
        ->is_active->toBeFalse()
        ->and($voucher->fresh()->redeemProblem(User::factory()->create(), 500000))->toBe('Kode promo ini sudah tidak berlaku.');

    Livewire::actingAs($this->admin)
        ->test('pages::admin-vouchers')
        ->call('toggle', $voucher->id);

    expect($voucher->fresh()->is_active)->toBeTrue();
});

it('shows how many of the quota are used and stops at the limit', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin-vouchers')
        ->set('code', 'DUAORANG')
        ->set('name', 'Dua pembeli pertama')
        ->set('value', '10')
        ->set('usageLimit', '2')
        ->call('save');

    orderWithCode('DUAORANG');
    orderWithCode('DUAORANG');

    Livewire::actingAs($this->admin)
        ->test('pages::admin-vouchers')
        ->assertSee('2 / 2')
        ->assertSee('Kuota habis');

    expect(fn () => orderWithCode('DUAORANG'))->toThrow(ValidationException::class);
});

it('deletes only codes that were never used', function () {
    $unused = Voucher::factory()->create(['code' => 'BELUMDIPAKAI']);
    $used = Voucher::factory()->create(['code' => 'SUDAHDIPAKAI']);
    orderWithCode('SUDAHDIPAKAI');

    Livewire::actingAs($this->admin)
        ->test('pages::admin-vouchers')
        ->call('delete', $unused->id)
        ->assertDispatched('notify');

    expect(Voucher::find($unused->id))->toBeNull();

    Livewire::actingAs($this->admin)
        ->test('pages::admin-vouchers')
        ->call('delete', $used->id)
        ->assertStatus(409);

    expect(Voucher::find($used->id))->not->toBeNull();
});

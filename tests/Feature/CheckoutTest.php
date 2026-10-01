<?php

use Livewire\Livewire;

/**
 * Data alamat + kartu yang valid, dipakai sebagai titik awal tiap skenario.
 *
 * @return array<string, string>
 */
function checkoutFields(): array
{
    return [
        'email' => 'jane@example.com',
        'firstName' => 'Jane',
        'lastName' => 'Doe',
        'address' => '123 Main St, Apt 4B',
        'city' => 'New York',
        'postalCode' => '10001',
        'country' => 'Indonesia',
        'cardNumber' => '4111 1111 1111 1111',
        'cardName' => 'JANE DOE',
        'cardExpiry' => '12/28',
        'cardCvv' => '123',
    ];
}

it('menghitung ongkir dan total sesuai metode pengiriman yang dipilih', function () {
    Livewire::test('pages::checkout')
        ->assertSee('$875.00')
        ->assertSee('$900.00')
        ->set('shippingMethod', 'express')
        ->assertSee('$925.00');
});

it('mengarahkan ke halaman sukses setelah pesanan dibuat', function () {
    Livewire::test('pages::checkout')
        ->set(checkoutFields())
        ->call('placeOrder')
        ->assertHasNoErrors()
        ->assertRedirect(route('checkout.success'));

    expect(session('checkout.order'))
        ->toHaveKeys(['id', 'placed_at', 'total', 'email', 'delivery_window'])
        ->and(session('checkout.order')['total'])->toBe(900.0);
});

it('mewajibkan data alamat sebelum pesanan dibuat', function () {
    Livewire::test('pages::checkout')
        ->call('placeOrder')
        ->assertHasErrors(['email', 'firstName', 'lastName', 'address', 'city', 'postalCode']);
});

it('mewajibkan detail kartu hanya saat metode kartu dipilih', function () {
    Livewire::test('pages::checkout')
        ->set(checkoutFields())
        ->set('cardNumber', '')
        ->set('cardExpiry', '1228')
        ->call('placeOrder')
        ->assertHasErrors(['cardNumber', 'cardExpiry']);

    Livewire::test('pages::checkout')
        ->set(checkoutFields())
        ->set('paymentMethod', 'transfer')
        ->set('cardNumber', '')
        ->call('placeOrder')
        ->assertHasNoErrors()
        ->assertRedirect(route('checkout.success'));
});

it('menampilkan ringkasan pesanan hasil checkout di halaman sukses', function () {
    $this->withSession(['checkout.order' => [
        'id' => 'FM-2026-ABCD',
        'placed_at' => 'Sep 22, 2026',
        'total' => 925.00,
        'email' => 'jane@example.com',
        'delivery_window' => 'September 23–24, 2026',
    ]])->get(route('checkout.success'))
        ->assertOk()
        ->assertSee('#FM-2026-ABCD')
        ->assertSee('$925.00')
        ->assertSee('September 23–24, 2026', escape: false);
});

<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

// Beranda sekarang membaca produk terbaru, jadi butuh skema database.
uses(RefreshDatabase::class);

test('the application returns a successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

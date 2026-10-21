<?php

use App\Models\EscrowHold;
use App\Models\Order;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('orders:expire-unpaid', function () {
    $this->info(Order::expireOverdue().' pesanan kedaluwarsa dibatalkan.');
})->purpose('Batalkan pesanan yang lewat batas bayar dan kembalikan stoknya')->everyFiveMinutes();

Artisan::command('escrow:release-due', function () {
    $this->info(EscrowHold::releaseDue().' paket diselesaikan dan dananya dicairkan.');
})->purpose('Cairkan escrow yang lewat batas konfirmasi pembeli dan tidak disengketakan')->hourly();

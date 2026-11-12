<?php

namespace App\Notifications;

use App\Models\StoreOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi in-app ke pembeli saat status paket per toko berubah (dikemas,
 * dikirim, sampai, dibatalkan, dana dikembalikan). Dikirim dari
 * `StoreOrder::transitionTo()`, jadi ikut batal kalau transaksinya gagal.
 *
 * Isinya salinan saat kejadian (seperti nota), jadi teks lama tidak berubah
 * kalau nama toko diganti.
 */
class PackageStatusChanged extends Notification
{
    use Queueable;

    /**
     * Status paket yang dikabarkan ke pembeli => judul notifikasi.
     *
     * @var array<string, string>
     */
    public const TITLES = [
        'processing' => 'Pesanan sedang dikemas',
        'shipped' => 'Pesanan dikirim',
        'delivered' => 'Pesanan sudah sampai',
        'cancelled' => 'Pesanan dibatalkan',
        'refunded' => 'Dana dikembalikan',
    ];

    /**
     * @param  array{store_order_id: int, order_id: int, order_number: string, store_order_number: string, store_name: string, status: string, title: string, message: string}  $payload
     */
    public function __construct(public array $payload) {}

    public static function for(StoreOrder $storeOrder): self
    {
        $storeName = $storeOrder->store?->displayName() ?? 'Penjual';
        $number = $storeOrder->store_order_number;
        $shipment = $storeOrder->shipment()->first();

        $message = match ($storeOrder->status) {
            'processing' => "{$storeName} sedang mengemas paket {$number}. Nomor resi muncul begitu paket diserahkan ke kurir.",
            'shipped' => "Paket {$number} dari {$storeName} sudah diserahkan ke kurir {$shipment?->courierLabel()}".($shipment?->tracking_number ? " dengan nomor resi {$shipment->tracking_number}." : '.'),
            'delivered' => "Paket {$number} dari {$storeName} sudah sampai. Tekan \"Pesanan Diterima\" kalau barangnya sesuai.",
            'cancelled' => "Paket {$number} dari {$storeName} dibatalkan".($storeOrder->note ? ": {$storeOrder->note}" : '.').($storeOrder->order->isPaid() ? ' Dananya dikembalikan ke saldo FitMate-mu.' : ''),
            'refunded' => "Dana paket {$number} sebesar Rp ".number_format((float) $storeOrder->total, 0, ',', '.').' dikembalikan ke saldo FitMate-mu.',
            default => "Status paket {$number} berubah.",
        };

        return new self([
            'store_order_id' => $storeOrder->id,
            'order_id' => $storeOrder->order_id,
            'order_number' => $storeOrder->order->order_number,
            'store_order_number' => $number,
            'store_name' => $storeName,
            'status' => $storeOrder->status,
            'title' => self::TITLES[$storeOrder->status] ?? 'Status pesanan berubah',
            'message' => $message,
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->payload;
    }
}

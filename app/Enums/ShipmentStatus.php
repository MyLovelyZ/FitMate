<?php

namespace App\Enums;

enum ShipmentStatus: string
{
    case Pending = 'pending';
    case PickedUp = 'picked_up';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Returned = 'returned';

    /**
     * Label bahasa Indonesia untuk ditampilkan ke pengguna.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Penjemputan',
            self::PickedUp => 'Sudah Dijemput',
            self::InTransit => 'Dalam Perjalanan',
            self::Delivered => 'Terkirim',
            self::Failed => 'Gagal Kirim',
            self::Returned => 'Dikembalikan',
        };
    }

    /**
     * Peta nilai => label, siap dipakai komponen <x-ui.select :options="...">.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}

<?php

namespace App\Enums;

enum StoreOrderStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    /**
     * Label bahasa Indonesia untuk ditampilkan ke pengguna.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Diproses',
            self::Processing => 'Diproses',
            self::Shipped => 'Dikirim',
            self::Delivered => 'Sampai Tujuan',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
            self::Refunded => 'Dana Dikembalikan',
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

<?php

namespace App\Enums;

enum SizeType: string
{
    case Top = 'top';
    case Bottom = 'bottom';
    case Footwear = 'footwear';
    case None = 'none';

    /**
     * Label bahasa Indonesia untuk ditampilkan ke pengguna.
     */
    public function label(): string
    {
        return match ($this) {
            self::Top => 'Atasan',
            self::Bottom => 'Bawahan',
            self::Footwear => 'Alas Kaki',
            self::None => 'Tanpa Ukuran',
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

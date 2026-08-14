<?php

namespace App\Enums;

enum CategoryType: string
{
    case Clothing = 'clothing';
    case Footwear = 'footwear';
    case Accessories = 'accessories';

    /**
     * Label bahasa Indonesia untuk ditampilkan ke pengguna.
     */
    public function label(): string
    {
        return match ($this) {
            self::Clothing => 'Pakaian',
            self::Footwear => 'Alas Kaki',
            self::Accessories => 'Aksesoris',
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

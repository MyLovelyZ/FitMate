<?php

namespace App\Enums;

enum MeasurementScope: string
{
    case General = 'general';
    case Top = 'top';
    case Bottom = 'bottom';
    case Footwear = 'footwear';

    /**
     * Label bahasa Indonesia untuk ditampilkan ke pengguna.
     */
    public function label(): string
    {
        return match ($this) {
            self::General => 'Umum',
            self::Top => 'Atasan',
            self::Bottom => 'Bawahan',
            self::Footwear => 'Alas Kaki',
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

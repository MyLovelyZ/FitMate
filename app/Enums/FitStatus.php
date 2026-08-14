<?php

namespace App\Enums;

enum FitStatus: string
{
    case Tight = 'tight';
    case Fit = 'fit';
    case Loose = 'loose';

    /**
     * Label bahasa Indonesia untuk ditampilkan ke pengguna.
     */
    public function label(): string
    {
        return match ($this) {
            self::Tight => 'Cenderung sempit',
            self::Fit => 'Pas untukmu',
            self::Loose => 'Cenderung longgar',
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

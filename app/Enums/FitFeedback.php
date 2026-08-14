<?php

namespace App\Enums;

enum FitFeedback: string
{
    case TooSmall = 'too_small';
    case SlightlySmall = 'slightly_small';
    case TrueToSize = 'true_to_size';
    case SlightlyLarge = 'slightly_large';
    case TooLarge = 'too_large';

    /**
     * Label bahasa Indonesia untuk ditampilkan ke pengguna.
     */
    public function label(): string
    {
        return match ($this) {
            self::TooSmall => 'Kekecilan',
            self::SlightlySmall => 'Sedikit kecil',
            self::TrueToSize => 'Sesuai ukuran',
            self::SlightlyLarge => 'Sedikit besar',
            self::TooLarge => 'Kebesaran',
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

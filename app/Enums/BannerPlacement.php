<?php

namespace App\Enums;

enum BannerPlacement: string
{
    case HomeHero = 'home_hero';
    case HomeMiddle = 'home_middle';
    case Category = 'category';
    case PromoPage = 'promo_page';

    /**
     * Label bahasa Indonesia untuk ditampilkan ke pengguna.
     */
    public function label(): string
    {
        return match ($this) {
            self::HomeHero => 'Beranda Atas',
            self::HomeMiddle => 'Beranda Tengah',
            self::Category => 'Halaman Kategori',
            self::PromoPage => 'Halaman Promo',
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

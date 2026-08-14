<?php

namespace App\Enums;

enum UserRole: string
{
    case User = 'user';
    case Seller = 'seller';
    case CustomerService = 'customerservice';
    case Admin = 'admin';
    case Superadmin = 'superadmin';

    /**
     * Label bahasa Indonesia untuk ditampilkan ke pengguna.
     */
    public function label(): string
    {
        return match ($this) {
            self::User => 'Pembeli',
            self::Seller => 'Penjual',
            self::CustomerService => 'Customer Service',
            self::Admin => 'Admin',
            self::Superadmin => 'Superadmin',
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

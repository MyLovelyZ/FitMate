<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case BankTransfer = 'bank_transfer';
    case VirtualAccount = 'virtual_account';
    case Ewallet = 'ewallet';
    case CreditCard = 'credit_card';
    case Cod = 'cod';

    /**
     * Label bahasa Indonesia untuk ditampilkan ke pengguna.
     */
    public function label(): string
    {
        return match ($this) {
            self::BankTransfer => 'Transfer Bank',
            self::VirtualAccount => 'Virtual Account',
            self::Ewallet => 'E-Wallet',
            self::CreditCard => 'Kartu Kredit',
            self::Cod => 'Bayar di Tempat',
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

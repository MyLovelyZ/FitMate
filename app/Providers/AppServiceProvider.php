<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Locale `id` belum punya file terjemahan, jadi isi email reset ditulis langsung.
        ResetPassword::toMailUsing(function (object $notifiable, string $token): MailMessage {
            $url = route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()]);

            return (new MailMessage)
                ->subject('Reset Kata Sandi FitMate')
                ->greeting('Halo '.$notifiable->name.',')
                ->line('Kami menerima permintaan untuk mengganti kata sandi akun FitMate-mu.')
                ->action('Buat Kata Sandi Baru', $url)
                ->line('Link ini berlaku '.config('auth.passwords.users.expire').' menit.')
                ->line('Kalau kamu tidak meminta reset, abaikan email ini. Kata sandimu tidak berubah.')
                ->salutation('Salam, Tim FitMate');
        });
    }
}

<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
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
        // El frontend es HTML/JS estático servido por esta misma app, no hay rutas
        // Blade con nombre "password.reset": apuntamos el link del mail ahí directo.
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return rtrim(config('app.url'), '/').'/app/reset-password.html?token='.$token.'&email='.urlencode($notifiable->getEmailForPasswordReset());
        });
    }
}

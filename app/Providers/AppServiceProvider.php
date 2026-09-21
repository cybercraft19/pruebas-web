<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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

        // Un curso entero suele salir a internet por la misma IP (la del colegio), así que el
        // tope por IP tiene que ser holgado: lo que frena la fuerza bruta es el límite por
        // cuenta (correo + IP), no el de la IP sola.
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(Str::lower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(300)->by($request->ip()),
        ]);

        RateLimiter::for('registro', fn (Request $request) => [
            Limit::perMinute(60)->by($request->ip()),
            Limit::perHour(400)->by($request->ip()),
        ]);
    }
}

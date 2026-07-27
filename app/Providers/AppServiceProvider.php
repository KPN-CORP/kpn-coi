<?php

namespace App\Providers;

use App\Listeners\LogSentEmail;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        if (env('APP_ENV') === 'production') {
            URL::forceScheme('https');
        }
        Vite::prefetch(concurrency: 3);

        // Standard password policy, enforced anywhere Password::defaults() is
        // used (self-service reset). Keep this in sync with the requirement
        // checklist shown on Pages/Auth/ResetPassword.vue.
        Password::defaults(fn () => Password::min(8)
            ->mixedCase()
            ->numbers()
            ->symbols());

        // Log every successfully sent email to the dedicated "email" channel.
        Event::listen(MessageSent::class, LogSentEmail::class);
    }
}

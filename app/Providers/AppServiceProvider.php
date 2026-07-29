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

            // cPanel terminates TLS and forwards plain HTTP without a trusted
            // X-Forwarded-Proto header, so trustProxies can't recover the
            // scheme. forceScheme() only fixes the UrlGenerator (route()/url()/
            // redirects) — the paginator builds its links from the raw request
            // ($request->url()), which stays http and gets blocked as mixed
            // content. Mark the request secure directly; production is always
            // served over https. This makes $request->url() report https too.
            if ($request = request()) {
                $request->server->set('HTTPS', 'on');
            }
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

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
        // Force https everywhere except explicit local dev. Gating on
        // env('APP_ENV') === 'production' is fragile: if the live .env isn't
        // spelled exactly "production" (or env() returns null), nothing runs
        // and links come out http. environment('local') reads config('app.env')
        // (cache-safe) and defaults to production when unset, so live is always
        // covered.
        //
        // cPanel terminates TLS and forwards plain HTTP without a trusted
        // X-Forwarded-Proto header, so trustProxies can't recover the scheme.
        // forceScheme() only fixes the UrlGenerator (route()/url()/redirects) —
        // the paginator builds its links from the raw request ($request->url()),
        // which stays http and gets blocked as mixed content. Marking the
        // request secure makes $request->url() report https too.
        if (! $this->app->environment('local')) {
            URL::forceScheme('https');

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

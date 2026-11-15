<?php

namespace App\Providers;

use App\Cart\Cart;
use Illuminate\Mail\Markdown;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(Cart::class, fn ($app) => new Cart($app['session.store']));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Mails interpolate what customers typed; without this, Markdown in a name becomes a link.
        Markdown::withSecuredEncoding();
    }
}

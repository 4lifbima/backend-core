<?php

namespace App\Providers;

use App\Models\Artwork;
use App\Policies\ArtworkPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        Gate::policy(Artwork::class, ArtworkPolicy::class);

        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perMinutes(10, 15)->by($request->ip());
        });

        RateLimiter::for('likes', function (Request $request) {
            $token = (string) ($request->cookie('uk_visitor_token') ?? $request->header('X-Visitor-Token') ?? '');

            return [
                Limit::perMinute(30)->by($request->ip()),
                Limit::perMinute(20)->by($request->ip().'|'.$token),
            ];
        });

        RateLimiter::for('login', function (Request $request) {
            $email = mb_strtolower((string) $request->input('email', ''));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });
    }
}

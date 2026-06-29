<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class RateLimitServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('auth-login', function (Request $request) {
            $config = config('rate-limits.auth-login');

            return Limit::perSecond($config['limit'], $config['period'])->by(
                $request->input('identifier', '').'|'.$request->ip()
            );
        });

        RateLimiter::for('auth-verify-otp', function (Request $request) {
            $config = config('rate-limits.auth-verify-otp');

            return Limit::perSecond($config['period'], $config['limit'])->by(
                $request->input('identifier', '').'|'.$request->ip()
            );
        });
    }
}

<?php

namespace App\Providers;

use Closure;
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

            return Limit::perSecond($config['limit'], $config['period'])
                ->by($request->input('identifier', '').'|'.$request->ip())
                ->response($this->tooManyAttemptsResponse());
        });

        RateLimiter::for('auth-verify-otp', function (Request $request) {
            $config = config('rate-limits.auth-verify-otp');

            return Limit::perSecond($config['limit'], $config['period'])
                ->by($request->input('identifier', '').'|'.$request->ip())
                ->response($this->tooManyAttemptsResponse());
        });

        RateLimiter::for('recruitment-otp-send', function (Request $request) {
            $config = config('rate-limits.recruitment-otp-send');

            return Limit::perSecond($config['limit'], $config['period'])
                ->by($request->route('uuid', '').'|send|'.$request->ip())
                ->response($this->tooManyAttemptsResponse());
        });

        RateLimiter::for('recruitment-otp-verify', function (Request $request) {
            $config = config('rate-limits.recruitment-otp-verify');

            return Limit::perSecond($config['limit'], $config['period'])
                ->by($request->route('uuid', '').'|verify|'.$request->ip())
                ->response($this->tooManyAttemptsResponse());
        });
    }

    /**
     * Build the localized 429 response for route-level rate limiters.
     *
     * Includes `retry_after` (seconds) in the body in addition to the
     * `Retry-After` header so both the frontend hook and external clients
     * can start the cooldown countdown.
     */
    private function tooManyAttemptsResponse(): Closure
    {
        return function (Request $request, array $headers) {
            return response()->json([
                'message' => __('messages.too_many_attempts'),
                'retry_after' => (int) ($headers['Retry-After'] ?? 0),
            ], 429, $headers);
        };
    }
}

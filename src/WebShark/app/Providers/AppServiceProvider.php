<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

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
        $this->configureDefaults();
        RateLimiter::for('pcap-uploads', function (Request $request) {
            $sessionKey = $request->session()->getId();
            $ip = $request->ip();

            if (!$request->hasCookie(config('session.cookie'))) {
                Log::channel('audit')->warning('NO_COOKIES_REJECTED', [
                    'ip' => $ip,
                    'session' => 'NONE',
                ]);
                return Limit::none()->response(fn() => redirect('/?error=cookies_required'));
            }

            return [ 
                // Limit 1 is per session (let's say honest user)
                // 10 req per 15 minutes
                Limit::perMinutes(15, 10)
                    ->by($sessionKey)
                    ->response(function (Request $request) use ($sessionKey) {
                        Log::channel('audit')->warning('RATE_LIMIT_SESSION', [
                            'ip' => $request->ip(),
                            'session' => $sessionKey,
                        ]);

                        $this->applyProgressiveBlock("session:{$sessionKey}", [
                            'ip' => $request->ip(),
                            'session' => $sessionKey,
                            'type' => 'SESSION',
                        ]);

                        $blockedUntil = Cache::get("pcap_block:session:{$sessionKey}");
                        $wait = $blockedUntil
                            ? now()->diffForHumans(Carbon::parse($blockedUntil), true)
                            : '15 minutes';

                        return redirect()->back()->with('error', "Personal limit exceeded. Try again in {$wait}.");
                    }),

                // Limit 2 is per IP (let's say the fallback for the whole University because attacker can still clear session cookies)  
                // 200 req per 15 minutes
                Limit::perMinutes(15, 200)
                    ->by($ip)
                    ->response(function (Request $request) use ($ip, $sessionKey) {
                        Log::channel('audit')->warning('RATE_LIMIT_IP', [
                            'ip' => $ip,
                            'session' => $sessionKey,
                        ]);

                        $this->applyProgressiveBlock("ip:{$ip}", [
                            'ip' => $ip,
                            'session' => $sessionKey,
                            'type' => 'IP',
                        ]);

                        $blockedUntil = Cache::get("pcap_block:ip:{$ip}");
                        $wait = $blockedUntil
                            ? now()->diffForHumans(Carbon::parse($blockedUntil), true)
                            : '15 minutes';

                        return redirect()->back()->with('error', "High traffic from this network. Try again in {$wait}.");
                    }),
            ];
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }

    /**
     * Record a violation and apply exponential backoff block.
     * Sequence: 15m -> 30m -> 1h -> 2h -> 4h -> ... -> 24h
     */
    private function applyProgressiveBlock(string $key, array $context): void
    {

        $violationKey = "pcap_violations:{$key}";
        $blockKey = "pcap_block:{$key}";

        // Violations decay after 24 hours of clean behaviour
        $violations = Cache::get($violationKey, 0) + 1;
        Cache::put($violationKey, $violations, now()->addHours(24));

        // 15 min * 2^(n-1), capped at 24 hours
        $blockMinutes = min(15 * (2 ** ($violations - 1)), 1440);
        $blockedUntil = now()->addMinutes($blockMinutes);
        Cache::put($blockKey, $blockedUntil->toIso8601String(), $blockedUntil);

        Log::channel('audit')->warning('PROGRESSIVE_BLOCK_APPLIED', array_merge($context, [
            'violation_count' => $violations,
            'block_minutes' => $blockMinutes,
            'blocked_until' => $blockedUntil->toIso8601String(),
        ]));
    }
}

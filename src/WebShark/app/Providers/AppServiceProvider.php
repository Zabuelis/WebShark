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
            return [
                // Limit 1 is per session (let's say honest user)
                // 10 req per 15 minutes
                Limit::perMinutes(15, 10)
                    ->by($request->session()->getId())
                    ->response(function (Request $request, array $headers) {
                        $seconds = $headers['Retry-After'] ?? 0;
                        $wait = Carbon::now()->addSeconds($seconds)->diffForHumans(null, true);
                        return redirect()->back()->with('error', "You have reached your personal limit. Please try again in $wait.");
                    }),

                // Limit 2 is per IP (let's say the fallback for the whole University because attacker can still clear session cookies)  
                // 200 req per 15 minutes
                Limit::perMinutes(15, 200)
                    ->by($request->ip())
                    ->response(function (Request $request, array $headers) {
                        $seconds = $headers['Retry-After'] ?? 0;
                        $wait = Carbon::now()->addSeconds($seconds)->diffForHumans(null, true);
                        return redirect()->back()->with('error', "High traffic from this network. Please try again in $wait.");
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
}

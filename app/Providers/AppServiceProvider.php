<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->configureDefaults();

        foreach (['map-read' => 60, 'walking-routes' => 10, 'sos-preparation' => 20] as $limiter => $maximum) {
            RateLimiter::for($limiter, function (Request $request) use ($maximum): Limit {
                $identity = $request->user() ? 'user:'.$request->user()->id : 'visitor:'.$request->ip();
                $key = hash_hmac('sha256', now()->format('Y-m-d').'|'.$identity, config()->string('app.key'));

                return Limit::perMinute($maximum)->by($key);
            });
        }
        RateLimiter::for('report-submission', fn (Request $request): Limit => Limit::perHour(5)
            ->by(hash_hmac('sha256', 'reporter:'.$request->user()->id, config()->string('app.key'))));
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
            : null,
        );
    }
}

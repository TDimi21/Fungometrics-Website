<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        $this->routes(function (): void {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request): Limit {
            $userId = $request->user()?->id;

            return $userId
                ? Limit::perMinute(300)->by('api:user:' . $userId)
                : Limit::perMinute(60)->by('api:ip:' . $request->ip());
        });

        // Session writes also have a dedicated authenticated ceiling. The
        // larger authenticated API bucket prevents normal read bursts from
        // starving creates/scores while this limiter still caps write abuse.
        RateLimiter::for('session-write', fn (Request $request): Limit => Limit::perMinute(120)
            ->by('session-write:user:' . $request->user()->id));
    }
}

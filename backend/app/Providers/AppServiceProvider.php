<?php

namespace App\Providers;

use App\Events\ExecutionNodeFailed;
use App\Listeners\AlertOnWorkspaceFailureSpike;
use App\Listeners\SendExecutionFailedNotification;
use App\Listeners\SendNewSignupAdminAlert;
use App\Models\Variable;
use App\Observers\VariableObserver;
use Carbon\CarbonInterval;
use Illuminate\Auth\Events\Registered;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Cashier\Cashier;
use Laravel\Passport\Passport;

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
        Variable::observe(VariableObserver::class);

        // ── Notification Event Listeners ─────────────────────────────────
        Event::listen(ExecutionNodeFailed::class, SendExecutionFailedNotification::class);
        Event::listen(ExecutionNodeFailed::class, AlertOnWorkspaceFailureSpike::class);
        Event::listen(Registered::class, SendNewSignupAdminAlert::class);

        Password::defaults(fn () => Password::min(8)->mixedCase()->numbers()->symbols());

        $this->configureRateLimiting();

        Passport::tokensExpireIn(CarbonInterval::days(15));
        Passport::refreshTokensExpireIn(CarbonInterval::days(30));
        Passport::personalAccessTokensExpireIn(CarbonInterval::months(6));
        Passport::enablePasswordGrant();

        Cashier::useCustomerModel(\App\Models\Workspace::class);
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('workspace-api', function (Request $request) {
            $userId = $request->user()?->id ?? $request->ip();
            $workspaceId = $request->route('workspace')?->id ?? 'global';

            return Limit::perMinute(120)->by("{$userId}:{$workspaceId}");
        });

        RateLimiter::for('execution-trigger', function (Request $request) {
            $workspaceId = $request->route('workspace')?->id ?? 'global';

            return Limit::perMinute(30)->by("exec:{$workspaceId}");
        });

        RateLimiter::for('webhook-receive', function (Request $request) {
            return [
                // Per-UUID: prevents a single webhook from being hammered
                Limit::perMinute(60)->by('webhook-uuid:'.$request->route('uuid')),
                // Per-IP: prevents UUID brute-force / discovery across all webhooks
                Limit::perMinute(300)->by('webhook-ip:'.$request->ip()),
            ];
        });
    }
}

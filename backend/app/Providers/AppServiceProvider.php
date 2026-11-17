<?php

namespace App\Providers;

use App\Models\Booking;
use App\Models\Building;
use App\Models\Package;
use App\Models\Room;
use App\Models\TransferFixedRoute;
use App\Models\TransferHub;
use App\Models\Trip;
use App\Models\User;
use App\Services\CurrencyService;
use App\Services\McpTokenService;
use App\Services\NationalityService;
use App\Services\Pricing\CommissionResolver;
use App\Services\SettingsService;
use App\Services\Transfers\DistanceProvider;
use App\Services\Transfers\HaversineDistanceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrencyService::class);
        $this->app->scoped(SettingsService::class);
        $this->app->scoped(NationalityService::class);
        $this->app->scoped(CommissionResolver::class);
        $this->app->bind(DistanceProvider::class, fn (): DistanceProvider => new HaversineDistanceProvider(
            (float) config('transfers.road_factor', 1.3),
            (float) config('transfers.average_speed_kmh', 60),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap([
            'user' => User::class,
            'trip' => Trip::class,
            'package' => Package::class,
            'building' => Building::class,
            'room' => Room::class,
            'booking' => Booking::class,
            'transfer_hub' => TransferHub::class,
            'transfer_fixed_route' => TransferFixedRoute::class,
        ]);

        Gate::define('manage-prices', fn (User $user): bool => $user->role?->canManagePrices() ?? false);
        Gate::define('manage-settings', fn (User $user): bool => $user->role?->canManageSettings() ?? false);

        RateLimiter::for('admin-login', fn (Request $request): Limit => Limit::perMinute(5)
            ->by(strtolower((string) $request->input('email')).'|'.$request->ip()));

        // Claude MCP server: 60 calls per minute per token (CR-14).
        RateLimiter::for('mcp', fn (Request $request): Limit => Limit::perMinute(60)
            ->by('mcp|'.($request->user()?->currentAccessToken()?->id ?? $request->ip())));

        // An MCP token only opens /mcp: it never authenticates the website or partner API.
        Sanctum::authenticateAccessTokensUsing(
            fn (PersonalAccessToken $token, bool $isValid): bool => $isValid && (! McpTokenService::isMcpToken($token) || request()->is('mcp')),
        );
    }
}

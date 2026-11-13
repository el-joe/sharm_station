<?php

namespace App\Providers;

use App\Models\Booking;
use App\Models\Building;
use App\Models\Package;
use App\Models\Room;
use App\Models\TransferHub;
use App\Models\Trip;
use App\Models\User;
use App\Services\CurrencyService;
use App\Services\SettingsService;
use App\Services\Transfers\DistanceProvider;
use App\Services\Transfers\HaversineDistanceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
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
        $this->app->scoped(CurrencyService::class);
        $this->app->scoped(SettingsService::class);
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
        ]);

        Gate::define('manage-prices', fn (User $user): bool => $user->role?->canManagePrices() ?? false);
        Gate::define('manage-settings', fn (User $user): bool => $user->role?->canManageSettings() ?? false);

        RateLimiter::for('admin-login', fn (Request $request): Limit => Limit::perMinute(5)
            ->by(strtolower((string) $request->input('email')).'|'.$request->ip()));
    }
}

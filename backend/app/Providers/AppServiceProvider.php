<?php

namespace App\Providers;

use App\Models\Addon;
use App\Models\AiSuggestion;
use App\Models\BlogPost;
use App\Models\Booking;
use App\Models\Building;
use App\Models\Coupon;
use App\Models\Currency;
use App\Models\Destination;
use App\Models\FaqCategory;
use App\Models\FaqItem;
use App\Models\NationalityGroup;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\Package;
use App\Models\Page;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentProof;
use App\Models\Room;
use App\Models\RoomOption;
use App\Models\Setting;
use App\Models\SlugRedirect;
use App\Models\TransferFixedRoute;
use App\Models\TransferHub;
use App\Models\Trip;
use App\Models\User;
use App\Models\VehicleType;
use App\Services\CurrencyService;
use App\Services\McpTokenService;
use App\Services\NationalityService;
use App\Services\Pricing\CommissionResolver;
use App\Services\SettingsService;
use App\Services\Transfers\DistanceProvider;
use App\Services\Transfers\GoogleRoutesDistanceProvider;
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
        $this->app->bind(HaversineDistanceProvider::class, fn (): HaversineDistanceProvider => new HaversineDistanceProvider(
            (float) config('transfers.road_factor', 1.3),
            (float) config('transfers.average_speed_kmh', 60),
        ));
        $this->app->bind(DistanceProvider::class, function (): DistanceProvider {
            $driver = (string) config('transfers.distance_driver', 'auto');
            $serverKey = config('services.google.maps_server_key');

            if ($driver === 'haversine' || ($driver !== 'google' && blank($serverKey))) {
                return $this->app->make(HaversineDistanceProvider::class);
            }

            return new GoogleRoutesDistanceProvider(
                $this->app->make(HaversineDistanceProvider::class),
                filled($serverKey) ? (string) $serverKey : null,
                (string) config('services.google.routes_endpoint', 'https://routes.googleapis.com/directions/v2:computeRoutes'),
                (int) config('services.google.routes_timeout', 5),
            );
        });
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
            'room_option' => RoomOption::class,
            'booking' => Booking::class,
            'transfer_hub' => TransferHub::class,
            'transfer_fixed_route' => TransferFixedRoute::class,
            'destination' => Destination::class,
            'addon' => Addon::class,
            'payment_method' => PaymentMethod::class,
            'payment' => Payment::class,
            'payment_proof' => PaymentProof::class,
            'vehicle_type' => VehicleType::class,
            'nationality_group' => NationalityGroup::class,
            'currency' => Currency::class,
            'setting' => Setting::class,
            'partner' => Partner::class,
            'ai_suggestion' => AiSuggestion::class,
            'coupon' => Coupon::class,
            'page' => Page::class,
            'blog_post' => BlogPost::class,
            'faq_category' => FaqCategory::class,
            'faq_item' => FaqItem::class,
            'notification_template' => NotificationTemplate::class,
            'notification_log' => NotificationLog::class,
            'slug_redirect' => SlugRedirect::class,
        ]);

        Gate::define('manage-prices', fn (User $user): bool => $user->role?->canManagePrices() ?? false);
        Gate::define('manage-settings', fn (User $user): bool => $user->role?->canManageSettings() ?? false);
        Gate::define('manage-content', fn (User $user): bool => $user->role?->canManageContent() ?? false);
        Gate::define('view-reports', fn (User $user): bool => $user->role?->canViewReports() ?? false);
        Gate::define('manage-users', fn (User $user): bool => $user->role?->canManageUsers() ?? false);

        RateLimiter::for('admin-login', fn (Request $request): Limit => Limit::perMinute(5)
            ->by(strtolower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('admin-two-factor', fn (Request $request): Limit => Limit::perMinute(5)
            ->by('2fa|'.($request->session()->get('admin.two_factor_login')['user_id'] ?? $request->ip())));

        // Claude MCP server: 60 calls per minute per token (CR-14).
        RateLimiter::for('mcp', fn (Request $request): Limit => Limit::perMinute(60)
            ->by('mcp|'.($request->user()?->currentAccessToken()?->id ?? $request->ip())));

        // An MCP token only opens /mcp: it never authenticates the website or partner API. Deactivated accounts lose their tokens' access.
        Sanctum::authenticateAccessTokensUsing(
            fn (PersonalAccessToken $token, bool $isValid): bool => $isValid
                && ($token->tokenable?->getAttribute('is_active') ?? true) !== false
                && (! McpTokenService::isMcpToken($token) || request()->is('mcp')),
        );
    }
}

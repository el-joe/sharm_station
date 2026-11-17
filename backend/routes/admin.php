<?php

use App\Http\Controllers\Admin\AddonController;
use App\Http\Controllers\Admin\AiSuggestionController;
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\BuildingController;
use App\Http\Controllers\Admin\ComingSoonController;
use App\Http\Controllers\Admin\CommissionMatrixController;
use App\Http\Controllers\Admin\CommissionSettingController;
use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\CurrencyController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DestinationController;
use App\Http\Controllers\Admin\GoogleReviewController;
use App\Http\Controllers\Admin\McpAccessController;
use App\Http\Controllers\Admin\NationalityGroupController;
use App\Http\Controllers\Admin\PackageController;
use App\Http\Controllers\Admin\PartnerController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\RoomAvailabilityController;
use App\Http\Controllers\Admin\RoomController;
use App\Http\Controllers\Admin\SeasonPriceController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TransferController;
use App\Http\Controllers\Admin\TripController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin panel (prefix /admin, name prefix "admin.", "web" middleware group)
|--------------------------------------------------------------------------
| Registered in bootstrap/app.php. Staff roles only (EnsureStaffRole); editors
| cannot reach sales/finance routes nor change prices (gate "manage-prices").
*/

Route::middleware('guest')->group(function (): void {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:admin-login')->name('login.store');
});

Route::middleware(['auth', 'staff'])->group(function (): void {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
    Route::redirect('/', '/admin/dashboard')->name('home');
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Catalogue
    Route::get('trips/data', [TripController::class, 'data'])->name('trips.data');
    Route::resource('trips', TripController::class)->except('show');
    Route::get('destinations/data', [DestinationController::class, 'data'])->name('destinations.data');
    Route::resource('destinations', DestinationController::class)->except('show');
    Route::get('packages/data', [PackageController::class, 'data'])->name('packages.data');
    Route::resource('packages', PackageController::class)->except('show');
    Route::get('addons/data', [AddonController::class, 'data'])->name('addons.data');
    Route::resource('addons', AddonController::class)->except('show');

    // Accommodation
    Route::get('buildings/data', [BuildingController::class, 'data'])->name('buildings.data');
    Route::resource('buildings', BuildingController::class)->except('show');
    Route::post('buildings/{building}/reviews/sync', [BuildingController::class, 'syncReviews'])->middleware('throttle:10,1')->name('buildings.reviews.sync');
    Route::get('rooms/data', [RoomController::class, 'data'])->name('rooms.data');
    Route::resource('rooms', RoomController::class)->except('show');
    Route::get('rooms/{room}/calendar', [RoomAvailabilityController::class, 'show'])->name('rooms.calendar');
    Route::post('rooms/{room}/availability', [RoomAvailabilityController::class, 'update'])->name('rooms.availability.update');

    // Price lists (seasons)
    Route::middleware('can:manage-prices')->group(function (): void {
        Route::put('trips/{trip}/seasons', [SeasonPriceController::class, 'updateTrip'])->name('trips.seasons.update');
        Route::put('rooms/{room}/seasons', [SeasonPriceController::class, 'updateRoom'])->name('rooms.seasons.update');
        Route::put('packages/{package}/seasons', [SeasonPriceController::class, 'updatePackage'])->name('packages.seasons.update');
        Route::put('transfers/routes/{fixedRoute}/seasons', [SeasonPriceController::class, 'updateFixedRoute'])->name('transfers.routes.seasons.update');
        Route::post('seasons/{season}/copy-next-year', [SeasonPriceController::class, 'copyToNextYear'])->name('seasons.copy-next-year');
    });

    // Google reviews (content)
    Route::get('google-reviews', [GoogleReviewController::class, 'index'])->name('google-reviews.index');
    Route::put('google-reviews/place', [GoogleReviewController::class, 'updatePlace'])->name('google-reviews.place');
    Route::post('google-reviews/sync', [GoogleReviewController::class, 'sync'])->middleware('throttle:10,1')->name('google-reviews.sync');
    Route::patch('google-reviews/{review}', [GoogleReviewController::class, 'update'])->name('google-reviews.update');

    // AI suggestions from Claude (CR-14): every staff role reviews; creating accommodation needs "manage-prices".
    Route::prefix('ai-suggestions')->name('ai-suggestions.')->controller(AiSuggestionController::class)->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('data', 'data')->name('data');
        Route::get('{suggestion}', 'show')->whereNumber('suggestion')->name('show');
        Route::post('{suggestion}/approve-seo', 'approveSeo')->whereNumber('suggestion')->name('approve-seo');
        Route::post('{suggestion}/approve-accommodation', 'approveAccommodation')->whereNumber('suggestion')->name('approve-accommodation');
        Route::post('{suggestion}/reject', 'reject')->whereNumber('suggestion')->name('reject');
    });

    // Sales and finance: super admins and agents only
    Route::middleware('staff:super_admin,agent')->group(function (): void {
        Route::get('bookings/data', [BookingController::class, 'data'])->name('bookings.data');
        Route::get('bookings/export', [BookingController::class, 'export'])->name('bookings.export');
        Route::get('bookings', [BookingController::class, 'index'])->name('bookings.index');
        Route::get('bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
        Route::patch('bookings/{booking}/status', [BookingController::class, 'updateStatus'])->name('bookings.status');
        Route::post('bookings/{booking}/payments', [PaymentController::class, 'recordPayment'])->name('bookings.payments.store');

        Route::get('contact-messages', [ContactMessageController::class, 'index'])->name('contact-messages.index');
        Route::get('contact-messages/data', [ContactMessageController::class, 'data'])->name('contact-messages.data');
        Route::get('contact-messages/{contactMessage}', [ContactMessageController::class, 'show'])->name('contact-messages.show');

        // Payments (SRS 10)
        Route::controller(PaymentController::class)->group(function (): void {
            Route::get('receipts', 'receipts')->name('receipts.index');
            Route::get('receipts/data', 'receiptsData')->name('receipts.data');
            Route::get('receipts/{proof}', 'showReceipt')->name('receipts.show');
            Route::get('receipts/{proof}/files/{index}', 'receiptFile')->whereNumber('index')->name('receipts.file');
            Route::post('receipts/{proof}/approve', 'approve')->name('receipts.approve');
            Route::post('receipts/{proof}/reject', 'reject')->name('receipts.reject');
            Route::get('payments', 'log')->name('payments.index');
            Route::get('payments/data', 'logData')->name('payments.data');
            Route::get('payment-methods', 'methods')->name('payment-methods.index');
            Route::get('payment-methods/data', 'methodsData')->name('payment-methods.data');
            Route::get('payment-methods/create', 'createMethod')->name('payment-methods.create');
            Route::post('payment-methods', 'storeMethod')->name('payment-methods.store');
            Route::get('payment-methods/{paymentMethod}/edit', 'editMethod')->name('payment-methods.edit');
            Route::put('payment-methods/{paymentMethod}', 'updateMethod')->name('payment-methods.update');
            Route::delete('payment-methods/{paymentMethod}', 'destroyMethod')->name('payment-methods.destroy');
        });

        Route::get('partners/data', [PartnerController::class, 'data'])->name('partners.data');
        Route::get('partners/commissions', [CommissionMatrixController::class, 'edit'])->name('partners.commissions.edit');
        Route::put('partners/commissions', [CommissionMatrixController::class, 'update'])->name('partners.commissions.update');
        Route::get('partners', [PartnerController::class, 'index'])->name('partners.index');
        Route::get('partners/{partner}', [PartnerController::class, 'show'])->name('partners.show');
        Route::put('partners/{partner}/commissions', [PartnerController::class, 'updateCommissions'])->name('partners.commissions.override');

        // Pricing configuration (CR-10, CR-11)
        Route::get('settings/commissions', [CommissionSettingController::class, 'edit'])->name('commissions.edit');
        Route::put('settings/commissions', [CommissionSettingController::class, 'update'])->name('commissions.update');
        Route::get('nationality-groups', [NationalityGroupController::class, 'index'])->name('nationality-groups.index');
        Route::post('nationality-groups', [NationalityGroupController::class, 'store'])->name('nationality-groups.store');
        Route::put('nationality-groups/{nationalityGroup}', [NationalityGroupController::class, 'update'])->name('nationality-groups.update');
        Route::delete('nationality-groups/{nationalityGroup}', [NationalityGroupController::class, 'destroy'])->name('nationality-groups.destroy');
        Route::post('partners/{partner}/approve', [PartnerController::class, 'approve'])->name('partners.approve');
        Route::post('partners/{partner}/suspend', [PartnerController::class, 'suspend'])->name('partners.suspend');

        // Transfers (SRS 5)
        Route::prefix('transfers')->name('transfers.')->controller(TransferController::class)->group(function (): void {
            Route::get('requests', 'requests')->name('requests.index');
            Route::get('requests/data', 'requestsData')->name('requests.data');
            Route::get('requests/{transferRequest}', 'showRequest')->name('requests.show');
            Route::patch('requests/{transferRequest}', 'updateRequest')->name('requests.update');
            Route::patch('included/{task}', 'updateIncludedTransfer')->name('included.update');
            Route::get('simulator', 'simulator')->name('simulator');

            Route::get('hubs', 'hubs')->name('hubs.index');
            Route::get('hubs/data', 'hubsData')->name('hubs.data');
            Route::get('hubs/create', 'createHub')->name('hubs.create');
            Route::post('hubs', 'storeHub')->name('hubs.store');
            Route::get('hubs/{hub}/edit', 'editHub')->name('hubs.edit');
            Route::put('hubs/{hub}', 'updateHub')->name('hubs.update');
            Route::delete('hubs/{hub}', 'destroyHub')->name('hubs.destroy');

            Route::get('vehicles', 'vehicles')->name('vehicles.index');
            Route::get('vehicles/data', 'vehiclesData')->name('vehicles.data');
            Route::get('vehicles/create', 'createVehicle')->name('vehicles.create');
            Route::post('vehicles', 'storeVehicle')->name('vehicles.store');
            Route::get('vehicles/{vehicle}/edit', 'editVehicle')->name('vehicles.edit');
            Route::put('vehicles/{vehicle}', 'updateVehicle')->name('vehicles.update');
            Route::delete('vehicles/{vehicle}', 'destroyVehicle')->name('vehicles.destroy');

            Route::get('routes', 'routes')->name('routes.index');
            Route::get('routes/data', 'routesData')->name('routes.data');
            Route::get('routes/create', 'createRoute')->name('routes.create');
            Route::post('routes', 'storeRoute')->name('routes.store');
            Route::get('routes/{fixedRoute}/edit', 'editRoute')->name('routes.edit');
            Route::put('routes/{fixedRoute}', 'updateRoute')->name('routes.update');
            Route::delete('routes/{fixedRoute}', 'destroyRoute')->name('routes.destroy');
        });

        Route::get('currencies', [CurrencyController::class, 'index'])->name('currencies.index');
        Route::put('currencies', [CurrencyController::class, 'update'])->name('currencies.update');
    });

    Route::middleware('staff:super_admin')->group(function (): void {
        Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');

        // Claude MCP access tokens (CR-14)
        Route::get('settings/mcp', [McpAccessController::class, 'index'])->name('mcp.index');
        Route::post('settings/mcp', [McpAccessController::class, 'store'])->name('mcp.store');
        Route::delete('settings/mcp/{token}', [McpAccessController::class, 'destroy'])->whereNumber('token')->name('mcp.destroy');
    });

    // Modules from the SRS that are not built yet render an honest placeholder page.
    Route::get('soon/{module}', ComingSoonController::class)
        ->whereIn('module', ['packages', 'calendar', 'receipts', 'payments', 'customers', 'coupons', 'transfers', 'content', 'users'])
        ->name('soon');
});

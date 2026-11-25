<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AddonController;
use App\Http\Controllers\Admin\AiSuggestionController;
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Admin\BlogPostController;
use App\Http\Controllers\Admin\BookingCancellationController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\BookingEditorController;
use App\Http\Controllers\Admin\BookingRefundController;
use App\Http\Controllers\Admin\BuildingController;
use App\Http\Controllers\Admin\CalendarController;
use App\Http\Controllers\Admin\CommissionMatrixController;
use App\Http\Controllers\Admin\CommissionSettingController;
use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\CurrencyController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DestinationController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\GoogleReviewController;
use App\Http\Controllers\Admin\McpAccessController;
use App\Http\Controllers\Admin\NationalityGroupController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PackageController;
use App\Http\Controllers\Admin\PartnerController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RoomAvailabilityController;
use App\Http\Controllers\Admin\RoomController;
use App\Http\Controllers\Admin\RoomIcalController;
use App\Http\Controllers\Admin\RoomOptionController;
use App\Http\Controllers\Admin\SeasonPriceController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\StaffUserController;
use App\Http\Controllers\Admin\TransferController;
use App\Http\Controllers\Admin\TripController;
use App\Http\Controllers\Admin\TwoFactorController;
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
    Route::get('two-factor-challenge', [TwoFactorChallengeController::class, 'create'])->name('two-factor.challenge');
    Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'store'])->middleware('throttle:admin-two-factor')->name('two-factor.challenge.store');
});

Route::middleware(['auth', 'staff', 'two-factor'])->group(function (): void {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    // Own two-factor authentication (SRS 14.2)
    Route::get('security/two-factor', [TwoFactorController::class, 'show'])->name('two-factor.show');
    Route::post('security/two-factor', [TwoFactorController::class, 'enable'])->middleware('throttle:10,1')->name('two-factor.enable');
    Route::post('security/two-factor/recovery-codes', [TwoFactorController::class, 'regenerate'])->middleware('throttle:10,1')->name('two-factor.recovery-codes');
    Route::delete('security/two-factor', [TwoFactorController::class, 'disable'])->middleware('throttle:10,1')->name('two-factor.disable');
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
    Route::post('rooms/{room}/ical-feeds', [RoomIcalController::class, 'store'])->middleware('throttle:20,1')->name('rooms.ical.store');
    Route::post('rooms/{room}/ical-feeds/{feed}/sync', [RoomIcalController::class, 'sync'])->middleware('throttle:20,1')->name('rooms.ical.sync');
    Route::delete('rooms/{room}/ical-feeds/{feed}', [RoomIcalController::class, 'destroy'])->name('rooms.ical.destroy');
    Route::post('rooms/{room}/ical-token', [RoomIcalController::class, 'regenerate'])->name('rooms.ical.regenerate');
    Route::get('calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::post('calendar/block', [CalendarController::class, 'block'])->name('calendar.block');

    // Price lists (seasons)
    Route::middleware('can:manage-prices')->group(function (): void {
        Route::put('trips/{trip}/seasons', [SeasonPriceController::class, 'updateTrip'])->name('trips.seasons.update');
        Route::put('rooms/{room}/seasons', [SeasonPriceController::class, 'updateRoom'])->name('rooms.seasons.update');
        Route::put('packages/{package}/seasons', [SeasonPriceController::class, 'updatePackage'])->name('packages.seasons.update');
        Route::put('transfers/routes/{fixedRoute}/seasons', [SeasonPriceController::class, 'updateFixedRoute'])->name('transfers.routes.seasons.update');
        Route::post('seasons/{season}/copy-next-year', [SeasonPriceController::class, 'copyToNextYear'])->name('seasons.copy-next-year');
        Route::put('room-options/{roomOption}/seasons', [SeasonPriceController::class, 'updateRoomOption'])->name('room-options.seasons.update');

        // Optional rooms of multi-day trips, Nile trips and package stages (SRS 7.1)
        Route::get('trips/{trip}/room-options', [RoomOptionController::class, 'trip'])->name('trips.room-options');
        Route::post('trips/{trip}/room-options', [RoomOptionController::class, 'storeForTrip'])->name('trips.room-options.store');
        Route::get('packages/{package}/room-options', [RoomOptionController::class, 'package'])->name('packages.room-options');
        Route::post('packages/{package}/room-options', [RoomOptionController::class, 'storeForPackage'])->name('packages.room-options.store');
        Route::get('room-options/{roomOption}/edit', [RoomOptionController::class, 'edit'])->name('room-options.edit');
        Route::put('room-options/{roomOption}', [RoomOptionController::class, 'update'])->name('room-options.update');
        Route::delete('room-options/{roomOption}', [RoomOptionController::class, 'destroy'])->name('room-options.destroy');
    });

    // Google reviews (content)
    Route::get('google-reviews', [GoogleReviewController::class, 'index'])->name('google-reviews.index');
    Route::put('google-reviews/place', [GoogleReviewController::class, 'updatePlace'])->name('google-reviews.place');
    Route::post('google-reviews/sync', [GoogleReviewController::class, 'sync'])->middleware('throttle:10,1')->name('google-reviews.sync');
    Route::patch('google-reviews/{review}', [GoogleReviewController::class, 'update'])->name('google-reviews.update');

    // Website content: pages, blog and FAQ (every staff role)
    Route::middleware('can:manage-content')->prefix('content')->name('content.')->group(function (): void {
        Route::get('/', [ContentController::class, 'index'])->name('index');
        Route::get('blog/data', [ContentController::class, 'blogData'])->name('blog.data');
        Route::get('pages/{slug}', [ContentController::class, 'editPage'])->name('pages.edit');
        Route::put('pages/{slug}', [ContentController::class, 'updatePage'])->name('pages.update');
        Route::resource('posts', BlogPostController::class)->except(['index', 'show']);
        Route::controller(FaqController::class)->prefix('faq')->name('faq.')->group(function (): void {
            Route::post('reorder', 'reorder')->name('reorder');
            Route::post('categories', 'storeCategory')->name('categories.store');
            Route::get('categories/{category}/edit', 'editCategory')->name('categories.edit');
            Route::put('categories/{category}', 'updateCategory')->name('categories.update');
            Route::delete('categories/{category}', 'destroyCategory')->name('categories.destroy');
            Route::get('items/create', 'createItem')->name('items.create');
            Route::post('items', 'storeItem')->name('items.store');
            Route::get('items/{item}/edit', 'editItem')->name('items.edit');
            Route::put('items/{item}', 'updateItem')->name('items.update');
            Route::delete('items/{item}', 'destroyItem')->name('items.destroy');
        });
    });

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
        Route::get('bookings/create', [BookingEditorController::class, 'create'])->name('bookings.create');
        Route::get('bookings/form/product-options', [BookingEditorController::class, 'productOptions'])->name('bookings.product-options');
        Route::get('bookings/form/customers', [BookingEditorController::class, 'customers'])->middleware('throttle:60,1')->name('bookings.customers');
        Route::post('bookings/quote', [BookingEditorController::class, 'quote'])->middleware('throttle:60,1')->name('bookings.quote');
        Route::post('bookings', [BookingEditorController::class, 'store'])->name('bookings.store');
        Route::get('bookings/{booking}/edit', [BookingEditorController::class, 'edit'])->name('bookings.edit');
        Route::post('bookings/{booking}/preview', [BookingEditorController::class, 'preview'])->middleware('throttle:60,1')->name('bookings.preview');
        Route::put('bookings/{booking}', [BookingEditorController::class, 'update'])->name('bookings.update');
        Route::post('bookings/{booking}/release-hold', [BookingEditorController::class, 'releaseHold'])->name('bookings.release-hold');
        Route::post('calendar/move', [CalendarController::class, 'move'])->name('calendar.move');
        Route::get('bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
        Route::patch('bookings/{booking}/status', [BookingController::class, 'updateStatus'])->name('bookings.status');
        Route::post('bookings/{booking}/payments', [PaymentController::class, 'recordPayment'])->name('bookings.payments.store');
        Route::get('bookings/{booking}/voucher', [BookingController::class, 'voucher'])->name('bookings.voucher');
        Route::patch('bookings/{booking}/balance-due', [BookingController::class, 'updateBalanceDue'])->name('bookings.balance-due');
        Route::post('bookings/{booking}/refunds', [BookingRefundController::class, 'store'])->name('bookings.refunds.store');
        Route::post('bookings/{booking}/refunds/{payment}/paid', [BookingRefundController::class, 'markPaid'])->name('bookings.refunds.paid');
        Route::post('bookings/{booking}/cancellation/approve', [BookingCancellationController::class, 'approve'])->name('bookings.cancellation.approve');
        Route::post('bookings/{booking}/cancellation/decline', [BookingCancellationController::class, 'decline'])->name('bookings.cancellation.decline');

        Route::get('customers/data', [CustomerController::class, 'data'])->name('customers.data');
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::put('customers/{customer}/notes', [CustomerController::class, 'updateNotes'])->name('customers.notes');
        Route::patch('customers/{customer}/active', [CustomerController::class, 'toggleActive'])->name('customers.toggle-active');

        Route::get('coupons/data', [CouponController::class, 'data'])->name('coupons.data');
        Route::resource('coupons', CouponController::class)->except('show');

        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export/{table}', [ReportController::class, 'export'])->name('reports.export');

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
        Route::patch('partners/{partner}/coupons', [PartnerController::class, 'updateCoupons'])->name('partners.coupons');

        // Pricing configuration (CR-10, CR-11)
        Route::get('settings/commissions', [CommissionSettingController::class, 'edit'])->name('commissions.edit');
        Route::put('settings/commissions', [CommissionSettingController::class, 'update'])->name('commissions.update');
        Route::get('nationality-groups', [NationalityGroupController::class, 'index'])->name('nationality-groups.index');
        Route::post('nationality-groups', [NationalityGroupController::class, 'store'])->name('nationality-groups.store');
        Route::put('nationality-groups/{nationalityGroup}', [NationalityGroupController::class, 'update'])->name('nationality-groups.update');
        Route::delete('nationality-groups/{nationalityGroup}', [NationalityGroupController::class, 'destroy'])->name('nationality-groups.destroy');
        Route::post('partners/{partner}/approve', [PartnerController::class, 'approve'])->name('partners.approve');
        Route::post('partners/{partner}/suspend', [PartnerController::class, 'suspend'])->name('partners.suspend');
        Route::post('partners/{partner}/reject', [PartnerController::class, 'reject'])->name('partners.reject');

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

        // Users and roles, activity log (A5, A7)
        Route::get('users/data', [StaffUserController::class, 'data'])->name('users.data');
        Route::post('users/{user}/two-factor/reset', [TwoFactorController::class, 'reset'])->name('users.two-factor.reset');
        Route::resource('users', StaffUserController::class)->except('show');
        Route::get('activity-log', [ActivityLogController::class, 'index'])->name('activity.index');
        Route::get('activity-log/data', [ActivityLogController::class, 'data'])->name('activity.data');

        // Notifications: email templates and sending log (SRS 14.1)
        Route::prefix('notifications')->name('notifications.')->controller(NotificationController::class)->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('log', 'log')->name('log');
            Route::get('log/data', 'logData')->name('log.data');
            Route::post('log/{log}/resend', 'resend')->middleware('throttle:30,1')->name('log.resend');
            Route::get('templates/{event}/{locale}', 'edit')->where('locale', 'en|it')->name('edit');
            Route::get('templates/{event}/{locale}/render', 'show')->where('locale', 'en|it')->name('render');
            Route::put('templates/{event}/{locale}', 'update')->where('locale', 'en|it')->name('update');
            Route::post('templates/{event}/{locale}/preview', 'preview')->where('locale', 'en|it')->name('preview');
            Route::match(['post', 'put'], 'templates/{event}/{locale}/test', 'sendTest')->where('locale', 'en|it')->middleware('throttle:10,1')->name('test');
        });
    });
});

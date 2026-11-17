<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\BookingPaymentController;
use App\Http\Controllers\Api\V1\BuildingController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\DestinationController;
use App\Http\Controllers\Api\V1\GoogleReviewController;
use App\Http\Controllers\Api\V1\HomeController;
use App\Http\Controllers\Api\V1\MetaController;
use App\Http\Controllers\Api\V1\NationalityController;
use App\Http\Controllers\Api\V1\NewsletterController;
use App\Http\Controllers\Api\V1\PackageController;
use App\Http\Controllers\Api\V1\PackageQuoteController;
use App\Http\Controllers\Api\V1\PartnerApplicationController;
use App\Http\Controllers\Api\V1\PartnerPanelController;
use App\Http\Controllers\Api\V1\PaymentWebhookController;
use App\Http\Controllers\Api\V1\RoomController;
use App\Http\Controllers\Api\V1\RoomQuoteController;
use App\Http\Controllers\Api\V1\SearchSuggestController;
use App\Http\Controllers\Api\V1\TransferController;
use App\Http\Controllers\Api\V1\TripController;
use App\Http\Controllers\Api\V1\TripQuoteController;
use App\Http\Middleware\EnsureActivePartner;
use App\Http\Middleware\SetLocaleAndCurrency;
use Illuminate\Support\Facades\Route;

// Every throttle has its own prefix: Laravel keys unnamed throttles by user/IP only, so without a
// prefix all of them would share one counter (ten quotes would lock the visitor out of sign-in).
Route::prefix('v1')->name('api.v1.')->middleware(SetLocaleAndCurrency::class)->group(function (): void {
    Route::get('meta', MetaController::class)->name('meta');
    Route::get('nationalities', NationalityController::class)->name('nationalities');
    Route::get('home', HomeController::class)->name('home');
    Route::get('search/suggest', SearchSuggestController::class)->middleware('throttle:120,1,search')->name('search.suggest');

    Route::get('destinations', [DestinationController::class, 'index'])->name('destinations.index');
    Route::get('destinations/{slug}', [DestinationController::class, 'show'])->name('destinations.show');

    Route::get('trips', [TripController::class, 'index'])->name('trips.index');
    Route::get('trips/{slug}', [TripController::class, 'show'])->name('trips.show');
    Route::post('trips/{slug}/quote', TripQuoteController::class)->middleware('throttle:60,1,quotes')->name('trips.quote');

    Route::get('packages', [PackageController::class, 'index'])->name('packages.index');
    Route::get('packages/{slug}', [PackageController::class, 'show'])->name('packages.show');
    Route::post('packages/{slug}/quote', PackageQuoteController::class)->middleware('throttle:60,1,quotes')->name('packages.quote');

    Route::get('transfers/hubs', [TransferController::class, 'hubs'])->name('transfers.hubs');
    Route::get('transfers/vehicles', [TransferController::class, 'vehicles'])->name('transfers.vehicles');
    Route::post('transfers/quote', [TransferController::class, 'quote'])->middleware('throttle:60,1,quotes')->name('transfers.quote');

    Route::get('rooms', [RoomController::class, 'index'])->name('rooms.index');
    Route::post('rooms/quote', RoomQuoteController::class)->middleware('throttle:60,1,quotes')->name('rooms.quote');
    Route::get('rooms/{id}', [RoomController::class, 'show'])->whereNumber('id')->name('rooms.show');
    Route::get('buildings/{slug}', [BuildingController::class, 'show'])->name('buildings.show');

    Route::get('reviews/google', GoogleReviewController::class)->name('reviews.google');

    Route::post('bookings', [BookingController::class, 'store'])->middleware('throttle:20,1,bookings')->name('bookings.store');
    Route::get('bookings/{reference}', [BookingController::class, 'show'])->name('bookings.show');
    Route::get('bookings/{reference}/payment-methods', [BookingPaymentController::class, 'methods'])->name('bookings.payment-methods');
    Route::post('bookings/{reference}/proofs', [BookingPaymentController::class, 'storeProof'])->middleware('throttle:10,1,payment-proofs')->name('bookings.proofs.store');
    Route::post('bookings/{reference}/checkout', [BookingPaymentController::class, 'checkout'])->middleware('throttle:20,1,payment-checkout')->name('bookings.checkout');
    Route::post('payments/webhooks/{driver}', PaymentWebhookController::class)->whereAlpha('driver')->name('payments.webhook');

    Route::post('newsletter', NewsletterController::class)->middleware('throttle:10,1,newsletter')->name('newsletter.subscribe');

    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1,register')->name('register');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1,login')->name('login');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('me', [AuthController::class, 'me'])->name('me');
        });
    });

    Route::post('contact', ContactController::class)->middleware('throttle:5,1,contact')->name('contact');

    Route::middleware('auth:sanctum')->prefix('me')->name('me.')->group(function (): void {
        Route::get('bookings', [AccountController::class, 'bookings'])->name('bookings');
        Route::patch('profile', [AccountController::class, 'updateProfile'])->name('profile');
        Route::put('password', [AccountController::class, 'updatePassword'])->middleware('throttle:10,1,password')->name('password');
    });

    Route::middleware(['auth:sanctum', EnsureActivePartner::class])->prefix('partner')->name('partner.')->controller(PartnerPanelController::class)->group(function (): void {
        Route::get('dashboard', 'dashboard')->name('dashboard');
        Route::get('catalogue', 'catalogue')->name('catalogue');
        Route::post('quotes', 'quote')->middleware('throttle:60,1,partner-quotes')->name('quotes');
        Route::post('bookings', 'storeBooking')->middleware('throttle:20,1,partner-bookings')->name('bookings.store');
        Route::get('bookings', 'bookings')->name('bookings');
        Route::get('statements', 'statements')->name('statements');
        Route::get('price-list', 'priceList')->name('price-list');
        Route::get('profile', 'profile')->name('profile');
        Route::patch('profile', 'updateProfile')->name('profile.update');
    });

    Route::post('partners/apply', PartnerApplicationController::class)->middleware('throttle:10,1,partner-apply')->name('partners.apply');
});

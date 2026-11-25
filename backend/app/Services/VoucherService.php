<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Trip;
use App\Repositories\Contracts\SettingRepositoryInterface;
use App\Services\Notifications\NotificationContextFactory;
use App\Services\Notifications\TemplateRenderer;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Booking voucher PDF (SRS 12.3), in the booking language, with or without prices. Prices are those the booker
 * pays (the partner total for partner bookings) in the booking currency.
 */
class VoucherService
{
    /**
     * Statuses in which the customer or partner can download the voucher (deposit paid or later).
     *
     * @var list<BookingStatus>
     */
    public const AVAILABLE_FOR = [BookingStatus::Confirmed, BookingStatus::FullyPaid, BookingStatus::Completed];

    public function __construct(
        private readonly NotificationContextFactory $context,
        private readonly SettingRepositoryInterface $settings,
    ) {}

    public function isAvailable(Booking $booking): bool
    {
        return in_array($booking->status, self::AVAILABLE_FOR, true);
    }

    /**
     * Raw PDF bytes.
     */
    public function render(Booking $booking, bool $withPrices = true, ?string $locale = null): string
    {
        $locale = TemplateRenderer::locale($locale ?? $booking->locale);
        $booking->loadMissing(['travelers', 'units', 'addons', 'rooms', 'bookable', 'partner', 'transferRequest.hub', 'transferRequest.vehicleType', 'includedTransfers']);
        $money = fn (float $amountEur): string => $this->context->money($amountEur * (float) $booking->exchange_rate, $booking->currency, $locale);
        $paid = $booking->paidAmount();
        $product = $booking->bookable;

        return Pdf::loadView('pdf.voucher', [
            'booking' => $booking,
            'locale' => $locale,
            'withPrices' => $withPrices,
            'service' => $this->context->serviceName($booking, $locale),
            'serviceDate' => $this->context->serviceDate($booking, $locale),
            'meetingPoint' => $product instanceof Trip ? $product->translate('meeting_point', $locale) : null,
            'transferDetails' => $this->context->transferDetails($booking, $locale),
            'issuedOn' => $this->context->date(now(), $locale),
            'hasBalance' => (float) $booking->total - $paid > 0.01,
            'balanceDueOn' => $this->context->date($this->context->balanceDueOn($booking), $locale),
            'prices' => [
                'total' => $money((float) $booking->total),
                'paid' => $money($paid),
                'balance' => $money(max(0.0, round((float) $booking->total - $paid, 2))),
            ],
            'company' => [
                'name' => $this->settings->get('site.name', config('app.name')),
                'address' => $this->settings->get('site.address'),
                'email' => $this->settings->get('site.email'),
                'phone' => $this->settings->get('site.phone'),
                'whatsapp' => $this->settings->get('site.whatsapp'),
            ],
        ])->setOption(['enable_font_subsetting' => true, 'enable_remote' => false])->setPaper('a4')->output();
    }

    public function filename(Booking $booking, bool $withPrices): string
    {
        return 'voucher-'.$booking->reference.($withPrices ? '' : '-no-prices').'.pdf';
    }
}

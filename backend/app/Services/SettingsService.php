<?php

namespace App\Services;

use App\DTOs\Settings\SettingsData;
use App\Repositories\Contracts\SettingRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Admin-editable settings stored in the key/value "settings" table.
 */
class SettingsService
{
    public const DEFAULT_PREVIEW_COMMISSION = 15.0;

    private ?float $previewCommission = null;

    public function __construct(private readonly SettingRepositoryInterface $settings) {}

    /**
     * Current values keyed like the settings form fields.
     *
     * @return array<string, mixed>
     */
    public function form(): array
    {
        $values = $this->settings->many([
            'site.name', 'site.email', 'site.phone', 'site.whatsapp', 'site.address',
            'booking.default_deposit_percent', 'accommodation.hold_minutes', 'google_reviews.place_id', 'partners.preview_commission',
        ]);

        return [
            'site_name' => $values['site.name'] ?? config('app.name'),
            'site_email' => $values['site.email'] ?? null,
            'site_phone' => $values['site.phone'] ?? null,
            'site_whatsapp' => $values['site.whatsapp'] ?? null,
            'site_address' => $values['site.address'] ?? null,
            'default_deposit_percent' => $values['booking.default_deposit_percent'] ?? 30,
            'hold_minutes' => $values['accommodation.hold_minutes'] ?? BookingService::DEFAULT_HOLD_MINUTES,
            'google_place_id' => $values['google_reviews.place_id'] ?? null,
            'preview_commission' => $values['partners.preview_commission'] ?? self::DEFAULT_PREVIEW_COMMISSION,
        ];
    }

    public function update(SettingsData $data): void
    {
        DB::transaction(function () use ($data): void {
            foreach ($data->toSettings() as $key => $value) {
                $this->settings->set($key, $value);
            }
        });

        $this->previewCommission = null;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->settings->get($key, $default);
    }

    public function set(string $key, mixed $value): void
    {
        $this->settings->set($key, $value);
    }

    /**
     * Commission % used only to preview partner prices next to empty partner price fields.
     */
    public function previewCommission(): float
    {
        return $this->previewCommission ??= (float) $this->settings->get('partners.preview_commission', self::DEFAULT_PREVIEW_COMMISSION);
    }

    public function defaultDepositPercent(): float
    {
        return (float) $this->settings->get('booking.default_deposit_percent', 30);
    }
}

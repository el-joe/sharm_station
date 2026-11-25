<?php

namespace App\DTOs\Settings;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin "Settings" page payload: company data, office location (fed to /meta contact), booking defaults, the Google Place ID,
 * notifications, exchange-rate refresh and two-factor enforcement. Optional fields are only saved when submitted.
 */
final readonly class SettingsData
{
    public function __construct(
        public string $siteName,
        public ?string $siteEmail,
        public ?string $sitePhone,
        public ?string $siteWhatsapp,
        public ?string $siteAddress,
        public float $defaultDepositPercent,
        public int $holdMinutes,
        public ?string $googlePlaceId,
        public float $previewCommission,
        public ?float $siteLat = null,
        public ?float $siteLng = null,
        public ?array $adminEmails = null,
        public ?int $balanceReminderDays = null,
        public ?int $balanceDueDaysBefore = null,
        public ?int $holdWarningMinutes = null,
        public ?int $exchangeRateRefreshHours = null,
        public ?bool $requireTwoFactor = null,
    ) {}

    /**
     * Email addresses separated by commas, semicolons or new lines.
     *
     * @return list<string>
     */
    public static function parseEmails(string $value): array
    {
        return array_values(array_unique(array_filter(array_map('trim', preg_split('/[\s,;]+/', $value) ?: []))));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            siteName: (string) $data['site_name'],
            siteEmail: $data['site_email'] ?? null,
            sitePhone: $data['site_phone'] ?? null,
            siteWhatsapp: $data['site_whatsapp'] ?? null,
            siteAddress: $data['site_address'] ?? null,
            defaultDepositPercent: (float) $data['default_deposit_percent'],
            holdMinutes: (int) $data['hold_minutes'],
            googlePlaceId: filled($data['google_place_id'] ?? null) ? trim($data['google_place_id']) : null,
            previewCommission: (float) ($data['preview_commission'] ?? 0),
            siteLat: is_numeric($data['site_lat'] ?? null) ? round((float) $data['site_lat'], 7) : null,
            siteLng: is_numeric($data['site_lng'] ?? null) ? round((float) $data['site_lng'], 7) : null,
            adminEmails: array_key_exists('admin_emails', $data) ? self::parseEmails((string) $data['admin_emails']) : null,
            balanceReminderDays: isset($data['balance_reminder_days']) ? (int) $data['balance_reminder_days'] : null,
            balanceDueDaysBefore: isset($data['balance_due_days_before']) ? (int) $data['balance_due_days_before'] : null,
            holdWarningMinutes: isset($data['hold_warning_minutes']) ? (int) $data['hold_warning_minutes'] : null,
            exchangeRateRefreshHours: isset($data['exchange_rate_refresh_hours']) ? (int) $data['exchange_rate_refresh_hours'] : null,
            requireTwoFactor: array_key_exists('require_two_factor', $data) ? (bool) $data['require_two_factor'] : null,
        );
    }

    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }

    /**
     * Setting key => value.
     *
     * @return array<string, mixed>
     */
    public function toSettings(): array
    {
        return [
            'site.name' => $this->siteName,
            'site.email' => $this->siteEmail,
            'site.phone' => $this->sitePhone,
            'site.whatsapp' => $this->siteWhatsapp,
            'site.address' => $this->siteAddress,
            'site.lat' => $this->siteLat,
            'site.lng' => $this->siteLng,
            'booking.default_deposit_percent' => $this->defaultDepositPercent,
            'accommodation.hold_minutes' => $this->holdMinutes,
            'google_reviews.place_id' => $this->googlePlaceId,
            'partners.preview_commission' => $this->previewCommission,
            ...array_filter([
                'notifications.admin_emails' => $this->adminEmails,
                'notifications.balance_reminder_days' => $this->balanceReminderDays,
                'booking.balance_due_days_before' => $this->balanceDueDaysBefore,
                'accommodation.hold_warning_minutes' => $this->holdWarningMinutes,
                'currency.refresh_hours' => $this->exchangeRateRefreshHours,
                'security.require_two_factor' => $this->requireTwoFactor,
            ], fn ($value): bool => $value !== null),
        ];
    }
}

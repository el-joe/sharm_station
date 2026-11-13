<?php

namespace App\DTOs\Settings;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin "Settings" page payload: company data, booking defaults and the Google Place ID.
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
    ) {}

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
            'booking.default_deposit_percent' => $this->defaultDepositPercent,
            'accommodation.hold_minutes' => $this->holdMinutes,
            'google_reviews.place_id' => $this->googlePlaceId,
            'partners.preview_commission' => $this->previewCommission,
        ];
    }
}

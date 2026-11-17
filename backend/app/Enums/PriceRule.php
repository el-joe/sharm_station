<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Source of the commission percentage applied to a partner price (CR-11).
 */
enum PriceRule: string
{
    use HasOptions;

    case PartnerOverride = 'partner_override';
    case GlobalSetting = 'global_setting';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::PartnerOverride => 'Partner override',
            self::GlobalSetting => 'Global setting',
            self::None => 'No commission',
        };
    }
}

<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PriceRule: string
{
    use HasOptions;

    case Retail = 'retail';
    case PartnerPrice = 'partner_price';
    case CategoryCommission = 'category_commission';
    case DefaultCommission = 'default_commission';
    case Mixed = 'mixed';

    public function label(): string
    {
        return match ($this) {
            self::Retail => 'Retail price',
            self::PartnerPrice => 'Explicit partner price',
            self::CategoryCommission => 'Partner category commission',
            self::DefaultCommission => 'Partner default commission',
            self::Mixed => 'Mixed rules',
        };
    }
}

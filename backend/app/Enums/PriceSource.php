<?php

namespace App\Enums;

/**
 * Layer of the price matrix a customer price came from (PLAN v2.2 §3), in resolution order.
 */
enum PriceSource: string
{
    case GroupSeason = 'group_season';
    case AllGroupsSeason = 'all_groups_season';
    case GroupBase = 'group_base';
    case ProductBase = 'product_base';

    public function label(): string
    {
        return match ($this) {
            self::GroupSeason => 'Season for the nationality group',
            self::AllGroupsSeason => 'Season for all groups',
            self::GroupBase => 'Base price of the nationality group',
            self::ProductBase => 'Product base price',
        };
    }
}

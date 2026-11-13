<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Which search field asks for suggestions (home hero tabs and transfer forms).
 */
enum SearchScope: string
{
    use HasOptions;

    case Trips = 'trips';
    case Nile = 'nile';
    case Packages = 'packages';
    case Stays = 'stays';
    case TransferFrom = 'transfer_from';
    case TransferTo = 'transfer_to';

    public function label(): string
    {
        return match ($this) {
            self::Trips => 'Trips',
            self::Nile => 'Nile trips',
            self::Packages => 'Packages',
            self::Stays => 'Accommodation',
            self::TransferFrom => 'Transfer pick-up',
            self::TransferTo => 'Transfer drop-off',
        };
    }
}

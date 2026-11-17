<?php

namespace App\Exceptions;

/**
 * Thrown when a price period overlaps another active period of the same product and nationality group.
 */
class SeasonOverlapException extends DomainValidationException
{
    public static function between(string $newSeason, string $existingSeason, string $range, ?string $groupLabel = null): self
    {
        $scope = $groupLabel !== null ? " ({$groupLabel})" : '';
        $message = "“{$newSeason}”{$scope} overlaps “{$existingSeason}” on {$range}.";

        return new self($message, ['starts_on' => [$message]]);
    }
}

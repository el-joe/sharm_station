<?php

namespace App\Exceptions;

/**
 * Thrown when a price period overlaps another active period of the same product.
 */
class SeasonOverlapException extends DomainValidationException
{
    public static function between(string $newSeason, string $existingSeason, string $range): self
    {
        $message = "“{$newSeason}” overlaps “{$existingSeason}” on {$range}.";

        return new self($message, ['starts_on' => [$message]]);
    }
}

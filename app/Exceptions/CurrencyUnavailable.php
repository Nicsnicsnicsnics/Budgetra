<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an amount can't be converted into pesos because no live rate is
 * reachable. Budgetra keeps no hardcoded rate table, so the only honest options
 * are to convert with a real rate or to refuse — never to store a foreign number
 * in a peso column, which is what used to happen silently.
 */
class CurrencyUnavailable extends RuntimeException
{
    public static function for(string $code): self
    {
        return new self(
            "We couldn't fetch the {$code} exchange rate just now, so this amount can't be "
            . 'converted to pesos. Please try again in a moment.'
        );
    }
}

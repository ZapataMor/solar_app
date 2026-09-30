<?php

namespace App\Domain\Climate;

use RuntimeException;

final class NoClimateData extends RuntimeException
{
    /**
     * @param  string|null  $source  Requested source key, or null when every source was tried.
     */
    public function __construct(public readonly ?string $source = null)
    {
        parent::__construct('El proyecto no tiene datos climaticos.');
    }
}

<?php

namespace App\Domain\Reference;

/**
 * Port: the reference values that apply today (ADR-0015).
 */
interface ReferenceValues
{
    public function current(string $key): CurrentReferenceValue;
}

<?php

namespace DBM\Geo;

interface CountryLookup
{
    /** ISO-код країни за IP або null. */
    public function country(string $ip): ?string;
}

<?php

namespace DBM\Geo;

class GeoDetector
{
    public function __construct(private CountryLookup $lookup) {}

    /** Країна: CF-IPCountry → лукап IP → 'WORLD'. $headers — асоц. масив заголовків. */
    public function detect(array $headers, string $ip): string
    {
        $cf = strtoupper(trim((string) ($headers['CF-IPCountry'] ?? '')));
        if ($cf !== '' && $cf !== 'XX' && ctype_alpha($cf)) {
            return $cf;
        }

        $byIp = $this->lookup->country($ip);

        return $byIp !== null && $byIp !== '' ? strtoupper($byIp) : 'WORLD';
    }
}

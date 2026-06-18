<?php

namespace DBM\Geo;

class GeoSimulation
{
    private const OPTION = 'dbm_simulated_country';

    public function getSimulatedCountry(): ?string
    {
        $val = get_option(self::OPTION, '');
        if (empty($val) || $val === 'disabled') {
            return null;
        }

        return strtoupper($val);
    }

    public function setSimulatedCountry(string $country): void
    {
        update_option(self::OPTION, sanitize_text_field($country));
    }

    public function isEnabled(): bool
    {
        return $this->getSimulatedCountry() !== null;
    }

    /** @return string[] */
    public function getAvailableCountries(array $cache): array
    {
        $countries = ['WORLD', 'UA', 'RO', 'RU', 'BY', 'KZ', 'PL'];
        foreach ($cache['values'] ?? [] as $v) {
            if (! empty($v['geos']) && is_array($v['geos'])) {
                foreach ($v['geos'] as $g) {
                    $countries[] = strtoupper($g);
                }
            }
            if (! empty($v['geo'])) {
                $countries[] = strtoupper($v['geo']);
            }
        }
        $countries = array_unique($countries);
        sort($countries);

        return $countries;
    }
}

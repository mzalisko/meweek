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
            $this->appendCountryCodes($countries, $v['geos'] ?? null);
            $this->appendCountryCodes($countries, $v['geo'] ?? null);
        }
        $countries = array_unique($countries);
        sort($countries);

        return $countries;
    }

    /**
     * @param array<int, string> $countries
     * @param mixed $raw
     */
    private function appendCountryCodes(array &$countries, $raw): void
    {
        foreach (is_array($raw) ? $raw : [$raw] as $code) {
            if (! is_scalar($code)) {
                continue;
            }

            $code = trim((string) $code);
            if ($code !== '' && ! str_starts_with($code, '!')) {
                $countries[] = strtoupper($code);
            }
        }
    }
}

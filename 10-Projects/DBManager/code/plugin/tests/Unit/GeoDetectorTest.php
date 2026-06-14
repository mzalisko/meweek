<?php

namespace DBM\Tests\Unit;

use DBM\Geo\CountryLookup;
use DBM\Geo\GeoDetector;
use PHPUnit\Framework\TestCase;

class GeoDetectorTest extends TestCase
{
    private function detector(?string $lookupResult): GeoDetector
    {
        $lookup = new class($lookupResult) implements CountryLookup {
            public function __construct(private ?string $r) {}

            public function country(string $ip): ?string
            {
                return $this->r;
            }
        };

        return new GeoDetector($lookup);
    }

    public function test_prefers_cloudflare_header(): void
    {
        $country = $this->detector('PL')->detect(['CF-IPCountry' => 'UA'], '1.2.3.4');
        $this->assertSame('UA', $country);
    }

    public function test_falls_back_to_lookup_when_no_header(): void
    {
        $this->assertSame('PL', $this->detector('PL')->detect([], '1.2.3.4'));
    }

    public function test_world_when_nothing_resolves(): void
    {
        $this->assertSame('WORLD', $this->detector(null)->detect([], '1.2.3.4'));
    }

    public function test_ignores_cloudflare_placeholder_xx(): void
    {
        // CF віддає "XX" для невідомих/анонімайзерів — це не країна.
        $this->assertSame('PL', $this->detector('PL')->detect(['CF-IPCountry' => 'XX'], '1.2.3.4'));
    }
}

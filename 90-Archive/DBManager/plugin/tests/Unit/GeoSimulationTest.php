<?php

namespace DBM\Tests\Unit;

use DBM\Geo\GeoSimulation;
use PHPUnit\Framework\TestCase;

class GeoSimulationTest extends TestCase
{
    public function test_available_countries_accept_geo_arrays_and_strings(): void
    {
        $countries = (new GeoSimulation())->getAvailableCountries([
            'values' => [
                ['geo' => ['WORLD', 'UA']],
                ['geo' => 'RO'],
                ['geos' => ['PL', 'KZ']],
                ['geo' => [['bad']]],
            ],
        ]);

        $this->assertContains('WORLD', $countries);
        $this->assertContains('UA', $countries);
        $this->assertContains('RO', $countries);
        $this->assertContains('PL', $countries);
        $this->assertContains('KZ', $countries);
    }
}

<?php

namespace DBM\Geo;

interface GeoDbStore
{
    public function sha(): ?string;  // sha наявної бази або null

    public function bytes(): ?string;

    public function put(string $bytes): void;

    public function path(): string;  // шлях до файла бази для рідера
}

<?php

namespace DBM\Tests\Unit;

use PHPUnit\Framework\TestCase;

class SmokeTest extends TestCase
{
    public function test_autoloader_is_wired(): void
    {
        $this->assertTrue(class_exists(\DBM\Tests\Unit\SmokeTest::class));
    }
}

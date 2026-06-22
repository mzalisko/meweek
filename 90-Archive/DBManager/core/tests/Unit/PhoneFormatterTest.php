<?php

namespace Tests\Unit;

use App\Support\PhoneFormatter;
use PHPUnit\Framework\TestCase;

class PhoneFormatterTest extends TestCase
{
    public function test_formats_number_with_custom_pattern(): void
    {
        $this->assertSame(
            '+380 (44) 111-22-33',
            PhoneFormatter::format('+380441112233', '+### (##) ###-##-##')
        );
    }

    public function test_empty_pattern_keeps_raw_number(): void
    {
        $this->assertSame('+380441112233', PhoneFormatter::format('+380441112233', ''));
    }

    public function test_validates_safe_pattern_characters(): void
    {
        $this->assertTrue(PhoneFormatter::isValidPattern('+### ## ### ## ##'));
        $this->assertFalse(PhoneFormatter::isValidPattern('+### <script>'));
    }
}

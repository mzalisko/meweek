<?php

namespace DBM\Tests\Unit;

use DBM\Admin\SnippetBuilder;
use PHPUnit\Framework\TestCase;

class SnippetBuilderTest extends TestCase
{
    public function test_builds_shortcode_and_php_snippets(): void
    {
        $b = new SnippetBuilder('dbm');
        $snips = $b->forKey('phone_ua_1');

        $this->assertSame('[dbm key="phone_ua_1"]', $snips['shortcode']);
        $this->assertSame('[dbm key="phone_ua_1" format="tel"]', $snips['tel']);
        $this->assertSame("dbm_get('phone_ua_1')", $snips['php']);
    }

    public function test_respects_custom_shortcode_name(): void
    {
        $this->assertSame('[phone key="x"]', (new SnippetBuilder('phone'))->forKey('x')['shortcode']);
    }
}

<?php

namespace DBM\Tests\Unit;

use DBM\Tests\Support\InMemoryCacheStore;
use PHPUnit\Framework\TestCase;

class InMemoryCacheStoreTest extends TestCase
{
    public function test_stores_and_reads_payload_and_version(): void
    {
        $store = new InMemoryCacheStore();
        $store->put(['site_id' => 123, 'version' => 5, 'values' => []]);

        $this->assertSame(5, $store->version());
        $this->assertSame(123, $store->get()['site_id']);
    }

    public function test_version_is_zero_when_empty(): void
    {
        $this->assertSame(0, (new InMemoryCacheStore())->version());
        $this->assertNull((new InMemoryCacheStore())->get());
    }
}

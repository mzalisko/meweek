<?php

namespace DBM\Sync;

class SyncResult
{
    public function __construct(
        public bool $updated,
        public int $version,
        public string $reason = '',
    ) {}
}

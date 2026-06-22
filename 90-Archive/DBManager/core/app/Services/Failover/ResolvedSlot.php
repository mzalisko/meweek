<?php

namespace App\Services\Failover;

final readonly class ResolvedSlot
{
    public function __construct(
        public string $state,   // ok | on_reserve | pinned | exhausted
        public ?string $number,
        public ?int $entryId,
        public bool $visible,
    ) {}
}

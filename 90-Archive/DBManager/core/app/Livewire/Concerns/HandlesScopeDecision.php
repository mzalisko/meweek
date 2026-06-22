<?php

namespace App\Livewire\Concerns;

use App\Models\DataValue;

trait HandlesScopeDecision
{
    public bool $showScopeDialog = false;

    public ?array $pendingScopeAction = null;

    protected bool $scopeDecided = false;

    protected function deferForScope(string $action, array $params, ?DataValue $value): bool
    {
        return false;
    }

    public function confirmScopeOnlyThisSite(): void
    {
    }

    public function confirmScopeCascade(): void
    {
    }

    public function cancelScopeDecision(): void
    {
    }
}

<?php

namespace App\Livewire\Concerns;

use App\Admin\EditLock;

trait UsesEditLock
{
    public ?string $editLockResource = null;

    public ?string $editLockLabel = null;

    public ?array $editLockState = null;

    public bool $editLockBlocked = false;

    public function refreshEditLock(): void
    {
        if (! $this->editLockResource || ! $this->editLockLabel) {
            return;
        }

        $this->applyEditLockState(
            app(EditLock::class)->refresh($this->editLockResource, auth()->user(), $this->editLockLabel),
        );
    }

    public function takeoverEditLock(): void
    {
        $this->requestEditLockTakeover();
    }

    public function requestEditLockTakeover(): void
    {
        if (! $this->editLockResource || ! $this->editLockLabel) {
            return;
        }

        $this->applyEditLockState(
            app(EditLock::class)->requestTakeover($this->editLockResource, auth()->user(), $this->editLockLabel),
        );
    }

    public function approveEditLockTakeover(): void
    {
        if (! $this->editLockResource || ! $this->editLockLabel) {
            return;
        }

        $this->applyEditLockState(
            app(EditLock::class)->approveTakeover($this->editLockResource, auth()->user(), $this->editLockLabel),
        );
    }

    public function rejectEditLockTakeover(): void
    {
        if (! $this->editLockResource || ! $this->editLockLabel) {
            return;
        }

        $this->applyEditLockState(
            app(EditLock::class)->rejectTakeover($this->editLockResource, auth()->user(), $this->editLockLabel),
        );
    }

    protected function acquireEditLock(string $resource, string $label, bool $force = false): bool
    {
        $this->releaseEditLock();
        $this->editLockResource = $resource;
        $this->editLockLabel = $label;

        $this->applyEditLockState(
            app(EditLock::class)->acquire($resource, auth()->user(), $label, $force),
        );

        return ! $this->editLockBlocked;
    }

    protected function ensureEditLock(): bool
    {
        if (! $this->editLockResource || ! $this->editLockLabel) {
            return true;
        }

        $this->refreshEditLock();

        if ($this->editLockBlocked) {
            $this->addError('editLock', 'Запис зараз редагує інший користувач. Зміни заблоковано.');
            return false;
        }

        return true;
    }

    protected function releaseEditLock(): void
    {
        if ($this->editLockResource) {
            app(EditLock::class)->release($this->editLockResource, auth()->user());
        }

        $this->resetEditLock();
    }

    protected function resetEditLock(): void
    {
        $this->editLockResource = null;
        $this->editLockLabel = null;
        $this->editLockState = null;
        $this->editLockBlocked = false;
    }

    protected function editLockKey(string $type, int $id): string
    {
        return "{$type}:{$id}";
    }

    private function applyEditLockState(array $state): void
    {
        $this->editLockState = $state;
        $this->editLockBlocked = ! (bool) ($state['owned'] ?? false);
    }
}

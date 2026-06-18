<?php

namespace App\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class EditLock
{
    private const TTL_SECONDS = 120;

    public function acquire(string $resource, ?User $user, string $label, bool $force = false): array
    {
        $current = Cache::get($this->key($resource));
        $owner = $this->owner($user, $label);

        if ($current && $this->ownedByCurrentSession($current, $user)) {
            return $this->touch($resource, $this->mergeOwner($current, $owner), $user);
        }

        if ($current && ! $force) {
            return $this->state($resource, $label, false, $current, $user);
        }

        return $this->store($resource, $owner, $user, $force);
    }

    public function refresh(string $resource, ?User $user, string $label): array
    {
        $current = Cache::get($this->key($resource));

        if (! $current) {
            return $this->acquire($resource, $user, $label);
        }

        if ($this->ownedByCurrentSession($current, $user)) {
            return $this->touch($resource, $this->mergeOwner($current, $this->owner($user, $current['label'] ?? $label)), $user);
        }

        return $this->state($resource, $label, false, $current, $user);
    }

    public function requestTakeover(string $resource, ?User $user, string $label): array
    {
        $current = Cache::get($this->key($resource));

        if (! $current) {
            return $this->acquire($resource, $user, $label);
        }

        if ($this->ownedByCurrentSession($current, $user)) {
            return $this->touch($resource, $current, $user);
        }

        $current['takeover_request'] = $this->requester($user);
        unset($current['takeover_denial']);

        return $this->touch($resource, $current, $user);
    }

    public function approveTakeover(string $resource, ?User $user, string $label): array
    {
        $current = Cache::get($this->key($resource));

        if (! $current) {
            return $this->acquire($resource, $user, $label);
        }

        if (! $this->ownedByCurrentSession($current, $user) || empty($current['takeover_request'])) {
            return $this->state($resource, $label, $this->ownedByCurrentSession($current, $user), $current, $user);
        }

        $request = $current['takeover_request'];
        $newOwner = [
            'label' => $current['label'] ?? $label,
            'user_id' => $request['user_id'] ?? null,
            'user_name' => $request['user_name'] ?? 'Користувач',
            'session_id' => $request['session_id'] ?? null,
            'started_at' => now()->toIso8601String(),
            'takeover_approved_at' => now()->toIso8601String(),
            'takeover_approved_by_name' => $user?->name ?: ($user?->email ?: 'Користувач'),
        ];

        return $this->store($resource, $newOwner, $user, true);
    }

    public function rejectTakeover(string $resource, ?User $user, string $label): array
    {
        $current = Cache::get($this->key($resource));

        if (! $current) {
            return $this->acquire($resource, $user, $label);
        }

        if (! $this->ownedByCurrentSession($current, $user)) {
            return $this->state($resource, $label, false, $current, $user);
        }

        if (! empty($current['takeover_request'])) {
            $current['takeover_denial'] = $current['takeover_request'];
            $current['takeover_denial']['denied_at'] = now()->toIso8601String();
            $current['takeover_denial']['denied_by_name'] = $user?->name ?: ($user?->email ?: 'Користувач');
            unset($current['takeover_request']);
        }

        return $this->touch($resource, $current, $user);
    }

    public function release(string $resource, ?User $user): void
    {
        $key = $this->key($resource);
        $current = Cache::get($key);

        if ($current && $this->ownedByCurrentSession($current, $user)) {
            Cache::forget($key);
        }
    }

    private function store(string $resource, array $owner, ?User $viewer, bool $takenOver = false): array
    {
        $owner['expires_at'] = now()->addSeconds(self::TTL_SECONDS)->toIso8601String();
        Cache::put($this->key($resource), $owner, self::TTL_SECONDS);

        return $this->state($resource, $owner['label'], $this->ownedByCurrentSession($owner, $viewer), $owner, $viewer, $takenOver);
    }

    private function touch(string $resource, array $owner, ?User $viewer): array
    {
        $owner['expires_at'] = now()->addSeconds(self::TTL_SECONDS)->toIso8601String();
        Cache::put($this->key($resource), $owner, self::TTL_SECONDS);

        return $this->state(
            $resource,
            $owner['label'],
            $this->ownedByCurrentSession($owner, $viewer),
            $owner,
            $viewer,
            ! empty($owner['takeover_approved_at']),
        );
    }

    private function state(string $resource, string $label, bool $owned, array $owner, ?User $viewer, bool $takenOver = false): array
    {
        $request = $owner['takeover_request'] ?? null;
        $denial = $owner['takeover_denial'] ?? null;
        $requestMatchesViewer = $request && $this->sessionMatches($request, $viewer);
        $denialMatchesViewer = $denial && $this->sessionMatches($denial, $viewer);

        return [
            'resource' => $resource,
            'label' => $label,
            'owned' => $owned,
            'blocked' => ! $owned,
            'taken_over' => $takenOver,
            'owner_name' => $owner['user_name'] ?? 'інший користувач',
            'owner_id' => $owner['user_id'] ?? null,
            'has_takeover_request' => $owned && (bool) $request,
            'takeover_request_pending' => ! $owned && (bool) $requestMatchesViewer,
            'takeover_request_denied' => ! $owned && (bool) $denialMatchesViewer,
            'takeover_requester_name' => $request['user_name'] ?? null,
            'takeover_denied_by_name' => $denial['denied_by_name'] ?? null,
            'takeover_approved_by_name' => $owner['takeover_approved_by_name'] ?? null,
            'started_at' => $owner['started_at'] ?? null,
            'expires_at' => $owner['expires_at'] ?? null,
        ];
    }

    private function owner(?User $user, string $label): array
    {
        return [
            'label' => $label,
            'user_id' => $user?->id,
            'user_name' => $user?->name ?: ($user?->email ?: 'Користувач'),
            'session_id' => session()->getId() ?: 'cli',
            'started_at' => now()->toIso8601String(),
        ];
    }

    private function requester(?User $user): array
    {
        return [
            'user_id' => $user?->id,
            'user_name' => $user?->name ?: ($user?->email ?: 'Користувач'),
            'session_id' => session()->getId() ?: 'cli',
            'requested_at' => now()->toIso8601String(),
        ];
    }

    private function mergeOwner(array $current, array $owner): array
    {
        return array_merge($current, [
            'label' => $owner['label'],
            'user_id' => $owner['user_id'],
            'user_name' => $owner['user_name'],
            'session_id' => $owner['session_id'],
        ]);
    }

    private function ownedByCurrentSession(array $owner, ?User $user): bool
    {
        return $this->sessionMatches($owner, $user);
    }

    private function sessionMatches(array $owner, ?User $user): bool
    {
        return (int) ($owner['user_id'] ?? 0) === (int) ($user?->id ?? 0)
            && (string) ($owner['session_id'] ?? '') === (string) (session()->getId() ?: 'cli');
    }

    private function key(string $resource): string
    {
        return 'dbmanager:edit-lock:' . sha1($resource);
    }
}

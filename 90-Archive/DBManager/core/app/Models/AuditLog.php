<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->actor_type)) {
                $model->actor_type = 'user';
            }
            if (empty($model->actor_id) && auth()->check()) {
                $model->actor_id = auth()->id();
            }

            // Якщо логується дія над DataValue, автоматично підмішуємо scope_type та scope_id у масиви old та new,
            // щоб історія не втрачалась після видалення запису.
            if ($model->subject_type === 'DataValue' && $model->subject_id) {
                $dv = DataValue::find($model->subject_id);
                if ($dv) {
                    if (is_array($model->old)) {
                        $model->old = array_merge(['scope_type' => $dv->scope_type, 'scope_id' => (int) $dv->scope_id], $model->old);
                    }
                    if (is_array($model->new)) {
                        $model->new = array_merge(['scope_type' => $dv->scope_type, 'scope_id' => (int) $dv->scope_id], $model->new);
                    }
                }
            }
        });
    }

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'old' => 'array',
        'new' => 'array',
        'created_at' => 'datetime',
    ];
    public function getAffectedDomains(): string
    {
        $log = $this;
        if ($this->action === 'audit.restored') {
            // Для відновлення беремо оригінальний лог
            $log = self::find($this->subject_id);
            if (! $log) {
                return '—';
            }
        }

        $scopeType = null;
        $scopeId = null;

        if ($log->subject_type === 'DataValue') {
            $dv = \Illuminate\Support\Facades\Cache::driver('array')->remember('dv_' . $log->subject_id, 60, function () use ($log) {
                return DataValue::find($log->subject_id);
            });
            if ($dv) {
                $scopeType = $dv->scope_type;
                $scopeId = $dv->scope_id;
            }
        }

        if (! $scopeType && is_array($log->old)) {
            $scopeType = $log->old['scope_type'] ?? null;
            $scopeId = $log->old['scope_id'] ?? null;
        }
        if (! $scopeType && is_array($log->new)) {
            $scopeType = $log->new['scope_type'] ?? null;
            $scopeId = $log->new['scope_id'] ?? null;
        }

        if ($scopeType === 'site' && $scopeId) {
            $site = \Illuminate\Support\Facades\Cache::driver('array')->remember('site_' . $scopeId, 60, function () use ($scopeId) {
                return Site::find($scopeId);
            });
            return $site ? $site->domain : '—';
        }

        if ($scopeType === 'group' && $scopeId) {
            return \Illuminate\Support\Facades\Cache::driver('array')->remember('group_domains_' . $scopeId, 60, function () use ($scopeId) {
                $group = SiteGroup::with('sites')->find($scopeId);
                if ($group && $group->sites->isNotEmpty()) {
                    return $group->sites->pluck('domain')->implode(', ');
                }
                return '—';
            });
        }

        return '—';
    }
}



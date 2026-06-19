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
}


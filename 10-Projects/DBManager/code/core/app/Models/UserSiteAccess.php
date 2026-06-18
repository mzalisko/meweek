<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSiteAccess extends Model
{
    protected $table = 'user_site_access';

    protected $guarded = [];

    protected $casts = [
        'can_view' => 'boolean',
        'can_edit' => 'boolean',
        'can_delete' => 'boolean',
        'can_publish' => 'boolean',
        'can_view_history' => 'boolean',
        'can_view_failover' => 'boolean',
        'can_view_prices' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}

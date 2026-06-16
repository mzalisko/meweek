<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSiteGroupAccess extends Model
{
    protected $table = 'user_site_group_access';

    protected $guarded = [];

    protected $casts = [
        'can_view' => 'boolean',
        'can_edit' => 'boolean',
        'can_delete' => 'boolean',
        'can_publish' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function siteGroup(): BelongsTo
    {
        return $this->belongsTo(SiteGroup::class);
    }
}

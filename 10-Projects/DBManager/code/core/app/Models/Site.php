<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Site extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    public function group(): BelongsTo
    {
        return $this->belongsTo(SiteGroup::class, 'site_group_id');
    }

    public function parentSite(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_site_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_site_id');
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    public function userAccess(): HasMany
    {
        return $this->hasMany(UserSiteAccess::class);
    }
}

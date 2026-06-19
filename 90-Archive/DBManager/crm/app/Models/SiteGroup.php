<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SiteGroup extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function userAccess(): HasMany
    {
        return $this->hasMany(UserSiteGroupAccess::class);
    }
}

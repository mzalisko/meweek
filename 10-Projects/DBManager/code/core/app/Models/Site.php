<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function group(): BelongsTo
    {
        return $this->belongsTo(SiteGroup::class, 'site_group_id');
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class UserFavorite extends Model
{
    protected $guarded = [];

    public function favorable(): MorphTo
    {
        return $this->morphTo();
    }
}

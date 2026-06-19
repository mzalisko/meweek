<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeoTag extends Model
{
    protected $guarded = [];

    protected $casts = ['is_protected' => 'boolean'];
}

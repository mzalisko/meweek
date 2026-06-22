<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhoneNumber extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = ['down_since' => 'datetime'];

    public function entries(): HasMany
    {
        return $this->hasMany(NumberEntry::class);
    }
}

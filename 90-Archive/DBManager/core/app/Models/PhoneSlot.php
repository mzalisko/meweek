<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhoneSlot extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function dataValue(): BelongsTo
    {
        return $this->belongsTo(DataValue::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(NumberEntry::class);
    }
}

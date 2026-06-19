<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DataValue extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = ['content' => 'array'];

    public function type(): BelongsTo
    {
        return $this->belongsTo(ValueType::class, 'value_type_id');
    }

    public function geoTags(): BelongsToMany
    {
        return $this->belongsToMany(GeoTag::class);
    }

    public function phoneSlot(): HasOne
    {
        return $this->hasOne(PhoneSlot::class);
    }
}

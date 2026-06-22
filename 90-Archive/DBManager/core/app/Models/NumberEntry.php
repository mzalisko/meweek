<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NumberEntry extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function slot(): BelongsTo
    {
        return $this->belongsTo(PhoneSlot::class, 'phone_slot_id');
    }

    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(PhoneNumber::class);
    }
}

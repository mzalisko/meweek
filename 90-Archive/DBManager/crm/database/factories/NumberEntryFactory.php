<?php

namespace Database\Factories;

use App\Models\PhoneNumber;
use App\Models\PhoneSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

class NumberEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'phone_slot_id' => PhoneSlot::factory(),
            'phone_number_id' => PhoneNumber::factory(),
            'priority' => 0,
        ];
    }
}

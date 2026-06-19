<?php

namespace Database\Factories;

use App\Models\DataValue;
use Illuminate\Database\Eloquent\Factories\Factory;

class PhoneSlotFactory extends Factory
{
    public function definition(): array
    {
        return [
            'data_value_id' => DataValue::factory()->ofType('phone')->state(['content' => null]),
            'return_mode' => 'auto',
            'exhaustion_policy' => 'hide',
        ];
    }
}

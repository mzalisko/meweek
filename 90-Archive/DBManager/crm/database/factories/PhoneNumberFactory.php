<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PhoneNumberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'e164' => '+380' . fake()->unique()->numerify('#########'),
            'label' => null,
            'status' => 'active',
        ];
    }
}

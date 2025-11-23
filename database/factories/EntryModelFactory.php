<?php

declare(strict_types=1);

namespace Database\factories;

use FormaFlow\Entries\Infrastructure\Persistence\Eloquent\EntryModel;
use Illuminate\Database\Eloquent\Factories\Factory;

class EntryModelFactory extends Factory
{
    protected $model = EntryModel::class;

    public function definition(): array
    {
        return [
            'id' => $this->faker->uuid(),
            'form_id' => null,
            'user_id' => null,
            'data' => [
                'amount' => $this->faker->randomFloat(2, 10, 1000),
                'date' => $this->faker->date(),
                'category' => $this->faker->randomElement(['income', 'expense']),
            ],
        ];
    }
}

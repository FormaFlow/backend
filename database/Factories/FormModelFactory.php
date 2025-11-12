<?php

declare(strict_types=1);

namespace Database\Factories;

use Carbon\Carbon;
use FormaFlow\Forms\Infrastructure\Persistence\Eloquent\FormModel;
use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class FormModelFactory extends Factory
{
    protected $model = FormModel::class;

    public function definition(): array
    {
        return [
            'id' => (string)Str::uuid(),
            'user_id' => (string)Str::uuid(), // Или свяжите с User, если нужно
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'published' => false,
            'version' => 1,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }

    public function published(): static
    {
        return $this->state(fn(array $attributes) => [
            'published' => true,
        ]);
    }

    public function forUser(UserModel $user): static
    {
        return $this->state(fn(array $attributes) => [
            'user_id' => $user->id ?? (string)Str::uuid(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace LaravelPlus\Subscribe\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use LaravelPlus\Subscribe\Models\Subscriber;

final class SubscriberFactory extends Factory
{
    protected $model = Subscriber::class;

    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->optional()->phoneNumber(),
            'company' => fake()->optional()->company(),
            'attributes' => [],
            'tags' => fake()->randomElements(['newsletter', 'updates', 'promotions', 'beta'], rand(0, 3)),
            'source' => fake()->randomElement(['website', 'api', 'import', 'manual']),
            'ip_address' => fake()->optional()->ipv4(),
            'status' => fake()->randomElement(['pending', 'subscribed', 'subscribed', 'subscribed']),
            'confirmed_at' => fn (array $attrs) => $attrs['status'] === 'subscribed' ? fake()->dateTimeBetween('-1 year') : null,
            'confirmation_token' => fn (array $attrs) => $attrs['status'] === 'pending' ? Str::random(64) : null,
        ];
    }

    public function subscribed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'subscribed',
            'confirmed_at' => fake()->dateTimeBetween('-1 year'),
            'confirmation_token' => null,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'confirmed_at' => null,
            'confirmation_token' => Str::random(64),
        ]);
    }

    public function unsubscribed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'unsubscribed',
            'confirmed_at' => fake()->dateTimeBetween('-1 year'),
            'confirmation_token' => null,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace LaravelPlus\Subscribe\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use LaravelPlus\Subscribe\Models\SubscriptionList;

final class SubscriptionListFactory extends Factory
{
    protected $model = SubscriptionList::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(rand(1, 3), true);

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'description' => fake()->optional()->sentence(),
            'is_public' => fake()->boolean(80),
            'is_default' => false,
            'double_opt_in' => fake()->boolean(70),
            'welcome_email_enabled' => fake()->boolean(50),
            'welcome_email_subject' => fn (array $attrs) => $attrs['welcome_email_enabled'] ? 'Welcome to '.ucfirst($name).'!' : null,
            'welcome_email_content' => fn (array $attrs) => $attrs['welcome_email_enabled'] ? fake()->paragraph() : null,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => true,
        ]);
    }

    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => false,
        ]);
    }

    public function withDoubleOptIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'double_opt_in' => true,
        ]);
    }

    public function withWelcomeEmail(): static
    {
        return $this->state(fn (array $attributes) => [
            'welcome_email_enabled' => true,
            'welcome_email_subject' => 'Welcome!',
            'welcome_email_content' => fake()->paragraph(),
        ]);
    }
}

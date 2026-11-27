<?php

declare(strict_types=1);

namespace LaravelPlus\Subscribe\Tests\Feature;

use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use LaravelPlus\Subscribe\Tests\TestCase;

/** A host user with Spatie-style roles, without needing spatie/laravel-permission. */
final class RoleUser extends User
{
    /** @param  list<string>  $roles */
    public function __construct(private readonly array $roles = [])
    {
        parent::__construct();
    }

    /** @param  array<int, string>|string  $roles */
    public function hasAnyRole(array|string $roles): bool
    {
        return array_intersect((array) $roles, $this->roles) !== [];
    }
}

final class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_without_roles_support_gets_404_on_a_host_without_spatie(): void
    {
        $this->actingAs(new User)->getJson('/admin/subscribers/stats')->assertNotFound();
    }

    public function test_a_user_without_an_admin_role_gets_404(): void
    {
        $this->actingAs(new RoleUser(['editor']))->getJson('/admin/subscribers/stats')->assertNotFound();
    }

    public function test_an_admin_role_reaches_the_console(): void
    {
        $this->actingAs(new RoleUser(['admin']))->getJson('/admin/subscribers/stats')
            ->assertOk()->assertJsonPath('total', 0);
    }

    public function test_the_host_decides_through_the_gate(): void
    {
        Gate::define('subscribe.admin', fn ($user): bool => $user->email === 'boss@example.com');

        $this->actingAs((new User)->forceFill(['email' => 'boss@example.com']))->getJson('/admin/subscribers/stats')->assertOk();
        $this->actingAs(new RoleUser(['admin']))->getJson('/admin/subscribers/stats')->assertNotFound();
    }
}

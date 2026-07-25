<?php

namespace Database\Factories;

use App\Enums\OperationalStatus;
use App\Enums\UserRole;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organizational_unit_id' => null,
            'full_name' => fake()->name(),
            'position' => fake()->optional()->jobTitle(),
            'email' => fake()->unique()->safeEmail(),
            'username' => fake()->unique()->userName(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::LevelTwo,
            'status' => OperationalStatus::Active,
            'last_login_at' => null,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function levelOne(?OrganizationalUnit $unit = null): static
    {
        return $this->state(fn (): array => [
            'organizational_unit_id' => $unit?->getKey()
                ?? OrganizationalUnit::factory(),
            'role' => UserRole::LevelOne,
        ]);
    }

    public function levelTwo(): static
    {
        return $this->state(fn (): array => [
            'organizational_unit_id' => null,
            'role' => UserRole::LevelTwo,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => OperationalStatus::Inactive,
        ]);
    }
}

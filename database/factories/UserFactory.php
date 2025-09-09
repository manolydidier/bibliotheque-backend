<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        $first = $this->faker->firstName();
        $last  = $this->faker->lastName();

        return [
            // ✅ colonnes qui existent dans ta table
            'first_name'          => $first,
            'last_name'           => $last,
            'username'            => Str::slug($first.$last).$this->faker->numberBetween(10, 9999),
            'email'               => $this->faker->unique()->safeEmail(),
            'email_verified_at'   => now(),
            'password'            => Hash::make('password'), // mot de passe par défaut pour dev
            'remember_token'      => Str::random(10),

            // colonnes optionnelles qu’on voit souvent dans ton dump, mets-les à null par défaut
            'phone'               => null,
            'address'             => null,
            'birth_date'          => null,
            'profile_photo_path'  => null,

            // si ta table a ces booléens :
            'is_active'           => 1,
            'is_admin'            => 0,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}

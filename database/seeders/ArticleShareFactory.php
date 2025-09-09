<?php

namespace Database\Factories;

use App\Models\ArticleShare;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ArticleShareFactory extends Factory
{
    protected $model = ArticleShare::class;

    public function definition(): array
    {
        $method   = $this->faker->randomElement(['email','social','link','embed','print']);
        $platform = $method === 'social'
            ? $this->faker->randomElement(['facebook','twitter','linkedin','whatsapp'])
            : null;

        return [
            'uuid'        => (string) Str::uuid(),
            'tenant_id'   => null,
            'article_id'  => null,
            'user_id'     => null,
            'method'      => $method,
            'platform'    => $platform,
            'url'         => $this->faker->optional()->url(),
            'meta'        => [],
            'ip_address'  => $this->faker->ipv4(),
            'user_agent'  => $this->faker->userAgent(),
            'referrer'    => $this->faker->optional()->url(),
            'location'    => ['country' => $this->faker->country()],
            'is_converted'=> $this->faker->boolean(15),
            'converted_at'=> null,
        ];
    }
}

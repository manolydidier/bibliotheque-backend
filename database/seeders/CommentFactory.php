<?php

namespace Database\Factories;

use App\Models\Comment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CommentFactory extends Factory
{
    protected $model = Comment::class;

    public function definition(): array
    {
        return [
            'uuid'          => (string) Str::uuid(),
            'tenant_id'     => null,
            'article_id'    => null,
            'parent_id'     => null,
            'user_id'       => null,
            'guest_name'    => $this->faker->optional()->name(),
            'guest_email'   => $this->faker->optional()->safeEmail(),
            'guest_website' => $this->faker->optional()->url(),
            'content'       => $this->faker->paragraphs(rand(1, 3), true),
            'status'        => 'approved',
            'meta'          => [],
            'like_count'        => $this->faker->numberBetween(0, 50),
            'dislike_count'     => $this->faker->numberBetween(0, 10),
            'reply_count'       => 0,
            'is_featured'       => $this->faker->boolean(5),
            'moderated_by'      => null,
            'moderated_at'      => null,
            'moderation_notes'  => null,
            'created_by'        => null,
            'updated_by'        => null,
        ];
    }
}

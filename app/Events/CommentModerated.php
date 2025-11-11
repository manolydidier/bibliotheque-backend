<?php

namespace App\Events;

use App\Models\Comment; // <-- le BON import
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable; // <- bon namespace (Events\Dispatchable)
use Illuminate\Queue\SerializesModels;

class CommentModerated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Comment $comment,
        public string $action // 'approved' | 'rejected' | 'deleted'
    ) {}

    public function broadcastOn(): array
    {
        // canal privé ciblé par utilisateur
        return [new PrivateChannel('users.'.$this->recipientId())];
    }

    public function broadcastAs(): string
    {
        return 'comment.'.$this->action;
    }

    public function broadcastWith(): array
    {
        return [
            'type'         => "comment_{$this->action}",
            'recipient_id' => $this->recipientId(),
            'article_slug' => optional($this->comment->article)->slug,
            'comment_id'   => $this->comment->id,
            'title'        => match ($this->action) {
                'approved' => 'Votre commentaire a été approuvé',
                'rejected' => 'Votre commentaire a été rejeté',
                'deleted'  => 'Votre commentaire a été supprimé',
                default    => 'Mise à jour du commentaire',
            },
            'created_at'   => now()->toIso8601String(),
        ];
    }

    /**
     * Robuste si ta colonne est "user_id" ou si tu relies via $comment->user
     */
    protected function recipientId(): int|string|null
    {
        return $this->comment->user_id
            ?? optional($this->comment->user)->id
            ?? null;
    }
}

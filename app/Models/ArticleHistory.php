<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ArticleHistory extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'article_histories';

    /**
     * On conserve une PK entière auto-incrémentée.
     */
    public $incrementing = true;
    protected $keyType = 'int';

    /**
     * IMPORTANT : demander au trait de ne générer un UUID que pour la colonne 'uuid'
     * et ne pas toucher à 'id'.
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * Neutralise le comportement par défaut du trait qui mettrait keyType=string
     * et incrementing=false.
     */
    protected function initializeHasUuids(): void
    {
        // intentionnellement vide
    }

    protected $fillable = [
        'uuid',
        'tenant_id',
        'article_id',
        'user_id',
        'action',
        'changes',
        'previous_values',
        'new_values',
        'notes',
        'ip_address',
        'user_agent',
        'meta',
    ];

    protected $casts = [
        'changes' => 'array',
        'previous_values' => 'array',
        'new_values' => 'array',
        'meta' => 'array',
    ];

    // Relations
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Helpers d’affichage
    public function getActionLabel(): string
    {
        return match ($this->action) {
            'create'     => 'Créé',
            'update'     => 'Modifié',
            'publish'    => 'Publié',
            'unpublish'  => 'Dépublié',
            'archive'    => 'Archivé',
            'restore'    => 'Restauré',
            'delete'     => 'Supprimé',
            'duplicate'  => 'Dupliqué',
            'move'       => 'Déplacé',
            'feature'    => 'Mis en avant',
            'unfeature'  => 'Retiré des mises en avant',
            default      => ucfirst($this->action),
        };
    }

    public function getChangesSummary(): string
    {
        if (!$this->changes) {
            return 'Aucun changement détecté';
        }

        $summary = [];
        foreach ($this->changes as $field => $change) {
            $summary[] = ucfirst($field);
        }

        return implode(', ', $summary);
    }

    public function hasFieldChanged(string $field): bool
    {
        return $this->changes && isset($this->changes[$field]);
    }

    public function getFieldChange(string $field): ?array
    {
        return $this->changes[$field] ?? null;
    }

    public function getPreviousValue(string $field): mixed
    {
        return $this->previous_values[$field] ?? null;
    }

    public function getNewValue(string $field): mixed
    {
        return $this->new_values[$field] ?? null;
    }
}

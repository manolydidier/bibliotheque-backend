<?php

namespace App\Enums;

enum ArticleStatus: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return match($this) {
            self::DRAFT => 'Brouillon',
            self::PENDING => 'En attente',
            self::PUBLISHED => 'Publié',
            self::ARCHIVED => 'Archivé',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::DRAFT => 'gray',
            self::PENDING => 'yellow',
            self::PUBLISHED => 'green',
            self::ARCHIVED => 'red',
        };
    }

    public function isPublic(): bool
    {
        return $this === self::PUBLISHED;
    }

    public function canEdit(): bool
    {
        return in_array($this, [self::DRAFT, self::PENDING]);
    }

    public function canPublish(): bool
    {
        return in_array($this, [self::DRAFT, self::PENDING]);
    }
}

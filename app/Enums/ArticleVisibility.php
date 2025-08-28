<?php

namespace App\Enums;

enum ArticleVisibility: string
{
    case PUBLIC = 'public';
    case PRIVATE = 'private';
    case PASSWORD_PROTECTED = 'password_protected';

    public function label(): string
    {
        return match($this) {
            self::PUBLIC => 'Public',
            self::PRIVATE => 'Privé',
            self::PASSWORD_PROTECTED => 'Protégé par mot de passe',
        };
    }

    public function requiresAuth(): bool
    {
        return in_array($this, [self::PRIVATE, self::PASSWORD_PROTECTED]);
    }

    public function isAccessible(): bool
    {
        return $this === self::PUBLIC;
    }
}

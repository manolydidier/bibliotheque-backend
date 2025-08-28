<?php

namespace App\Enums;

enum ShareMethod: string
{
    case EMAIL = 'email';
    case SOCIAL = 'social';
    case LINK = 'link';
    case EMBED = 'embed';
    case PRINT = 'print';

    public function label(): string
    {
        return match($this) {
            self::EMAIL => 'Email',
            self::SOCIAL => 'Réseaux sociaux',
            self::LINK => 'Lien direct',
            self::EMBED => 'Intégration',
            self::PRINT => 'Impression',
        };
    }

    public function isSocial(): bool
    {
        return $this === self::SOCIAL;
    }

    public function isDigital(): bool
    {
        return in_array($this, [self::EMAIL, self::SOCIAL, self::LINK, self::EMBED]);
    }

    public function requiresPlatform(): bool
    {
        return $this === self::SOCIAL;
    }
}

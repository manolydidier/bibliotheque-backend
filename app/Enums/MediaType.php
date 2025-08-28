<?php

namespace App\Enums;

enum MediaType: string
{
    case IMAGE = 'image';
    case VIDEO = 'video';
    case AUDIO = 'audio';
    case DOCUMENT = 'document';
    case EMBED = 'embed';

    public function label(): string
    {
        return match($this) {
            self::IMAGE => 'Image',
            self::VIDEO => 'Vidéo',
            self::AUDIO => 'Audio',
            self::DOCUMENT => 'Document',
            self::EMBED => 'Intégration',
        };
    }

    public function isImage(): bool
    {
        return $this === self::IMAGE;
    }

    public function isVideo(): bool
    {
        return $this === self::VIDEO;
    }

    public function isAudio(): bool
    {
        return $this === self::AUDIO;
    }

    public function isDocument(): bool
    {
        return $this === self::DOCUMENT;
    }

    public function isEmbed(): bool
    {
        return $this === self::EMBED;
    }

    public function getMimeTypes(): array
    {
        return match($this) {
            self::IMAGE => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'],
            self::VIDEO => ['video/mp4', 'video/webm', 'video/ogg', 'video/avi', 'video/mov'],
            self::AUDIO => ['audio/mpeg', 'audio/ogg', 'audio/wav', 'audio/mp4', 'audio/webm'],
            self::DOCUMENT => ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            self::EMBED => ['text/html', 'application/x-embed'],
        };
    }
}

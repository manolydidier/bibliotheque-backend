<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Module Articles Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration générale du module Articles de la bibliothèque en ligne.
    | Ce fichier contient tous les paramètres configurables du module.
    |
    */

    'name' => 'Articles',
    'version' => '1.0.0',
    'description' => 'Module de gestion des articles pour la bibliothèque en ligne',

    /*
    |--------------------------------------------------------------------------
    | Configuration Générale
    |--------------------------------------------------------------------------
    */

    'general' => [
        'default_status' => 'draft',
        'default_visibility' => 'public',
        'default_allow_comments' => true,
        'default_allow_sharing' => true,
        'default_allow_rating' => true,
        'max_title_length' => 500,
        'max_excerpt_length' => 500,
        'min_content_length' => 100,
        'max_content_length' => 50000,
        'max_slug_length' => 500,
        'auto_generate_excerpt' => true,
        'auto_calculate_reading_time' => true,
        'auto_calculate_word_count' => true,
        'max_categories_per_article' => 5,
        'max_tags_per_article' => 10,
        'max_media_per_article' => 20,
        'max_comment_depth' => 3,
        'max_comment_length' => 2000,
        'min_rating' => 1,
        'max_rating' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuration des Statuts
    |--------------------------------------------------------------------------
    */

    'statuses' => [
        'draft' => [
            'label' => 'Brouillon',
            'color' => 'gray',
            'can_edit' => true,
            'can_publish' => true,
            'can_view_public' => false,
        ],
        'pending' => [
            'label' => 'En attente',
            'color' => 'yellow',
            'can_edit' => true,
            'can_publish' => true,
            'can_view_public' => false,
        ],
        'published' => [
            'label' => 'Publié',
            'color' => 'green',
            'can_edit' => true,
            'can_publish' => false,
            'can_view_public' => true,
        ],
        'archived' => [
            'label' => 'Archivé',
            'color' => 'red',
            'can_edit' => false,
            'can_publish' => false,
            'can_view_public' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuration de la Visibilité
    |--------------------------------------------------------------------------
    */

    'visibility' => [
        'public' => [
            'label' => 'Public',
            'requires_auth' => false,
            'requires_password' => false,
        ],
        'private' => [
            'label' => 'Privé',
            'requires_auth' => true,
            'requires_password' => false,
        ],
        'password_protected' => [
            'label' => 'Protégé par mot de passe',
            'requires_auth' => false,
            'requires_password' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuration des Médias
    |--------------------------------------------------------------------------
    */

    'media' => [
        'disk' => 'public',
        'max_file_size' => 10 * 1024 * 1024, // 10MB
        'allowed_mime_types' => [
            'image' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'],
            'video' => ['video/mp4', 'video/webm', 'video/ogg', 'video/avi', 'video/mov'],
            'audio' => ['audio/mpeg', 'audio/ogg', 'audio/wav', 'audio/mp4', 'audio/webm'],
            'document' => ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        ],
        'image' => [
            'max_width' => 4000,
            'max_height' => 4000,
            'thumbnails' => [
                'small' => [150, 150],
                'medium' => [300, 300],
                'large' => [600, 600],
            ],
            'quality' => 85,
            'formats' => ['jpg', 'png', 'webp'],
        ],
        'video' => [
            'max_duration' => 300, // 5 minutes
            'thumbnails' => true,
            'formats' => ['mp4', 'webm', 'ogg'],
        ],
        'audio' => [
            'max_duration' => 600, // 10 minutes
            'formats' => ['mp3', 'ogg', 'wav'],
        ],
        'compression' => [
            'enabled' => true,
            'quality' => 80,
            'max_width' => 1920,
            'max_height' => 1080,
        ],
        'watermark' => [
            'enabled' => false,
            'image' => null,
            'position' => 'bottom-right',
            'opacity' => 0.7,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuration des Commentaires
    |--------------------------------------------------------------------------
    */

    'comments' => [
        'moderation' => [
            'enabled' => true,
            'auto_approve' => false,
            'auto_approve_authenticated' => true,
            'auto_approve_verified_users' => true,
            'spam_detection' => true,
            'max_links_per_comment' => 2,
            'forbidden_words' => [
                'spam', 'scam', 'casino', 'porn', 'xxx',
            ],
        ],
        'limits' => [
            'max_per_user_per_day' => 10,
            'max_per_article' => 1000,
            'min_interval_between_comments' => 60, // seconds
        ],
        'features' => [
            'likes' => true,
            'dislikes' => true,
            'replies' => true,
            'editing' => true,
            'deletion' => true,
            'reporting' => true,
        ],
        'editing' => [
            'allowed_within_hours' => 1,
            'require_moderation' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuration des Évaluations
    |--------------------------------------------------------------------------
    */

    'ratings' => [
        'moderation' => [
            'enabled' => true,
            'auto_approve' => false,
            'auto_approve_authenticated' => true,
        ],
        'criteria' => [
            'overall' => 'Évaluation générale',
            'content_quality' => 'Qualité du contenu',
            'readability' => 'Lisibilité',
            'usefulness' => 'Utilité',
            'originality' => 'Originalité',
        ],
        'limits' => [
            'max_per_user_per_article' => 1,
            'min_interval_between_ratings' => 3600, // 1 hour
        ],
        'features' => [
            'criteria_ratings' => true,
            'reviews' => true,
            'helpful_votes' => true,
            'verification' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuration du Partage
    |--------------------------------------------------------------------------
    */

    'sharing' => [
        'platforms' => [
            'facebook' => [
                'enabled' => true,
                'app_id' => env('FACEBOOK_APP_ID'),
                'app_secret' => env('FACEBOOK_APP_SECRET'),
            ],
            'twitter' => [
                'enabled' => true,
                'api_key' => env('TWITTER_API_KEY'),
                'api_secret' => env('TWITTER_API_SECRET'),
            ],
            'linkedin' => [
                'enabled' => true,
                'client_id' => env('LINKEDIN_CLIENT_ID'),
                'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
            ],
            'whatsapp' => [
                'enabled' => true,
            ],
            'telegram' => [
                'enabled' => true,
            ],
            'email' => [
                'enabled' => true,
            ],
            'print' => [
                'enabled' => true,
            ],
        ],
        'tracking' => [
            'enabled' => true,
            'track_conversions' => true,
            'conversion_timeout' => 86400, // 24 hours
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuration SEO
    |--------------------------------------------------------------------------
    */

    'seo' => [
        'meta_tags' => [
            'auto_generate' => true,
            'max_title_length' => 60,
            'max_description_length' => 160,
            'max_keywords' => 10,
        ],
        'social_media' => [
            'open_graph' => true,
            'twitter_cards' => true,
            'default_image' => null,
        ],
        'structured_data' => [
            'enabled' => true,
            'schema_org' => true,
            'breadcrumbs' => true,
            'article' => true,
            'organization' => true,
        ],
        'sitemap' => [
            'enabled' => true,
            'auto_generate' => true,
            'update_frequency' => 'daily',
            'priority' => 0.8,
        ],
        'robots' => [
            'enabled' => true,
            'allow_indexing' => true,
            'allow_following' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuration de la Recherche
    |--------------------------------------------------------------------------
    */

    'search' => [
        'engine' => env('SEARCH_ENGINE', 'database'), // database, elasticsearch, algolia
        'full_text_search' => true,
        'fuzzy_search' => true,
        'highlight_results' => true,
        'max_results' => 100,
        'min_query_length' => 2,
        'weights' => [
            'title' => 10,
            'excerpt' => 5,
            'content' => 3,
            'tags' => 4,
            'categories' => 2,
        ],
        'filters' => [
            'status' => true,
            'visibility' => true,
            'category' => true,
            'tag' => true,
            'author' => true,
            'date_range' => true,
            'rating' => true,
            'reading_time' => true,
        ],
        'suggestions' => [
            'enabled' => true,
            'max_suggestions' => 5,
            'min_popularity' => 10,
        ],
        'autocomplete' => [
            'enabled' => true,
            'max_results' => 10,
            'min_query_length' => 1,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuration du Cache
    |--------------------------------------------------------------------------
    */

    'cache' => [
        'enabled' => true,
        'driver' => env('CACHE_DRIVER', 'redis'),
        'ttl' => [
            'articles' => 3600, // 1 hour
            'categories' => 7200, // 2 hours
            'tags' => 7200, // 2 hours
            'search_results' => 1800, // 30 minutes
            'article_views' => 86400, // 24 hours
        ],
        'tags' => [
            'articles',
            'categories',
            'tags',
            'search',
            'analytics',
        ],
        'invalidation' => [
            'on_article_update' => true,
            'on_category_update' => true,
            'on_tag_update' => true,
            'on_comment_update' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuration des Analytics
    |--------------------------------------------------------------------------
    */

    'analytics' => [
        'enabled' => true,
        'track_views' => true,
        'track_shares' => true,
        'track_comments' => true,
        'track_ratings' => true,
        'track_engagement' => true,
        'track_conversions' => true,
        'track_heatmaps' => false,
        'track_scroll_depth' => false,
        'track_time_on_page' => true,
        'track_bounce_rate' => true,
        'track_exit_rate' => true,
        'track_referrers' => true,
        'track_user_agents' => true,
        'track_geolocation' => false,
        'privacy' => [
            'anonymize_ip' => true,
            'respect_dnt' => true,
            'cookie_consent' => true,
        ],
        'retention' => [
            'views' => 90, // days
            'shares' => 90,
            'comments' => 90,
            'ratings' => 90,
            'analytics' => 365,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuration des Notifications
    |--------------------------------------------------------------------------
    */

    'notifications' => [
        'article_published' => [
            'enabled' => true,
            'channels' => ['mail', 'database'],
            'recipients' => ['author', 'moderators', 'subscribers'],
        ],
        'comment_received' => [
            'enabled' => true,
            'channels' => ['mail', 'database'],
            'recipients' => ['author', 'moderators'],
        ],
        'rating_received' => [
            'enabled' => true,
            'channels' => ['mail', 'database'],
            'recipients' => ['author'],
        ],
        'moderation_required' => [
            'enabled' => true,
            'channels' => ['mail', 'database'],
            'recipients' => ['moderators'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuration des Permissions
    |--------------------------------------------------------------------------
    */

    'permissions' => [
        'articles' => [
            'view' => 'Voir les articles',
            'create' => 'Créer des articles',
            'edit' => 'Modifier les articles',
            'delete' => 'Supprimer les articles',
            'publish' => 'Publier des articles',
            'moderate' => 'Modérer les articles',
            'view_stats' => 'Voir les statistiques',
        ],
        'categories' => [
            'view' => 'Voir les catégories',
            'create' => 'Créer des catégories',
            'edit' => 'Modifier les catégories',
            'delete' => 'Supprimer les catégories',
        ],
        'tags' => [
            'view' => 'Voir les tags',
            'create' => 'Créer des tags',
            'edit' => 'Modifier les tags',
            'delete' => 'Supprimer les tags',
        ],
        'comments' => [
            'view' => 'Voir les commentaires',
            'create' => 'Créer des commentaires',
            'edit' => 'Modifier les commentaires',
            'delete' => 'Supprimer les commentaires',
            'moderate' => 'Modérer les commentaires',
        ],
        'media' => [
            'view' => 'Voir les médias',
            'upload' => 'Uploader des médias',
            'edit' => 'Modifier les médias',
            'delete' => 'Supprimer les médias',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuration des Webhooks
    |--------------------------------------------------------------------------
    */

    'webhooks' => [
        'enabled' => true,
        'endpoints' => [
            'article_published' => env('WEBHOOK_ARTICLE_PUBLISHED'),
            'article_updated' => env('WEBHOOK_ARTICLE_UPDATED'),
            'article_deleted' => env('WEBHOOK_ARTICLE_DELETED'),
            'comment_received' => env('WEBHOOK_COMMENT_RECEIVED'),
            'rating_received' => env('WEBHOOK_RATING_RECEIVED'),
        ],
        'retry_attempts' => 3,
        'retry_delay' => 300, // 5 minutes
        'timeout' => 30, // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuration des Tests
    |--------------------------------------------------------------------------
    */

    'testing' => [
        'enabled' => true,
        'factory_count' => [
            'articles' => 50,
            'categories' => 10,
            'tags' => 30,
            'comments' => 100,
            'ratings' => 80,
        ],
        'seeder' => [
            'enabled' => true,
            'clear_existing' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuration des Logs
    |--------------------------------------------------------------------------
    */

    'logging' => [
        'enabled' => true,
        'level' => env('LOG_LEVEL', 'info'),
        'channels' => ['daily'],
        'events' => [
            'article_created' => true,
            'article_updated' => true,
            'article_deleted' => true,
            'article_published' => true,
            'comment_received' => true,
            'rating_received' => true,
            'share_tracked' => true,
        ],
    ],
];

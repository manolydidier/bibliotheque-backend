# 📚 Module Articles - Bibliothèque en Ligne

## 🎯 Vue d'ensemble

Le module Articles est un système complet de gestion de contenu pour la bibliothèque en ligne, offrant toutes les fonctionnalités nécessaires pour créer, gérer et publier des articles avec un workflow de publication professionnel.

## ✨ Fonctionnalités Principales

### 🔧 Gestion des Articles
- **CRUD complet** : Création, lecture, mise à jour, suppression
- **Workflow de publication** : Brouillon → En attente → Publié → Archivé
- **Planification** : Publication programmée à une date/heure spécifique
- **Expiration automatique** : Articles avec date de fin de validité
- **Versioning** : Historique complet des modifications
- **Duplication** : Copie d'articles avec relations

### 🏷️ Organisation du Contenu
- **Catégories hiérarchiques** : Support des sous-catégories illimitées
- **Tags dynamiques** : Système de tags avec comptage d'usage
- **Relations multiples** : Articles avec plusieurs catégories et tags
- **Ordre personnalisable** : Tri manuel des éléments

### 📱 Gestion des Médias
- **Upload multi-fichiers** : Images, vidéos, audio, documents
- **Génération automatique de thumbnails** : Redimensionnement intelligent
- **Compression automatique** : Optimisation des fichiers
- **Support multi-formats** : JPG, PNG, WebP, MP4, MP3, PDF, etc.
- **Watermarking** : Ajout automatique de filigranes

### 💬 Système de Commentaires
- **Commentaires imbriqués** : Support des réponses (3 niveaux max)
- **Modération** : Workflow d'approbation/rejet
- **Détection de spam** : Filtrage automatique
- **Système de votes** : Like/dislike sur les commentaires
- **Commentaires invités** : Support des utilisateurs non connectés

### ⭐ Système d'Évaluation
- **Notation 1-5 étoiles** : Évaluation globale des articles
- **Critères multiples** : Qualité, lisibilité, utilité, originalité
- **Modération** : Approbation manuelle des évaluations
- **Votes utiles** : Système de notation des évaluations
- **Vérification** : Confirmation par email pour les invités

### 📊 Analytics et Statistiques
- **Suivi des vues** : Comptage avec déduplication IP
- **Tracking des partages** : Suivi des méthodes de partage
- **Engagement** : Temps de lecture, taux de rebond
- **Sources de trafic** : Référents et plateformes
- **Export des données** : Rapports détaillés

### 🔍 Recherche et Filtrage
- **Recherche full-text** : Recherche dans titre, contenu, extrait
- **Filtres avancés** : Par catégorie, tag, auteur, date, statut
- **Tri intelligent** : Par popularité, date, titre, etc.
- **Suggestions** : Autocomplétion et suggestions de recherche
- **Recherche floue** : Gestion des fautes de frappe

### 🚀 SEO et Performance
- **Meta tags automatiques** : Génération automatique des balises SEO
- **Open Graph** : Intégration réseaux sociaux
- **Schema.org** : Données structurées pour les moteurs de recherche
- **Sitemap XML** : Génération automatique du sitemap
- **Cache intelligent** : Mise en cache avec invalidation granulaire

## 🏗️ Architecture Technique

### 📁 Structure des Fichiers

```
app/
├── Models/
│   ├── Article.php              # Modèle principal des articles
│   ├── Category.php             # Modèle des catégories
│   ├── Tag.php                  # Modèle des tags
│   ├── ArticleMedia.php         # Modèle des médias
│   ├── Comment.php              # Modèle des commentaires
│   ├── ArticleRating.php        # Modèle des évaluations
│   ├── ArticleShare.php         # Modèle des partages
│   └── ArticleHistory.php       # Modèle de l'historique
├── Http/
│   ├── Controllers/Api/
│   │   ├── ArticleController.php    # Contrôleur principal
│   │   ├── CategoryController.php   # Contrôleur des catégories
│   │   ├── TagController.php        # Contrôleur des tags
│   │   ├── MediaController.php      # Contrôleur des médias
│   │   ├── CommentController.php    # Contrôleur des commentaires
│   │   ├── RatingController.php     # Contrôleur des évaluations
│   │   ├── ShareController.php      # Contrôleur des partages
│   │   └── AnalyticsController.php  # Contrôleur des analytics
│   ├── Requests/
│   │   ├── StoreArticleRequest.php  # Validation création
│   │   ├── UpdateArticleRequest.php # Validation modification
│   │   └── ...                     # Autres Form Requests
│   └── Resources/
│       ├── ArticleResource.php      # Transformation des articles
│       ├── ArticleCollection.php    # Collection d'articles
│       └── ...                     # Autres API Resources
├── Services/
│   ├── ArticleService.php       # Logique métier des articles
│   ├── MediaService.php         # Gestion des médias
│   ├── ShareService.php         # Gestion du partage
│   └── AnalyticsService.php     # Calcul des statistiques
├── Events/
│   ├── ArticlePublished.php     # Événement de publication
│   ├── ArticleViewed.php        # Événement de visualisation
│   └── ArticleShared.php        # Événement de partage
├── Listeners/
│   ├── SendArticlePublishedNotification.php
│   ├── UpdateArticleViews.php
│   └── LogArticleShare.php
├── Observers/
│   ├── ArticleObserver.php      # Observer des articles
│   └── CommentObserver.php      # Observer des commentaires
└── Enums/
    ├── ArticleStatus.php        # Statuts des articles
    ├── ArticleVisibility.php    # Niveaux de visibilité
    ├── MediaType.php            # Types de médias
    └── ShareMethod.php          # Méthodes de partage
```

### 🗄️ Base de Données

#### Tables Principales
- **`articles`** : Articles avec métadonnées complètes
- **`categories`** : Catégories hiérarchiques
- **`tags`** : Tags avec comptage d'usage
- **`article_media`** : Médias associés aux articles
- **`comments`** : Commentaires avec hiérarchie
- **`article_ratings`** : Évaluations des articles
- **`article_shares`** : Suivi des partages
- **`article_histories`** : Historique des modifications

#### Relations
- **Articles ↔ Catégories** : Many-to-Many avec pivot
- **Articles ↔ Tags** : Many-to-Many avec pivot
- **Articles ↔ Médias** : One-to-Many
- **Articles ↔ Commentaires** : One-to-Many
- **Articles ↔ Évaluations** : One-to-Many
- **Articles ↔ Partages** : One-to-Many
- **Articles ↔ Historique** : One-to-Many

### 🔐 Sécurité et Permissions

#### Middleware
- **`auth:sanctum`** : Authentification pour les routes protégées
- **`throttle`** : Limitation de débit pour éviter le spam
- **`cors`** : Gestion des requêtes cross-origin

#### Permissions
- **`view`** : Voir les articles publics
- **`create`** : Créer de nouveaux articles
- **`edit`** : Modifier ses propres articles
- **`delete`** : Supprimer ses propres articles
- **`publish`** : Publier des articles
- **`moderate`** : Modérer le contenu
- **`view_stats`** : Voir les statistiques

## 🚀 Installation et Configuration

### 1. Prérequis
- Laravel 11.x
- PHP 8.2+
- MySQL 8.0+ ou PostgreSQL 13+
- Redis (recommandé pour le cache)

### 2. Installation

```bash
# Les fichiers sont déjà créés, pas besoin d'installer de package
# Vérifier que toutes les migrations sont présentes
php artisan migrate:status

# Exécuter les migrations
php artisan migrate

# Exécuter les seeders
php artisan db:seed --class=ArticleSeeder
```

### 3. Configuration

#### Variables d'environnement
```env
# Cache
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Queue (optionnel)
QUEUE_CONNECTION=redis

# Storage
FILESYSTEM_DISK=public

# Social Media (optionnel)
FACEBOOK_APP_ID=your_facebook_app_id
FACEBOOK_APP_SECRET=your_facebook_app_secret
TWITTER_API_KEY=your_twitter_api_key
TWITTER_API_SECRET=your_twitter_api_secret
LINKEDIN_CLIENT_ID=your_linkedin_client_id
LINKEDIN_CLIENT_SECRET=your_linkedin_client_secret

# Webhooks (optionnel)
WEBHOOK_ARTICLE_PUBLISHED=https://your-domain.com/webhooks/articles/published
WEBHOOK_ARTICLE_UPDATED=https://your-domain.com/webhooks/articles/updated
WEBHOOK_ARTICLE_DELETED=https://your-domain.com/webhooks/articles/deleted
```

#### Configuration du module
Le fichier `config/articles.php` contient toutes les options configurables du module.

### 4. Vérification

```bash
# Vérifier que les routes sont bien enregistrées
php artisan route:list --name=articles

# Tester l'API
curl -X GET "http://your-domain.com/api/articles"
```

## 📖 Utilisation de l'API

### 🔑 Authentification

```bash
# Login pour obtenir le token
curl -X POST "http://your-domain.com/api/login" \
  -H "Content-Type: application/json" \
  -d '{"email": "user@example.com", "password": "password"}'

# Utiliser le token dans les headers
curl -X GET "http://your-domain.com/api/articles" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 📝 Articles

#### Lister les articles
```bash
GET /api/articles
GET /api/articles?page=1&per_page=20
GET /api/articles?category_id=1&featured=true
GET /api/articles?search=laravel&sort_by=published_at&sort_direction=desc
```

#### Créer un article
```bash
POST /api/articles
{
  "title": "Mon nouvel article",
  "excerpt": "Résumé de l'article",
  "content": "Contenu complet de l'article...",
  "status": "draft",
  "visibility": "public",
  "categories": [
    {"id": 1, "is_primary": true}
  ],
  "tags": [
    {"id": 1, "sort_order": 0},
    {"id": 2, "sort_order": 1}
  ]
}
```

#### Modifier un article
```bash
PUT /api/articles/{id}
{
  "title": "Titre modifié",
  "content": "Nouveau contenu..."
}
```

#### Publier un article
```bash
POST /api/articles/{id}/publish
```

#### Supprimer un article
```bash
DELETE /api/articles/{id}
```

### 🏷️ Catégories

#### Lister les catégories
```bash
GET /api/categories
GET /api/categories?active=true&featured=true
```

#### Créer une catégorie
```bash
POST /api/categories
{
  "name": "Nouvelle catégorie",
  "description": "Description de la catégorie",
  "parent_id": null,
  "icon": "fas fa-star",
  "color": "#FF5733"
}
```

### 🏷️ Tags

#### Lister les tags
```bash
GET /api/tags
GET /api/tags?active=true&popular=true
```

#### Créer un tag
```bash
POST /api/tags
{
  "name": "nouveau-tag",
  "description": "Description du tag",
  "color": "#33FF57"
}
```

### 💬 Commentaires

#### Lister les commentaires d'un article
```bash
GET /api/articles/{article_id}/comments
```

#### Ajouter un commentaire
```bash
POST /api/articles/{article_id}/comments
{
  "content": "Mon commentaire",
  "parent_id": null
}
```

### ⭐ Évaluations

#### Évaluer un article
```bash
POST /api/articles/{article_id}/ratings
{
  "rating": 5,
  "review": "Excellent article !",
  "criteria_ratings": {
    "content_quality": 5,
    "readability": 4,
    "usefulness": 5
  }
}
```

### 📊 Analytics

#### Statistiques d'un article
```bash
GET /api/articles/{article_id}/analytics
```

#### Vue d'ensemble
```bash
GET /api/analytics/overview
```

## 🧪 Tests

### Exécuter les tests
```bash
# Tests unitaires
php artisan test --testsuite=Unit

# Tests d'intégration
php artisan test --testsuite=Feature

# Tests avec couverture
php artisan test --coverage
```

### Tests disponibles
- **ArticleTest** : Tests CRUD des articles
- **CategoryTest** : Tests des catégories
- **CommentTest** : Tests des commentaires
- **ArticleServiceTest** : Tests des services
- **MediaServiceTest** : Tests de gestion des médias

## 🔧 Maintenance

### Commandes Artisan

```bash
# Nettoyer le cache
php artisan cache:clear

# Optimiser les routes
php artisan route:cache

# Optimiser la configuration
php artisan config:cache

# Nettoyer les anciens fichiers médias
php artisan articles:cleanup-media

# Générer le sitemap
php artisan articles:generate-sitemap

# Synchroniser les statistiques
php artisan articles:sync-stats
```

### Surveillance

#### Logs
- **`storage/logs/laravel.log`** : Logs généraux
- **`storage/logs/articles.log`** : Logs spécifiques aux articles

#### Métriques
- **Vues d'articles** : Suivi de la popularité
- **Engagement** : Commentaires, partages, évaluations
- **Performance** : Temps de réponse, utilisation du cache

## 🚀 Déploiement

### Production
1. **Optimiser le cache** : `php artisan config:cache && php artisan route:cache`
2. **Configurer Redis** : Pour le cache et les sessions
3. **Configurer la queue** : Pour les tâches asynchrones
4. **Configurer le storage** : Pour les fichiers médias
5. **Configurer les webhooks** : Pour les intégrations

### Monitoring
- **Health checks** : `/api/health/articles`
- **Métriques** : Utilisation des ressources, temps de réponse
- **Alertes** : Erreurs, surcharge, problèmes de performance

## 🤝 Contribution

### Standards de code
- **PSR-12** : Standards de codage PHP
- **Laravel** : Conventions Laravel
- **Tests** : Couverture minimale de 80%
- **Documentation** : PHPDoc complet

### Workflow
1. Fork du projet
2. Création d'une branche feature
3. Développement avec tests
4. Pull request avec description détaillée

## 📄 Licence

Ce module est sous licence MIT. Voir le fichier `LICENSE` pour plus de détails.

## 🆘 Support

### Documentation
- **README** : Ce fichier
- **Code** : Commentaires inline et PHPDoc
- **Tests** : Exemples d'utilisation

### Issues
- **GitHub Issues** : Pour les bugs et demandes de fonctionnalités
- **Discussions** : Pour les questions et l'aide

### Contact
- **Email** : support@bibliotheque-online.com
- **Discord** : Serveur de la communauté

---

**Module Articles v1.0.0** - Développé avec ❤️ pour la bibliothèque en ligne

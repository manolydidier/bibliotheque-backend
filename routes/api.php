<?php

use App\Http\Controllers\Api\ActivityController;

use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\ArticleRatingController;
use App\Http\Controllers\Api\ArticleTagController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DBController;

use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\UserCreationController;
use App\Http\Controllers\UserRoleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserRolePermissionController;

use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\ShareController;
use App\Http\Controllers\Api\UserActivityController;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Api\ArticleAddController;
use App\Http\Controllers\Api\ArticleMediaController;
use App\Http\Controllers\Api\ArticleQueryController;

use App\Http\Controllers\Api\ArticleViewController;
use App\Http\Controllers\Api\BureauController;
use App\Http\Controllers\Api\CmsSectionController;
use App\Http\Controllers\Api\FileDownloadController;
use App\Http\Controllers\Api\MiradiaSlideController;
use App\Http\Controllers\Api\ModerationController;
use App\Http\Controllers\Api\NewsletterSubscriptionController;
use App\Http\Controllers\Api\ReactionController;
use App\Http\Controllers\Api\SearchSuggestionController;
use App\Http\Controllers\Api\SocieteController;
use App\Http\Controllers\ArticleSpotlightController;
use App\Http\Controllers\FileProxyController;
use App\Http\Controllers\OrgChartController;
use App\Http\Controllers\OrgNodeController;
use App\Http\Controllers\PlatformUpdatesController;
use App\Http\Controllers\PublicContactController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
use Illuminate\Support\Facades\DB;


Route::get('/check-db', function () {
    return DB::connection()->getDatabaseName();
});




Route::post('/register', [AuthController::class, 'register'])
    ->middleware('throttle:5,1'); // 5 requêtes / minute / IP
Route::get('/user/{id}/roles-permissions', [UserRolePermissionController::class, 'show'])->middleware('auth:sanctum');
Route::get('/users-roles-permissions', [UserRolePermissionController::class, 'index'])->middleware('auth:sanctum');
Route::post('/users', [UserCreationController::class, 'store']);

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::get('/validate-unique', [AuthController::class, 'validateUnique'])->middleware('throttle:30,1');

// ✅ Vérification de la robustesse du mot de passe (policy serveur)
Route::post('/password/validate', [AuthController::class, 'validatePassword'])->middleware('throttle:30,1');

// ✅ Challenge des identifiants AVANT OTP (évite d'envoyer un code si le couple email+password est faux)
Route::post('/login/challenge', [AuthController::class, 'loginChallenge'])->middleware('throttle:10,1');

    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::post('/user/{id}/avatar', [AuthController::class, 'updateAvatar'])->middleware('auth:sanctum');
    Route::post('/auth/{id}/updatepassword', [AuthController::class, 'updatePassword'])->middleware('auth:sanctum');
    Route::get('/user', [AuthController::class, 'showProfile'])->middleware('auth:sanctum');
    Route::post('/user/{id}/edit', [AuthController::class, 'updateProfile'])->middleware('auth:sanctum');
    Route::get('/user/{id}/profile', [AuthController::class, 'user'])->middleware('auth:sanctum');
    Route::get('/users', [AuthController::class, 'index'])->middleware('auth:sanctum');
   
    Route::delete('users/{id}/delete', [AuthController::class, 'delete'])->middleware('auth:sanctum');
    Route::post('users/{id}/activate', [AuthController::class, 'activate']);
    Route::post('users/{id}/deactivate', [AuthController::class, 'deactivate']);

    Route::middleware('auth:sanctum')->prefix('roles')->group(function () {
    Route::get('/', [RoleController::class, 'index']);
    Route::get('/rolesliste', [RoleController::class, 'index2']);
    Route::get('/{id}', [RoleController::class, 'show']);
    Route::post('/insert', [RoleController::class, 'store']);
    Route::put('{id}', [RoleController::class, 'update']);
    Route::delete('/{id}/delete', [RoleController::class, 'destroy']);
        });

        // Lecture accessible à tous les utilisateurs authentifiés
    Route::middleware(['auth:sanctum'])->prefix('userrole')->group(function () {
    Route::get('/', [UserRoleController::class, 'index']);
    Route::get('/{userRole}', [UserRoleController::class, 'show']);
    Route::get('/{userId}/roles', [UserRoleController::class, 'getUserRoles']);
    Route::get('/{roleId}/users', [UserRoleController::class, 'getRoleUsers']);
    Route::post('/user-roles', [UserRoleController::class, 'store']);
    Route::delete('/{roleId}/delete', [UserRoleController::class, 'destroy']);
        });

Route::middleware(['auth:sanctum'])->prefix('permissions')->group(function () {
    Route::get('/', [PermissionController::class, 'index']);           // GET    /api/permissions
    Route::post('/', [PermissionController::class, 'store']);          // POST   /api/permissions
    Route::get('/{id}', [PermissionController::class, 'show']);        // GET    /api/permissions/{id}
    Route::put('/{id}', [PermissionController::class, 'update']);      // PUT    /api/permissions/{id}
    Route::delete('/{id}', [PermissionController::class, 'destroy']);  // DELETE /api/permissions/{id}
});
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('role-permissions', RolePermissionController::class);
    Route::post('/role-permissions', [RolePermissionController::class, 'store']);
    Route::get('roles/{roleId}/permissions', [RolePermissionController::class, 'permissionsByRole']);
    Route::get('permissions/{permissionId}/roles', [RolePermissionController::class, 'rolesByPermission']);
});

// ========================================
// AUTHENTIFICATION GOOGLE - API ROUTES

Route::get('/auth/google/redirect', [AuthController::class, 'googleRedirect'])
     ->name('google.redirect');

// 2️⃣ Callback Google
Route::get('/auth/google/callback', [AuthController::class, 'googleCallback'])
     ->name('google.callback');

    Route::post('/users/{userid}/role', [UserRoleController::class, 'update']);
    // Route::get('/tables', [DBController::class, 'getTables'])->middleware('auth:sanctum');
    Route::get('/tables', [DBController::class, 'getTables']);

    // Testez ceci dans routes/web.php
    Route::get('/test-log', function() {
    Log::info('Ceci est un test de log');
    return response()->json(['message' => 'Check storage/logs/laravel.log']);
});

       Route::get('/email/exists', [EmailVerificationController::class, 'exists']);
        Route::post('/email/verification/request', [EmailVerificationController::class, 'requestCode']);
        Route::post('/email/verification/confirm', [EmailVerificationController::class, 'confirm']);

        Route::post('auth/password/forgot', [PasswordController::class, 'forgot'])->middleware('throttle:10,1');
        Route::post('auth/password/reset',  [PasswordController::class, 'reset'])->middleware('throttle:10,1');



        Route::prefix('articles')->name('articles.')->group(function () {
            Route::get('/', [ArticleController::class, 'index'])->name('index')->middleware('auth:sanctum');
            Route::get('/search', [ArticleController::class, 'search'])->name('search')->middleware('auth:sanctum');
            Route::get('/{slug}', [ArticleController::class, 'show'])->name('show')->middleware('auth:sanctum');
              // Déverrouillage par POST JSON { password: "..." }
            Route::post('{idOrSlug}/unlock', [ArticleController::class, 'unlock'])->middleware('auth:sanctum');
        });
             Route::get('/articlesbackoffice/{id}', [ArticleController::class, 'showbackoffice'])->middleware('auth:sanctum');
Route::get('search/suggestions', [SearchSuggestionController::class, 'index']);     
// ========================================
// COMMENTS - API ROUTES
Route::apiResource('comments', CommentController::class)->only(['index','store','update','destroy']);

Route::get('comments/{comment}/replies', [CommentController::class, 'replies']);

// --- NEW: show = lister les commentaires d'un article (GET /api/comments/{article})
Route::get('comments/{article}', [CommentController::class, 'show'])->whereNumber('article');

// --- NEW: route séparée pour afficher un seul commentaire (GET /api/comment/{comment})
Route::get('comment/{comment}', [CommentController::class, 'showOne']);

Route::post('comments/{id}/restore', [CommentController::class, 'restore']);

Route::post('comments/{comment}/reply',   [CommentController::class, 'reply']);
Route::post('comments/{comment}/like',    [CommentController::class, 'like']);
Route::post('comments/{comment}/dislike', [CommentController::class, 'dislike']);

Route::post('comments/{comment}/approve', [CommentController::class, 'approve']);
Route::post('comments/{comment}/reject',  [CommentController::class, 'reject']);
Route::post('comments/{comment}/spam',    [CommentController::class, 'spam']);
Route::post('comments/{comment}/feature', [CommentController::class, 'feature']);


//========================================= ADD ARTICLE, DELETE, UPDATE

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/articles/{id}', [ArticleAddController::class, 'show']);
    Route::put('/articles/{id}', [ArticleAddController::class, 'update']);
    Route::post('/articles/{id}/update-with-files', [ArticleAddController::class, 'updateWithFiles']);
    Route::delete('/articles/{id}/hard-delete', [ArticleAddController::class, 'destroy']);
    Route::post('/articles/{id}/soft-delete', [ArticleAddController::class, 'softDelete']);
    Route::post('/articles/{id}/restore', [ArticleAddController::class, 'restore']);
    Route::get('/corbeille', [ArticleAddController::class, 'corbeille']);
    

    Route::post('/articlesstore', [ArticleAddController::class, 'store']);
    Route::post('/articles/with-files', [ArticleAddController::class, 'storeWithFiles']);
Route::get('/articles-index', [ArticleQueryController::class, 'index']);
});

// ========================================

// // Categories
Route::prefix('categories')->name('categories.')->group(function () {
    // Endpoints publics
    Route::get('/categorieAdvance', [CategoryController::class, 'index2']);
    // alias tolérant (tout en minuscules et au pluriel)
    Route::get('/', [CategoryController::class, 'index'])->name('index');
    Route::get('/{category}', [CategoryController::class, 'show'])->name('show');
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/', [CategoryController::class, 'store'])->name('store');
        Route::put('/{category}', [CategoryController::class, 'update'])->name('update');
        Route::delete('/{category}', [CategoryController::class, 'destroy'])->name('destroy');
        Route::post('/{category}/toggle-active', [CategoryController::class, 'toggleActive'])->name('toggle-active');
        Route::post('/{category}/toggle-featured', [CategoryController::class, 'toggleFeatured'])->name('toggle-featured');
    });
});

 Route::get('cat/trashed', [CategoryController::class, 'trashed']);
    Route::post('cat/{id}/restore', [CategoryController::class, 'restore']);
    Route::delete('cat/{id}/force', [CategoryController::class, 'forceDelete']);
// ========================================

// // Tags
Route::prefix('tags')->name('tags.')->group(function () {
    Route::get('/tagsadvance', [TagController::class, 'index2']);
    Route::get('/', [TagController::class, 'index'])->name('index');
    Route::get('/popular', [TagController::class, 'popular'])->name('popular');
    Route::get('/{tag}', [TagController::class, 'show'])->name('show');
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/', [TagController::class, 'store'])->name('store');
        Route::put('/{tag}', [TagController::class, 'update'])->name('update');
        Route::delete('/{tag}', [TagController::class, 'destroy'])->name('destroy');
        Route::post('/{tag}/toggle-active', [TagController::class, 'toggleActive'])->name('toggle-active');
    });
});

// ========================================
// PIVOT ENTRE ARTICLES ET TAGS

Route::prefix('articlestags/{article}')->group(function () {
    Route::get('tags', [ArticleTagController::class, 'index']);       // lister
    Route::put('tags', [ArticleTagController::class, 'sync']);        // remplacer tous
    Route::post('tags', [ArticleTagController::class, 'attach']);     // ajouter 1
    Route::delete('tags/{tag}', [ArticleTagController::class, 'detach']); // retirer 1
    Route::patch('tags/reorder', [ArticleTagController::class, 'reorder']); // MAJ ordre
});

// ========================================
// ROUTES PARTAGES - API ROUTES

// Envoi d'e-mail de partage (utilisé par ton ShareButton -> shareByEmailAuto)
Route::post('/share/email', [ShareController::class, 'email'])->name('shares.email');

// Création d'un partage générique (optionnel mais recommandé pour tracer Facebook/WhatsApp/…)
Route::post('/share', [ShareController::class, 'store'])->name('shares.store');

// Marquer une conversion (ex: l’utilisateur a réalisé une action après un partage)
Route::post('/share/{share}/convert', [ShareController::class, 'convert'])->name('shares.convert');

Route::get('/share/ping', [ShareController::class, 'ping'])->name('shares.ping');

// ========================================
// MODULE RATING OU NOTES DES ARTICLES
// ========================================


Route::middleware('throttle:30,1')->group(function () {
    Route::get   ('/articles/{article}/ratings',  [ArticleRatingController::class, 'show']);
    Route::post  ('/articles/{article}/ratings',  [ArticleRatingController::class, 'store']);
    Route::put   ('/articles/{article}/ratings',  [ArticleRatingController::class, 'update']);
    Route::patch ('/articles/{article}/ratings',  [ArticleRatingController::class, 'update']);
    Route::delete('/articles/{article}/ratings',  [ArticleRatingController::class, 'destroy']);

    // (optionnel) votes d’utilité d’un avis existant
    Route::post('/articles/{article}/ratings/{rating}/vote', [ArticleRatingController::class, 'voteHelpful']);
});

// ========================================
// MODULE ACTIVITY USER - API ROUTES
// ===


Route::middleware(['auth:sanctum'])->group(function () {
    // Compteurs & users
    Route::get('/stats/articles-count', [UserActivityController::class, 'articlesCount']);
    Route::get('/stats/users-count',    [UserActivityController::class, 'usersCount']);
    Route::get('/stats/users-new',      [UserActivityController::class, 'usersNew']);
    Route::get('/stats/active-users',   [UserActivityController::class, 'usersActive']);


    // Route::get('/moderation/pending',       [UserActivityController::class, 'pendingList']);

    // Séries et trending (consommés par le Dashboard)
    Route::get('/stats/time-series',  [UserActivityController::class, 'timeSeries']);
    Route::get('/stats/trending',     [UserActivityController::class, 'trending']);

    // (optionnel) feed activités
    Route::get('/activities',         [UserActivityController::class, 'all']);
    // Route::get('/users/{user}/activities', [UserActivityController::class, 'index']);

    // (optionnel) perms effectives
    Route::get('/me/effective-permissions',      [UserActivityController::class, 'me']);
    Route::get('/users/{user}/effective-permissions', [UserActivityController::class, 'show']);

    // (optionnel) csrf utilitaire
    Route::get('/csrf', [UserActivityController::class, 'showcsrf']);
    Route::get('/article-media/stats/time-series', [UserActivityController::class, 'downloadsTimeSeries']); // Alias
     Route::get('/stats/downloads/time-series', [UserActivityController::class, 'downloadsTimeSeries']);

});


Route::post('/articles/{article}/view', [ArticleViewController::class, 'store'])
    ->name('articles.view')
    ->middleware('auth:sanctum'); 

    
Route::post('/media/{media}/download', [FileDownloadController::class, 'store'])
    ->name('media.download')
    ->middleware('auth:sanctum'); 
/*
|-----------------------------------------------------------------------
| Article Media (CRUD + actions personnalisées)
|-----------------------------------------------------------------------
| Si tu utilises Sanctum : garde 'auth:sanctum'.
| Sinon, enlève le middleware ou remplace-le par le tien.
*/
Route::middleware(['auth:sanctum'])->group(function () {
    // existants…
    Route::get   ('article-media',                      [ArticleMediaController::class, 'index']);
    Route::post  ('article-media',                      [ArticleMediaController::class, 'store']);
    Route::get   ('article-media/{id}',                 [ArticleMediaController::class, 'show']);
    Route::put   ('article-media/{id}',                 [ArticleMediaController::class, 'update']);
    Route::delete('article-media/{id}',                 [ArticleMediaController::class, 'destroy']);

    Route::post  ('article-media/upload',               [ArticleMediaController::class, 'upload']);
    Route::get   ('article-media/by-article/{article}', [ArticleMediaController::class, 'byArticle']);

    Route::post  ('article-media/{id}/toggle-active',   [ArticleMediaController::class, 'toggleActive']);
    Route::post  ('article-media/{id}/toggle-featured', [ArticleMediaController::class, 'toggleFeatured']);

    Route::post  ('article-media/{id}/restore',         [ArticleMediaController::class, 'restore']);
    Route::delete('article-media/{id}/force',           [ArticleMediaController::class, 'forceDelete']);

    // NEW: bulk actions
    Route::post('article-media/bulk/destroy',           [ArticleMediaController::class, 'bulkDestroy']);
    Route::post('article-media/bulk/toggle-active',     [ArticleMediaController::class, 'bulkToggleActive']);
    Route::post('article-media/bulk/toggle-featured',   [ArticleMediaController::class, 'bulkToggleFeatured']);
    // routes/web.php
Route::get('/media/{id}/stream', [ArticleMediaController::class, 'stream']);
Route::post('/media/{id}/download', [ArticleMediaController::class, 'increment']); // ton ping/beacon

});


// ========================================
Route::match(['GET','HEAD','OPTIONS'], '/file-proxy', [FileProxyController::class, 'handle'])
    ->name('file-proxy');



  Route::middleware(['auth:sanctum'])->group(function () { 
// ===== Articles =====
// Articles
Route::get('/articles/count',       [UserActivityController::class, 'articlesCount']);
Route::get('/stats/articles-count', [UserActivityController::class, 'articlesCount']);

// Users
Route::get('/users/count',          [UserActivityController::class, 'usersCount']);
Route::get('/stats/users-count',    [UserActivityController::class, 'usersCount']);
Route::get('/stats/users-new',      [UserActivityController::class, 'usersNew']);
Route::get('/stats/active-users',   [UserActivityController::class, 'usersActive']);
Route::get('/users/active',         [UserActivityController::class, 'usersActive']);

// Moderation
// Route::get('/moderation/pending-count', [UserActivityController::class, 'pendingCount']);
// Route::get('/moderation/pending',       [UserActivityController::class, 'pendingList']);

// Activities
Route::get('/activities',                [UserActivityController::class, 'all']);
// Route::get('/users/{user}/activities',   [UserActivityController::class, 'index']);

// Effective permissions
Route::get('/me/effective-permissions',      [UserActivityController::class, 'me']);
Route::get('/users/{user}/effective-permissions', [UserActivityController::class, 'show']);

Route::get('/stats/timeseries', [UserActivityController::class, 'timeSeries']);
Route::get('/stats/trending',   [UserActivityController::class, 'trending']);

Route::middleware('web')->get('/sanctum/csrf-cookie', [UserActivityController::class, 'showcsrf']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/reactions/toggle', [ReactionController::class, 'toggle']);
    Route::get('/reactions/counts', [ReactionController::class, 'counts']); // public if needed (but here auth)
    Route::get('/reactions/me', [ReactionController::class, 'me']);
});


Route::middleware('auth:sanctum')->group(function () {
    // Notifications personnelles
        Route::get('/users/{user}/activities', [\App\Http\Controllers\Api\MixedActivityController::class, 'index']);
           Route::get('/users/{user}/activities/count',  [\App\Http\Controllers\Api\MixedActivityController::class, 'count']);
    Route::get('/users/{user}/activities/stream', [\App\Http\Controllers\Api\MixedActivityController::class, 'stream']); // stub

    
    Route::get('/users/{user}/activitiesUser', [ActivityController::class, 'index']);
    Route::get('/users/{user}/activities/countUser', [ActivityController::class, 'count']);

    // Compteurs/modération (réservé modérateurs)
    Route::get('/moderation/pending-count', [ModerationController::class, 'pendingCount']);
    Route::get('/moderation/pending',       [ModerationController::class, 'pending']);
});

Route::post('/newsletter/subscribe', [NewsletterSubscriptionController::class, 'store'])->middleware('auth:sanctum');
 Route::get('/newsletter/subscribers', [NewsletterSubscriptionController::class, 'index'])->middleware('auth:sanctum');

Route::get('/platform/status', [PlatformUpdatesController::class, 'status']);
Route::get('/platform/updates', [PlatformUpdatesController::class, 'updates']);




/*
|--------------------------------------------------------------------------
| Routes publiques (lecture seule)
|--------------------------------------------------------------------------
*/

// Sociétés – lecture publique
Route::get('/societes', [SocieteController::class, 'index']);
Route::get('/societes/{societe}', [SocieteController::class, 'show']);

// Bureaux – lecture publique
Route::get('/bureaux', [BureauController::class, 'index']); // éventuellement filtrés par tenant ou publics
Route::get('/bureaux/{bureau}', [BureauController::class, 'show']);

// Bureaux d’une société – lecture publique
Route::get('/societes/{societe}/bureaux', [BureauController::class, 'indexBySociete']);


/*
|--------------------------------------------------------------------------
| Routes protégées (création / modification / suppression)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    // Sociétés – écriture
    Route::post('/societes', [SocieteController::class, 'store']);
    Route::match(['put', 'patch'], '/societes/{societe}', [SocieteController::class, 'update']);
    Route::delete('/societes/{societe}', [SocieteController::class, 'destroy']);
   Route::post('/societes/{societe}/active', [SocieteController::class, 'updateActive']);

    // Bureaux – écriture
    Route::post('/societes/{societe}/bureaux', [BureauController::class, 'store']);
    Route::match(['put', 'patch'], '/bureaux/{bureau}', [BureauController::class, 'update']);
    Route::delete('/bureaux/{bureau}', [BureauController::class, 'destroy']);
});
Route::middleware('auth:sanctum')->group(function () {
    // Index custom
    Route::get('bureaux', [BureauController::class, 'index']);

    // Toutes les autres routes REST sauf index
    Route::apiResource('bureaux', BureauController::class)->except(['index']);

    Route::get('societes/{societe}/bureaux', [BureauController::class, 'indexBySociete']);
    Route::post('bureaux/{bureau}/active', [BureauController::class, 'updateActive']);
});
Route::get('/public/bureaux-map', [BureauController::class, 'publicMap']);
Route::get('/public/bureaux/{bureau}', [BureauController::class, 'publicShow']);
//controleur ana email envoyer et enregistrer contact
Route::post('/public/contact', [PublicContactController::class, 'store'])->middleware('throttle:5,1');
// ✅ routes "admin" pour la boîte de réception
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/contact-messages', [PublicContactController::class, 'index']);
    Route::delete('/contact-messages/{message}', [PublicContactController::class, 'destroy']);
    Route::put('contact-messages/{contactMessage}/status', [PublicContactController::class, 'update']);

});

// controlleur pour l'article spotlight page d'acceuil, article épinglé, à la une et dernier article
Route::get('/public/articles/spotlight', [ArticleSpotlightController::class, 'index']);





/*
|--------------------------------------------------------------------------
| ROUTES MIRADIA 
|--------------------------------------------------------------------------
*/
/*
|--------------------------------------------------------------------------
| SLIDE MIRADIAS
|--------------------------------------------------------------------------
*/

Route::get('/miradia-slides', [MiradiaSlideController::class, 'index']);
Route::get('/miradia-slides/{slide}', [MiradiaSlideController::class, 'show']);

Route::post('/miradia-slides', [MiradiaSlideController::class, 'store'])->middleware('auth:sanctum');

// update accepté en PUT, PATCH et via _method
Route::match(['put', 'patch'], '/miradia-slides/{slide}', [MiradiaSlideController::class, 'update'])->middleware('auth:sanctum');

Route::delete('/miradia-slides/{slide}', [MiradiaSlideController::class, 'destroy'])->middleware('auth:sanctum');

// routes/api.php
Route::prefix('orgnodes')->group(function () {
    Route::post('/', [OrgNodeController::class, 'store']);
    Route::get('/slides', [OrgNodeController::class, 'slides']);
    Route::get('/', [OrgNodeController::class, 'index']);
    Route::get('/{orgnode}', [OrgNodeController::class, 'show']);
    Route::put('/{orgnode}', [OrgNodeController::class, 'update']);
    Route::delete('/{orgnode}', [OrgNodeController::class, 'destroy']);
    
});
    Route::get('admin-users', [OrgNodeController::class, 'indexAdminUsers']);

//  ROUTE POUR AFFICHER LES RESTES DU SITE CMS SECTIONS
    Route::middleware('auth:sanctum')->group(function () {
    Route::get('/cms-sections', [CmsSectionController::class, 'index']);
    Route::get('/cms-sections/slot', [CmsSectionController::class, 'slot']);
    Route::get('/cms-sections/{cmsSection}', [CmsSectionController::class, 'show']);

    Route::post('/cms-sections', [CmsSectionController::class, 'store']);
    Route::put('/cms-sections/{cmsSection}', [CmsSectionController::class, 'update']);
    Route::patch('/cms-sections/{cmsSection}', [CmsSectionController::class, 'update']);

    Route::delete('/cms-sections/{cmsSection}', [CmsSectionController::class, 'destroy']);

    Route::post('/cms-sections/{cmsSection}/publish', [CmsSectionController::class, 'publish']);
    Route::post('/cms-sections/{cmsSection}/unpublish', [CmsSectionController::class, 'unpublish']);
    Route::post('/cms-sections/{cmsSection}/schedule', [CmsSectionController::class, 'schedule']);
});
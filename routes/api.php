<?php

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

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
use Illuminate\Support\Facades\DB;


Route::get('/check-db', function () {
    return DB::connection()->getDatabaseName();
});




Route::post('/register', [AuthController::class, 'register']);
Route::get('/user/{id}/roles-permissions', [UserRolePermissionController::class, 'show']);
Route::get('/users-roles-permissions', [UserRolePermissionController::class, 'index']);
Route::post('/users', [UserCreationController::class, 'store']);

Route::post('/login', [AuthController::class, 'login']);
Route::get('/validate-unique', [AuthController::class, 'validateUnique']);



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
    Route::get('/articles/trashed', [ArticleAddController::class, 'trashed']);
    Route::get('/articles/{id}', [ArticleAddController::class, 'show']);
    Route::put('/articles/{id}', [ArticleAddController::class, 'update']);
    Route::post('/articles/{id}/update-with-files', [ArticleAddController::class, 'updateWithFiles']);
    Route::delete('/articles/{id}', [ArticleAddController::class, 'destroy']);
    Route::delete('/articles/{id}/soft-delete', [ArticleAddController::class, 'softDelete']);
    Route::post('/articles/{id}/restore', [ArticleAddController::class, 'restore']);
    

    Route::post('/articlesstore', [ArticleAddController::class, 'store']);
    Route::post('/articles/with-files', [ArticleAddController::class, 'storeWithFiles']);

});

// ========================================

// // Categories
Route::prefix('categories')->name('categories.')->group(function () {
    Route::get('/categorieAdvance',[categoryController::class, 'index2']);
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

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/users/{user}/activities', [UserActivityController::class, 'index']);
    Route::get('/me/effective-permissions', [UserActivityController::class, 'me']);
});



// ========================================
// MODULE ARTICLES - API ROUTES
// ========================================

// use App\Http\Controllers\Api\ArticleController;
// use App\Http\Controllers\Api\CategoryController;
// use App\Http\Controllers\Api\TagController;
// use App\Http\Controllers\Api\MediaController;
// use App\Http\Controllers\Api\CommentController;
// use App\Http\Controllers\Api\ShareController;
// use App\Http\Controllers\Api\RatingController;
// use App\Http\Controllers\Api\AnalyticsController;

// // Articles - Public routes


// // Articles - Protected routes
// Route::middleware('auth:sanctum')->prefix('articles')->name('articles.')->group(function () {
//     Route::post('/', [ArticleController::class, 'store'])->name('store');
//     Route::put('/{article}', [ArticleController::class, 'update'])->name('update');
//     Route::delete('/{article}', [ArticleController::class, 'destroy'])->name('destroy');
//     Route::post('/{article}/publish', [ArticleController::class, 'publish'])->name('publish');
//     Route::post('/{article}/unpublish', [ArticleController::class, 'unpublish'])->name('unpublish');
//     Route::post('/{article}/duplicate', [ArticleController::class, 'duplicate'])->name('duplicate');
//     Route::post('/{article}/toggle-featured', [ArticleController::class, 'toggleFeatured'])->name('toggle-featured');
//     Route::get('/{article}/stats', [ArticleController::class, 'stats'])->name('stats');
// });



// // Media
// Route::middleware('auth:sanctum')->prefix('media')->name('media.')->group(function () {
//     Route::get('/', [MediaController::class, 'index'])->name('index');
//     Route::post('/', [MediaController::class, 'store'])->name('store');
//     Route::get('/{media}', [MediaController::class, 'show'])->name('show');
//     Route::put('/{media}', [MediaController::class, 'update'])->name('update');
//     Route::delete('/{media}', [MediaController::class, 'destroy'])->name('destroy');
//     Route::post('/{media}/toggle-featured', [MediaController::class, 'toggleFeatured'])->name('toggle-featured');
//     Route::post('/upload', [MediaController::class, 'upload'])->name('upload');
//     Route::post('/bulk-upload', [MediaController::class, 'bulkUpload'])->name('bulk-upload');
// });

// // Comments
// Route::prefix('comments')->name('comments.')->group(function () {
//     Route::get('/', [CommentController::class, 'index'])->name('index');
//     Route::get('/{comment}', [CommentController::class, 'show'])->name('show');
//     Route::post('/', [CommentController::class, 'store'])->name('store');
    
//     Route::middleware('auth:sanctum')->group(function () {
//         Route::put('/{comment}', [CommentController::class, 'update'])->name('update');
//         Route::delete('/{comment}', [CommentController::class, 'destroy'])->name('destroy');
//         Route::post('/{comment}/approve', [CommentController::class, 'approve'])->name('approve');
//         Route::post('/{comment}/reject', [CommentController::class, 'reject'])->name('reject');
//         Route::post('/{comment}/mark-spam', [CommentController::class, 'markAsSpam'])->name('mark-spam');
//         Route::post('/{comment}/like', [CommentController::class, 'like'])->name('like');
//         Route::post('/{comment}/dislike', [CommentController::class, 'dislike'])->name('dislike');
//     });
// });

// // Article-specific comments
// Route::prefix('articles/{article}/comments')->name('articles.comments.')->group(function () {
//     Route::get('/', [CommentController::class, 'articleComments'])->name('index');
//     Route::post('/', [CommentController::class, 'storeArticleComment'])->name('store');
// });

// // Ratings
// Route::prefix('ratings')->name('ratings.')->group(function () {
//     Route::get('/', [RatingController::class, 'index'])->name('index');
//     Route::get('/{rating}', [RatingController::class, 'show'])->name('show');
//     Route::post('/', [RatingController::class, 'store'])->name('store');
    
//     Route::middleware('auth:sanctum')->group(function () {
//         Route::put('/{rating}', [RatingController::class, 'update'])->name('update');
//         Route::delete('/{rating}', [RatingController::class, 'destroy'])->name('destroy');
//         Route::post('/{rating}/approve', [RatingController::class, 'approve'])->name('approve');
//         Route::post('/{rating}/reject', [RatingController::class, 'reject'])->name('reject');
//         Route::post('/{rating}/mark-spam', [RatingController::class, 'markAsSpam'])->name('mark-spam');
//         Route::post('/{rating}/helpful', [RatingController::class, 'markHelpful'])->name('helpful');
//         Route::post('/{rating}/not-helpful', [RatingController::class, 'markNotHelpful'])->name('not-helpful');
//     });
// });

// // Article-specific ratings
// Route::prefix('articles/{article}/ratings')->name('articles.ratings.')->group(function () {
//     Route::get('/', [RatingController::class, 'articleRatings'])->name('index');
//     Route::post('/', [RatingController::class, 'storeArticleRating'])->name('store');
// });

// // Shares
// Route::prefix('shares')->name('shares.')->group(function () {
//     Route::get('/', [ShareController::class, 'index'])->name('index');
//     Route::post('/', [ShareController::class, 'store'])->name('store');
//     Route::get('/{share}', [ShareController::class, 'show'])->name('show');
    
//     Route::middleware('auth:sanctum')->group(function () {
//         Route::delete('/{share}', [ShareController::class, 'destroy'])->name('destroy');
//         Route::post('/{share}/mark-converted', [ShareController::class, 'markConverted'])->name('mark-converted');
//     });
// });

// // Article-specific shares
// Route::prefix('articles/{article}/shares')->name('articles.shares.')->group(function () {
//     Route::get('/', [ShareController::class, 'articleShares'])->name('index');
//     Route::post('/', [ShareController::class, 'storeArticleShare'])->name('store');
// });

// // Analytics
// Route::middleware('auth:sanctum')->prefix('analytics')->name('analytics.')->group(function () {
//     Route::get('/overview', [AnalyticsController::class, 'overview'])->name('overview');
//     Route::get('/articles', [AnalyticsController::class, 'articles'])->name('articles');
//     Route::get('/categories', [AnalyticsController::class, 'categories'])->name('categories');
//     Route::get('/tags', [AnalyticsController::class, 'tags'])->name('tags');
//     Route::get('/users', [AnalyticsController::class, 'users'])->name('users');
//     Route::get('/traffic', [AnalyticsController::class, 'traffic'])->name('traffic');
//     Route::get('/engagement', [AnalyticsController::class, 'engagement'])->name('engagement');
//     Route::get('/export', [AnalyticsController::class, 'export'])->name('export');
// });

// // Article-specific analytics
// Route::middleware('auth:sanctum')->prefix('articles/{article}/analytics')->name('articles.analytics.')->group(function () {
//     Route::get('/', [AnalyticsController::class, 'articleAnalytics'])->name('index');
//     Route::get('/views', [AnalyticsController::class, 'articleViews'])->name('views');
//     Route::get('/engagement', [AnalyticsController::class, 'articleEngagement'])->name('engagement');
//     Route::get('/shares', [AnalyticsController::class, 'articleShares'])->name('shares');
//     Route::get('/comments', [AnalyticsController::class, 'articleComments'])->name('comments');
//     Route::get('/ratings', [AnalyticsController::class, 'articleRatings'])->name('ratings');
// });

// // Bulk operations
// Route::middleware('auth:sanctum')->prefix('bulk')->name('bulk.')->group(function () {
//     Route::post('/articles/publish', [ArticleController::class, 'bulkPublish'])->name('articles.publish');
//     Route::post('/articles/unpublish', [ArticleController::class, 'bulkUnpublish'])->name('articles.unpublish');
//     Route::post('/articles/archive', [ArticleController::class, 'bulkArchive'])->name('articles.archive');
//     Route::post('/articles/delete', [ArticleController::class, 'bulkDelete'])->name('articles.delete');
//     Route::post('/articles/move-category', [ArticleController::class, 'bulkMoveCategory'])->name('articles.move-category');
//     Route::post('/articles/add-tags', [ArticleController::class, 'bulkAddTags'])->name('articles.add-tags');
//     Route::post('/articles/remove-tags', [ArticleController::class, 'bulkRemoveTags'])->name('articles.remove-tags');
// });

// // Import/Export
// Route::middleware('auth:sanctum')->prefix('import-export')->name('import-export.')->group(function () {
//     Route::post('/articles/import', [ArticleController::class, 'import'])->name('articles.import');
//     Route::get('/articles/export', [ArticleController::class, 'export'])->name('articles.export');
//     Route::get('/articles/template', [ArticleController::class, 'downloadTemplate'])->name('articles.template');
// });

// // Search and filters
// Route::prefix('search')->name('search.')->group(function () {
//     Route::get('/articles', [ArticleController::class, 'search'])->name('articles');
//     Route::get('/suggestions', [ArticleController::class, 'searchSuggestions'])->name('suggestions');
//     Route::get('/autocomplete', [ArticleController::class, 'autocomplete'])->name('autocomplete');
// });

// // Sitemap and SEO
// Route::prefix('seo')->name('seo.')->group(function () {
//     Route::get('/sitemap.xml', [ArticleController::class, 'sitemap'])->name('sitemap');
//     Route::get('/robots.txt', [ArticleController::class, 'robots'])->name('robots');
//     Route::get('/meta/{article:slug}', [ArticleController::class, 'metaTags'])->name('meta-tags');
// });

// // Preview and drafts
// Route::middleware('auth:sanctum')->prefix('preview')->name('preview.')->group(function () {
//     Route::get('/articles/{article}', [ArticleController::class, 'preview'])->name('articles.show');
//     Route::post('/articles/{article}/generate-token', [ArticleController::class, 'generatePreviewToken'])->name('articles.generate-token');
// });

// // Webhooks and integrations
// Route::prefix('webhooks')->name('webhooks.')->group(function () {
//     Route::post('/articles/published', [ArticleController::class, 'webhookPublished'])->name('articles.published');
//     Route::post('/articles/updated', [ArticleController::class, 'webhookUpdated'])->name('articles.updated');
//     Route::post('/articles/deleted', [ArticleController::class, 'webhookDeleted'])->name('articles.deleted');
// });

// // Health checks
// Route::prefix('health')->name('health.')->group(function () {
//     Route::get('/articles', [ArticleController::class, 'health'])->name('articles');
//     Route::get('/database', [ArticleController::class, 'databaseHealth'])->name('database');
//     Route::get('/cache', [ArticleController::class, 'cacheHealth'])->name('cache');
// });


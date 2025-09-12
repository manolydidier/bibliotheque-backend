<?php

use App\Http\Controllers\Api\ShareController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Mail;

Route::get('/', function () {
    return view('welcome');
});
use Illuminate\Support\Facades\DB;


Route::get('/check-db', function () {
    return DB::connection()->getDatabaseName();
});

Route::get('/_mail-test', function () {
    Mail::raw('Test SMTP OK', function ($m) {
        $m->to('ton.email@exemple.com')->subject('Test SMTP');
    });
    return 'ok';
});
// Route courte pour rediriger et incrémenter le compteur
Route::get('/s/{share}', [ShareController::class, 'redirect'])
    ->name('shares.redirect');
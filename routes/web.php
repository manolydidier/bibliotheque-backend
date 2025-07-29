<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
use Illuminate\Support\Facades\DB;


Route::get('/check-db', function () {
    return DB::connection()->getDatabaseName();
});
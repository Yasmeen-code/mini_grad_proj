<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CompilerController;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/compile', [CompilerController::class, 'compile']);

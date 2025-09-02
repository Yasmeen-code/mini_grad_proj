<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CodeController;
use App\Http\Controllers\CompilerController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Compiler API Routes
Route::prefix('compiler')->group(function () {
    Route::post('/compile', [CompilerController::class, 'compile'])->name('api.compiler.compile');
    Route::post('/step-by-step', [CompilerController::class, 'compileStepByStep'])->name('api.compiler.stepByStep');
    Route::get('/examples', [CompilerController::class, 'getExamples'])->name('api.compiler.examples');
});

// Code Management API Routes
Route::prefix('codes')->group(function () {
    Route::get('/', [CodeController::class, 'index'])->name('api.codes.index');
    Route::post('/', [CodeController::class, 'store'])->name('api.codes.store');
    Route::get('/{id}', [CodeController::class, 'show'])->name('api.codes.show');
    Route::put('/{id}', [CodeController::class, 'update'])->name('api.codes.update');
    Route::delete('/{id}', [CodeController::class, 'destroy'])->name('api.codes.destroy');
    Route::post('/compile-and-store', [CodeController::class, 'compileAndStore'])->name('api.codes.compileAndStore');
    Route::get('/history/compilation', [CodeController::class, 'getCompilationHistory'])->name('api.codes.history');
});

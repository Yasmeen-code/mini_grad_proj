<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\CodeController;
use App\Http\Controllers\CompilerController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test', function () {
    return view('test');
})->name('test');

// Authentication Routes
Route::post('/api/login', function (Request $request) {
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    if (Auth::attempt($request->only('email', 'password'))) {
        $user = Auth::user();
        $token = $user->createToken('API Token')->plainTextToken;
        return response()->json(['token' => $token]);
    }

    return response()->json(['error' => 'Unauthorized'], 401);
})->name('api.login');

// API Routes (with Sanctum authentication)
Route::middleware('auth:sanctum')->group(function () {
    // Compiler API Routes
    Route::prefix('api/compiler')->group(function () {
        Route::post('/compile', [CompilerController::class, 'compile'])->name('api.compiler.compile');
        Route::post('/step-by-step', [CompilerController::class, 'compileStepByStep'])->name('api.compiler.stepByStep');
        Route::get('/examples', [CompilerController::class, 'getExamples'])->name('api.compiler.examples');
    });

    // Code Management API Routes
    Route::prefix('api/codes')->group(function () {
        Route::get('/', [CodeController::class, 'index'])->name('api.codes.index');
        Route::post('/', [CodeController::class, 'store'])->name('api.codes.store');
        Route::get('/{id}', [CodeController::class, 'show'])->name('api.codes.show');
        Route::put('/{id}', [CodeController::class, 'update'])->name('api.codes.update');
        Route::delete('/{id}', [CodeController::class, 'destroy'])->name('api.codes.destroy');
        Route::post('/compile-and-store', [CodeController::class, 'compileAndStore'])->name('api.codes.compileAndStore');
        Route::get('/history/compilation', [CodeController::class, 'getCompilationHistory'])->name('api.codes.history');
    });
});

// Legacy route for backward compatibility
Route::post('/analyze', [CodeController::class, 'analyze'])->name('code.analyze');

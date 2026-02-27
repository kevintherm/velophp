<?php

use App\Domain\Collections\Controllers\CollectionController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/collections', [CollectionController::class, 'index']);

Route::middleware(['web'])->group(function () {
    Route::post('/login', [LoginController::class, 'store'])->name('login');
    Route::post('/register', [RegisterController::class, 'store'])->name('register');
    Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth:web')->name('logout');
    Route::get('/user', function (Request $request) {
        return response()->json(['user' => $request->user()]);
    })->middleware('auth:web');
});

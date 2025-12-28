<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DocumentController;
use Illuminate\Support\Facades\Route;

Route::get('login', [AuthController::class, 'showLogin'])->name('login');
Route::post('login', [AuthController::class, 'login']);
Route::get('logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['api.auth'])->group(function () {
    Route::get('/', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('/documents/{accountId}/{fileId}', [DocumentController::class, 'show'])->name('documents.show');
    Route::get('/documents/{accountId}/{fileId}/download', [DocumentController::class, 'download'])->name('documents.download');
});

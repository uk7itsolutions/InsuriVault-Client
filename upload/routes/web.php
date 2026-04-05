<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DocumentController;
use Illuminate\Support\Facades\Route;

Route::get('login', [AuthController::class, 'showLogin'])->name('login');
Route::post('login', [AuthController::class, 'login']);
Route::get('logout', [AuthController::class, 'logout'])->name('logout');

Route::post('biometric/assertion-options', [AuthController::class, 'getAssertionOptions']);
Route::post('biometric/complete-assertion', [AuthController::class, 'completeAssertion']);

Route::middleware(['api.auth'])->group(function () {
    Route::get('/', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('/documents/{accountId}/{fileId}', [DocumentController::class, 'show'])->name('documents.show');
    Route::get('/documents/{accountId}/{fileId}/download', [DocumentController::class, 'download'])->name('documents.download');

    Route::post('biometric/register-options', [AuthController::class, 'getRegisterOptions']);
    Route::post('biometric/complete-registration', [AuthController::class, 'completeRegistration']);
});


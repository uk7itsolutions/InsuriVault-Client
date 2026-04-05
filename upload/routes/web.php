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

Route::group(['prefix' => 'install', 'as' => 'LaravelInstaller::', 'middleware' => ['web', \App\Http\Middleware\RedirectIfInstalled::class]], function () {
    Route::get('/', [
        'as' => 'welcome',
        'uses' => '\RachidLaasri\LaravelInstaller\Controllers\WelcomeController@welcome',
    ]);

    Route::get('environment', [
        'as' => 'environment',
        'uses' => '\RachidLaasri\LaravelInstaller\Controllers\EnvironmentController@environment',
    ]);

    Route::post('environment/save', [
        'as' => 'environmentSave',
        'uses' => '\RachidLaasri\LaravelInstaller\Controllers\EnvironmentController@save',
    ]);

    Route::get('requirements', [
        'as' => 'requirements',
        'uses' => '\RachidLaasri\LaravelInstaller\Controllers\RequirementsController@requirements',
    ]);

    Route::get('permissions', [
        'as' => 'permissions',
        'uses' => '\RachidLaasri\LaravelInstaller\Controllers\PermissionsController@permissions',
    ]);

    Route::get('database', [
        'as' => 'database',
        'uses' => '\RachidLaasri\LaravelInstaller\Controllers\DatabaseController@database',
    ]);

    Route::get('final', [
        'as' => 'final',
        'uses' => '\RachidLaasri\LaravelInstaller\Controllers\FinalController@finish',
    ]);
});

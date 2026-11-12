<?php

use App\Http\Controllers\InstallController;
use Illuminate\Support\Facades\Route;

// Kreator instalacji. Po instalacji middleware EnsureInstalled zwraca 404 dla całego /install.
Route::prefix('install')->name('install.')->controller(InstallController::class)->group(function () {
    Route::get('/', 'requirements')->name('requirements');
    Route::post('language', 'language')->name('language');
    Route::get('database', 'database')->name('database');
    Route::post('database', 'saveDatabase')->name('database.save');
    Route::get('site', 'site')->name('site');
    Route::post('site', 'install')->name('run');
});

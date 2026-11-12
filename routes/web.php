<?php

use App\Http\Controllers\Cms\DashboardController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SiteController::class, 'home'])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/cms.php';

// Publiczne adresy treści (strony, wpisy): na końcu, gdy nic innego nie pasuje.
Route::fallback([SiteController::class, 'show']);

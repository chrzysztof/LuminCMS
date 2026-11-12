<?php

use App\Http\Controllers\Cms\MediaController;
use App\Http\Controllers\Cms\MediaSizeController;
use Illuminate\Support\Facades\Route;

// API panelu administracyjnego (sesja + uwierzytelnienie).
Route::middleware(['auth', 'verified'])->prefix('cms')->name('cms.')->group(function () {
    Route::get('media', [MediaController::class, 'index'])->name('media.index');
    Route::post('media', [MediaController::class, 'store'])->name('media.store');
    Route::patch('media/{media}', [MediaController::class, 'update'])->name('media.update');
    Route::put('media/{media}/crops/{size}', [MediaController::class, 'crop'])->name('media.crop');
    Route::delete('media/{media}/crops/{size}', [MediaController::class, 'destroyCrop'])->name('media.crop.destroy');
    Route::delete('media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');

    Route::get('media-sizes', [MediaSizeController::class, 'index'])->name('media-sizes.index');
    Route::post('media-sizes', [MediaSizeController::class, 'store'])->name('media-sizes.store');
    Route::post('media-sizes/regenerate', [MediaSizeController::class, 'regenerate'])->name('media-sizes.regenerate');
    Route::patch('media-sizes/{size}', [MediaSizeController::class, 'update'])->name('media-sizes.update');
    Route::delete('media-sizes/{size}', [MediaSizeController::class, 'destroy'])->name('media-sizes.destroy');
});

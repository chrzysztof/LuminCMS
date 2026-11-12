<?php

use App\Http\Controllers\Cms\BuilderController;
use App\Http\Controllers\Cms\ContentController;
use App\Http\Controllers\Cms\MediaController;
use App\Http\Controllers\Cms\MediaSizeController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

// API panelu administracyjnego (sesja + uwierzytelnienie).
Route::middleware(['auth', 'verified'])->prefix('cms')->name('cms.')->group(function () {
    Route::inertia('library', 'media/library')->name('library');

    Route::get('content/{type:key}', [ContentController::class, 'index'])->name('content.index');
    Route::get('content/{type:key}/create', [ContentController::class, 'create'])->name('content.create');
    Route::post('content/{type:key}', [ContentController::class, 'store'])->name('content.store');
    Route::get('content/{type:key}/{content}/edit', [ContentController::class, 'edit'])->name('content.edit');
    Route::put('content/{type:key}/{content}', [ContentController::class, 'update'])->name('content.update');
    Route::delete('content/{type:key}/{content}', [ContentController::class, 'destroy'])->name('content.destroy');

    Route::get('content/{type:key}/{content}/builder', [BuilderController::class, 'edit'])->name('content.builder');
    Route::put('content/{type:key}/{content}/builder', [BuilderController::class, 'save'])->name('content.builder.save');
    Route::get('content/{type:key}/{content}/preview', [SiteController::class, 'preview'])->name('content.preview');

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

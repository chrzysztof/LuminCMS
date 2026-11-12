<?php

use App\Http\Controllers\Cms\BuilderController;
use App\Http\Controllers\Cms\ContentController;
use App\Http\Controllers\Cms\FieldGroupController;
use App\Http\Controllers\Cms\MediaController;
use App\Http\Controllers\Cms\MediaSizeController;
use App\Http\Controllers\Cms\RelationController;
use App\Http\Controllers\Cms\SeoSettingsController;
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

    Route::get('settings/seo', [SeoSettingsController::class, 'edit'])->name('settings.seo');
    Route::put('settings/seo', [SeoSettingsController::class, 'update'])->name('settings.seo.update');
    Route::get('relations/{group}', RelationController::class)->name('relations');
    Route::get('fields', [FieldGroupController::class, 'index'])->name('fields.index');
    Route::get('fields/create', [FieldGroupController::class, 'create'])->name('fields.create');
    Route::post('fields', [FieldGroupController::class, 'store'])->name('fields.store');
    Route::post('fields/import', [FieldGroupController::class, 'import'])->name('fields.import');
    Route::get('fields/{group}/edit', [FieldGroupController::class, 'edit'])->name('fields.edit');
    Route::put('fields/{group}', [FieldGroupController::class, 'update'])->name('fields.update');
    Route::delete('fields/{group}', [FieldGroupController::class, 'destroy'])->name('fields.destroy');
    Route::post('fields/{group}/toggle', [FieldGroupController::class, 'toggle'])->name('fields.toggle');
    Route::post('fields/{group}/duplicate', [FieldGroupController::class, 'duplicate'])->name('fields.duplicate');
    Route::get('fields/{group}/export', [FieldGroupController::class, 'export'])->name('fields.export');

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

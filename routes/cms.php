<?php

use App\Http\Controllers\Cms\BlockController;
use App\Http\Controllers\Cms\BuilderController;
use App\Http\Controllers\Cms\ContentController;
use App\Http\Controllers\Cms\CustomizeController;
use App\Http\Controllers\Cms\FieldGroupController;
use App\Http\Controllers\Cms\MediaController;
use App\Http\Controllers\Cms\MediaSizeController;
use App\Http\Controllers\Cms\MenuController;
use App\Http\Controllers\Cms\PartController;
use App\Http\Controllers\Cms\RedirectController;
use App\Http\Controllers\Cms\RelationController;
use App\Http\Controllers\Cms\SeoSettingsController;
use App\Http\Controllers\Cms\ShortcodeController;
use App\Http\Controllers\Cms\SiteSettingsController;
use App\Http\Controllers\Cms\TaxonomyController;
use App\Http\Controllers\Cms\ThemeController;
use App\Http\Controllers\Cms\UserController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

// API panelu administracyjnego (sesja + uwierzytelnienie).
Route::middleware(['auth', 'verified'])->prefix('cms')->name('cms.')->group(function () {
    Route::inertia('library', 'media/library')->name('library')->middleware('can:upload_files');

    Route::get('content/{type:key}', [ContentController::class, 'index'])->name('content.index');
    Route::get('content/{type:key}/create', [ContentController::class, 'create'])->name('content.create');
    Route::post('content/{type:key}', [ContentController::class, 'store'])->name('content.store');
    Route::get('content/{type:key}/{content}/edit', [ContentController::class, 'edit'])->name('content.edit');
    Route::put('content/{type:key}/{content}', [ContentController::class, 'update'])->name('content.update');
    Route::delete('content/{type:key}/{content}', [ContentController::class, 'destroy'])->name('content.destroy');

    Route::post('blocks/preview', [BlockController::class, 'preview'])->name('blocks.preview')->middleware('can:edit_posts');
    Route::get('content/{type:key}/{content}/builder', [BuilderController::class, 'edit'])->name('content.builder');
    Route::put('content/{type:key}/{content}/builder', [BuilderController::class, 'save'])->name('content.builder.save');
    Route::get('content/{type:key}/{content}/preview', [SiteController::class, 'preview'])->name('content.preview');

    Route::middleware('can:manage_seo')->group(function () {
        Route::get('redirects', [RedirectController::class, 'index'])->name('redirects.index');
        Route::post('redirects', [RedirectController::class, 'store'])->name('redirects.store');
        Route::delete('redirects/logs', [RedirectController::class, 'clearLogs'])->name('redirects.logs.clear');
        Route::delete('redirects/logs/{log}', [RedirectController::class, 'destroyLog'])->name('redirects.logs.destroy');
        Route::put('redirects/pages', [RedirectController::class, 'updatePages'])->name('redirects.pages');
        Route::put('redirects/{redirect}', [RedirectController::class, 'update'])->name('redirects.update');
        Route::delete('redirects/{redirect}', [RedirectController::class, 'destroy'])->name('redirects.destroy');
        Route::post('redirects/{redirect}/toggle', [RedirectController::class, 'toggle'])->name('redirects.toggle');
        Route::get('settings/seo', [SeoSettingsController::class, 'edit'])->name('settings.seo');
        Route::put('settings/seo', [SeoSettingsController::class, 'update'])->name('settings.seo.update');
    });
    Route::middleware('can:manage_options')->group(function () {
        Route::get('settings/site', [SiteSettingsController::class, 'edit'])->name('settings.site');
        Route::put('settings/site', [SiteSettingsController::class, 'update'])->name('settings.site.update');
    });
    Route::get('relations/{group}', RelationController::class)->name('relations')->middleware('can:edit_posts');
    Route::middleware('can:manage_options')->group(function () {
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
    });

    Route::middleware('can:manage_options')->group(function () {
        Route::get('parts', [PartController::class, 'index'])->name('parts.index');
        Route::post('parts', [PartController::class, 'store'])->name('parts.store');
        Route::put('parts/slots/{slot}', [PartController::class, 'assign'])->name('parts.assign');
        Route::delete('parts/{content}', [PartController::class, 'destroy'])->name('parts.destroy');

        Route::get('themes', [ThemeController::class, 'index'])->name('themes.index');
        Route::post('themes', [ThemeController::class, 'upload'])->name('themes.upload');
        Route::post('themes/{slug}/activate', [ThemeController::class, 'activate'])->name('themes.activate');
        Route::get('themes/{slug}/export', [ThemeController::class, 'export'])->name('themes.export');
        Route::get('themes/{slug}/screenshot', [ThemeController::class, 'screenshotImage'])->name('themes.screenshot');
        Route::delete('themes/{slug}', [ThemeController::class, 'destroy'])->name('themes.destroy');

        Route::get('customize', [CustomizeController::class, 'edit'])->name('customize.edit');
        Route::put('customize', [CustomizeController::class, 'update'])->name('customize.update');
        Route::delete('customize', [CustomizeController::class, 'reset'])->name('customize.reset');

        Route::get('menus', [MenuController::class, 'index'])->name('menus.index');
        Route::post('menus', [MenuController::class, 'store'])->name('menus.store');
        Route::put('menus/{menu}', [MenuController::class, 'update'])->name('menus.update');
        Route::delete('menus/{menu}', [MenuController::class, 'destroy'])->name('menus.destroy');
    });

    Route::get('shortcodes', ShortcodeController::class)->name('shortcodes')->middleware('can:edit_posts');

    Route::middleware('can:manage_categories')->group(function () {
        Route::get('taxonomies', [TaxonomyController::class, 'index'])->name('taxonomies.index');
        Route::post('taxonomies/{taxonomy:key}/terms', [TaxonomyController::class, 'storeTerm'])->name('taxonomies.terms.store');
        Route::put('taxonomies/{taxonomy:key}/terms/{term}', [TaxonomyController::class, 'updateTerm'])->name('taxonomies.terms.update');
        Route::delete('taxonomies/{taxonomy:key}/terms/{term}', [TaxonomyController::class, 'destroyTerm'])->name('taxonomies.terms.destroy');
    });
    Route::middleware('can:manage_options')->group(function () {
        Route::post('taxonomies', [TaxonomyController::class, 'store'])->name('taxonomies.store');
        Route::put('taxonomies/{taxonomy:key}', [TaxonomyController::class, 'update'])->name('taxonomies.update');
        Route::delete('taxonomies/{taxonomy:key}', [TaxonomyController::class, 'destroy'])->name('taxonomies.destroy');
    });

    Route::middleware('can:upload_files')->group(function () {
        Route::get('media', [MediaController::class, 'index'])->name('media.index');
        Route::post('media', [MediaController::class, 'store'])->name('media.store');
        Route::patch('media/{media}', [MediaController::class, 'update'])->name('media.update');
        Route::put('media/{media}/crops/{size}', [MediaController::class, 'crop'])->name('media.crop');
        Route::delete('media/{media}/crops/{size}', [MediaController::class, 'destroyCrop'])->name('media.crop.destroy');
        Route::delete('media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');
    });

    Route::middleware('can:manage_options')->group(function () {
        Route::get('media-sizes', [MediaSizeController::class, 'index'])->name('media-sizes.index');
        Route::post('media-sizes', [MediaSizeController::class, 'store'])->name('media-sizes.store');
        Route::post('media-sizes/regenerate', [MediaSizeController::class, 'regenerate'])->name('media-sizes.regenerate');
        Route::patch('media-sizes/{size}', [MediaSizeController::class, 'update'])->name('media-sizes.update');
        Route::delete('media-sizes/{size}', [MediaSizeController::class, 'destroy'])->name('media-sizes.destroy');
    });

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::get('users/roles', [UserController::class, 'roles'])->name('users.roles');
    Route::put('users/settings', [UserController::class, 'updateSettings'])->name('users.settings');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
});

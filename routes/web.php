<?php

use App\Cms\Builder\BlockRegistry;
use App\Cms\Themes\Customizer;
use App\Http\Controllers\Cms\DashboardController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\ThemeAssetController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SiteController::class, 'home'])->name('home');
Route::get('sitemap.xml', [SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('robots.txt', [SitemapController::class, 'robots'])->name('robots');

Route::get('theme-vars.css', fn () => response(app(Customizer::class)->css(), 200, ['Content-Type' => 'text/css; charset=UTF-8', 'Cache-Control' => 'no-cache']))->name('theme.vars');
Route::get('theme-blocks.css', fn () => response(app(BlockRegistry::class)->css(), 200, ['Content-Type' => 'text/css; charset=UTF-8', 'Cache-Control' => 'no-cache']))->name('theme.blocks');
Route::get('theme-assets/{theme}/{path}', ThemeAssetController::class)->where('path', '.+')->name('theme.asset');

Route::post('locale', LocaleController::class)->name('locale');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
});

require __DIR__.'/install.php';
require __DIR__.'/settings.php';
require __DIR__.'/cms.php';

// Publiczne adresy treści (strony, wpisy): na końcu, gdy nic innego nie pasuje.
Route::fallback([SiteController::class, 'show']);

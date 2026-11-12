@php
    $siteName = app(\App\Cms\Settings\Settings::class)->get('seo', 'site_name', config('app.name'));
    $siteSettings = app(\App\Cms\Settings\Settings::class)->group('site');
    $preview = $preview ?? false;
    $chrome = $chrome ?? true;
    $parts = app(\App\Cms\Themes\Parts::class);
    $menus = app(\App\Cms\Menus\MenuRenderer::class);
    $trackOn = ! $preview && ($siteSettings['track_logged_in'] || ! auth()->check());
    $hasTracking = $siteSettings['ga_id'] !== '' || $siteSettings['gtm_id'] !== '' || $siteSettings['pixel_id'] !== '';
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @isset($seo)
        @include('theme::partials.seo', ['seo' => $seo])
    @else
        <title>@yield('title') – {{ $siteName }}</title>
        <meta name="robots" content="noindex, nofollow">
    @endisset
    @include('theme::partials.tracking', ['trackOn' => $trackOn, 'hasTracking' => $hasTracking, 'siteSettings' => $siteSettings])
    <link rel="stylesheet" href="{{ asset('css/lumin-blocks.css') }}">
    <link rel="stylesheet" href="{{ $theme->asset('theme.css') }}">
    <link rel="stylesheet" href="{{ $theme->asset('menu.css') }}">
    <style id="lm-theme-vars">{!! str_replace('</', '<\/', app(\App\Cms\Themes\Customizer::class)->css()) !!}</style>
    @stack('head')
</head>
<body>
    @if ($chrome)
        <header class="lm-site-header">
            @if (($headerHtml = $parts->html('header')) !== null)
                <div class="lm-container">{!! $headerHtml !!}</div>
            @else
                <div class="lm-container lm-site-header__bar">
                    <a class="lm-site-header__brand" href="{{ url('/') }}">{{ $siteName }}</a>
                    {!! $menus->forLocation('primary') !!}
                </div>
            @endif
        </header>
    @endif
    <main>
        @yield('content')
    </main>
    @if ($chrome)
        <footer class="lm-site-footer">
            <div class="lm-container">
                @if (($footerHtml = $parts->html('footer')) !== null)
                    {!! $footerHtml !!}
                @else
                    {!! $menus->forLocation('footer') !!}
                    © {{ date('Y') }} {{ $siteName }}
                @endif
                @if ($trackOn && $hasTracking && $siteSettings['consent_required'])
                    · <a href="#" data-lm-cookie-settings>{{ __('Ustawienia cookies') }}</a>
                @endif
            </div>
        </footer>
    @endif
    @if ($trackOn && $siteSettings['custom_body_end'] !== '')
        {!! $siteSettings['custom_body_end'] !!}
    @endif
    <script src="{{ $theme->asset('menu.js') }}" defer></script>
</body>
</html>

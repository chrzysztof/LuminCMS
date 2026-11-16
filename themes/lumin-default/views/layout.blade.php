@php
    $siteName = app(\App\Cms\Settings\Settings::class)->get('seo', 'site_name', config('app.name'));
    $identity = app(\App\Cms\Settings\SiteIdentity::class);
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
    @if ($favicon = $identity->favicon())
        <link rel="icon" href="{{ $favicon['url'] }}" type="{{ $favicon['type'] }}">
        <link rel="apple-touch-icon" href="{{ $favicon['url'] }}">
    @endif
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
    <link rel="stylesheet" href="{{ $theme->asset('parts.css') }}">
    @if (($blockCss = app(\App\Cms\Builder\BlockRegistry::class)->css()) !== '')
        <link rel="stylesheet" href="{{ url('theme-blocks.css') }}?v={{ substr(md5($blockCss), 0, 8) }}">
    @endif
    <style id="lm-theme-vars">{!! str_replace('</', '<\/', app(\App\Cms\Themes\Customizer::class)->css()) !!}</style>
    @stack('head')
</head>
<body>
    @if ($chrome)
        @php
            $headerHtml = $parts->html('header');
        @endphp
        {{-- Gotowy projekt (z edytora) ma własny element <header>; własna część bez niego dostaje opakowanie. --}}
        @if ($headerHtml !== null && str_starts_with(ltrim($headerHtml), '<header'))
            {!! $headerHtml !!}
        @else
            <header class="lm-site-header">
                @if ($headerHtml !== null)
                    <div class="lm-container">{!! $headerHtml !!}</div>
                @else
                    <div class="lm-container lm-site-header__bar">
                        <a class="lm-site-header__brand" href="{{ url('/') }}">{!! $identity->logoHtml() ?? e($siteName) !!}</a>
                        {!! $menus->forLocation('primary') !!}
                    </div>
                @endif
            </header>
        @endif
    @endif
    <main>
        @yield('content')
    </main>
    @if ($chrome)
        @php
            $footerHtml = $parts->html('footer');
            $cookieLink = $trackOn && $hasTracking && $siteSettings['consent_required'];
        @endphp
        @if ($footerHtml !== null && str_starts_with(ltrim($footerHtml), '<footer'))
            @if ($cookieLink && ($end = strrpos($footerHtml, '</footer>')) !== false)
                {!! substr_replace($footerHtml, '<div class="lm-container lm-footer__cookies"><a href="#" data-lm-cookie-settings>'.e(__('Ustawienia cookies')).'</a></div>', $end, 0) !!}
            @else
                {!! $footerHtml !!}
            @endif
        @else
            <footer class="lm-site-footer lm-site-footer--default">
                <div class="lm-container">
                    @if ($footerHtml !== null)
                        {!! $footerHtml !!}
                    @else
                        {!! $menus->forLocation('footer') !!}
                        © {{ date('Y') }} {{ $siteName }}
                    @endif
                    @if ($cookieLink)
                        · <a href="#" data-lm-cookie-settings>{{ __('Ustawienia cookies') }}</a>
                    @endif
                </div>
            </footer>
        @endif
    @endif
    @if ($trackOn && $siteSettings['custom_body_end'] !== '')
        {!! $siteSettings['custom_body_end'] !!}
    @endif
    <script src="{{ $theme->asset('menu.js') }}" defer></script>
    @if (($blockJs = app(\App\Cms\Builder\BlockRegistry::class)->js()) !== '')
        <script src="{{ url('theme-blocks.js') }}?v={{ substr(md5($blockJs), 0, 8) }}" defer></script>
    @endif
</body>
</html>

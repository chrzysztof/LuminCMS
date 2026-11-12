@php
    $siteName = app(\App\Cms\Settings\Settings::class)->get('seo', 'site_name', config('app.name'));
    $siteSettings = app(\App\Cms\Settings\Settings::class)->group('site');
    $trackOn = ! $preview && ($siteSettings['track_logged_in'] || ! auth()->check());
    $hasTracking = $siteSettings['ga_id'] !== '' || $siteSettings['gtm_id'] !== '' || $siteSettings['pixel_id'] !== '';
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('site.partials.seo', ['seo' => $seo])
    @include('site.partials.tracking', ['trackOn' => $trackOn, 'hasTracking' => $hasTracking, 'siteSettings' => $siteSettings])
    <link rel="stylesheet" href="{{ asset('css/lumin-blocks.css') }}">
</head>
<body>
    <header class="lm-site-header">
        <div class="lm-container"><a href="{{ url('/') }}">{{ $siteName }}</a></div>
    </header>
    <main>
        @if ($html !== '')
            {!! $html !!}
        @else
            <article class="lm-prose">
                <h1>{{ $content->title }}</h1>
                @if ($content->excerpt)
                    <p>{{ $content->excerpt }}</p>
                @endif
            </article>
        @endif
    </main>
    <footer class="lm-site-footer">
        <div class="lm-container">
            © {{ date('Y') }} {{ $siteName }}
            @if ($trackOn && $hasTracking && $siteSettings['consent_required'])
                · <a href="#" data-lm-cookie-settings>Ustawienia cookies</a>
            @endif
        </div>
    </footer>
    @if ($trackOn && $siteSettings['custom_body_end'] !== '')
        {!! $siteSettings['custom_body_end'] !!}
    @endif
</body>
</html>

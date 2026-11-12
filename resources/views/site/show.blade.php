<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('site.partials.seo', ['seo' => $seo])
    <link rel="stylesheet" href="{{ asset('css/lumin-blocks.css') }}">
</head>
<body>
    <header class="lm-site-header">
        <div class="lm-container"><a href="{{ url('/') }}">{{ config('app.name') }}</a></div>
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
        <div class="lm-container">© {{ date('Y') }} {{ config('app.name') }}</div>
    </footer>
</body>
</html>

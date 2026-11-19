@php
    $paths = app(\App\Cms\Content\ContentResolver::class);
    $current = $current ?? null;

    $title = trim((string) ($attrs['title'] ?? '')) !== '' ? $attrs['title'] : ($current?->title ?? __('Tytuł podstrony'));
    $subtitle = trim((string) ($attrs['subtitle'] ?? '')) !== '' ? $attrs['subtitle'] : trim((string) ($current?->excerpt ?? ''));
    $align = ($attrs['align'] ?? 'left') === 'center' ? 'center' : 'left';
    $size = in_array($attrs['size'] ?? 'medium', ['small', 'medium', 'large'], true) ? $attrs['size'] : 'medium';

    $media = null;
    $custom = null;
    $background = $attrs['background'] ?? 'none';
    if ($background === 'featured' && $current?->featured_media_id) {
        $media = \App\Cms\Media\Media::find($current->featured_media_id);
    } elseif ($background === 'custom' && is_array($attrs['image'] ?? null)) {
        $media = ! empty($attrs['image']['id']) ? \App\Cms\Media\Media::find((int) $attrs['image']['id']) : null;
        $custom = $media ? null : ($attrs['image']['url'] ?? null);
    }
    $hasImage = $media !== null || $custom !== null;

    // Ścieżka nawigacji: Strona główna › nadrzędne › bieżąca (bez ostatniego elementu jako linku).
    $trail = [];
    if (! empty($attrs['breadcrumbs']) && $current) {
        $parent = $current->parent;
        for ($i = 0; $parent && $i < 20; $i++) {
            array_unshift($trail, ['title' => $parent->title, 'url' => $paths->urlFor($parent)]);
            $parent = $parent->parent;
        }
    }
@endphp
<header class="lm-page-header lm-page-header--{{ $size }} lm-page-header--{{ $align }}@if ($hasImage) lm-page-header--image @endif">
    @if ($media)
        {!! $media->picture('large', '100vw', ['class' => 'lm-page-header__bg', 'alt' => '']) !!}
    @elseif ($custom)
        <img class="lm-page-header__bg" src="{{ $custom }}" alt="">
    @endif
    <div class="lm-container lm-page-header__inner">
        @if (! empty($attrs['breadcrumbs']))
            <nav class="lm-page-header__crumbs" aria-label="{{ __('Ścieżka nawigacji') }}">
                <ol>
                    <li><a href="{{ url('/') }}">{{ __('Strona główna') }}</a></li>
                    @foreach ($trail as $crumb)
                        <li><a href="{{ $crumb['url'] }}">{{ $crumb['title'] }}</a></li>
                    @endforeach
                    <li aria-current="page">{{ $title }}</li>
                </ol>
            </nav>
        @endif
        <h1>{{ $title }}</h1>
        @if ($subtitle !== '')
            <p class="lm-page-header__lead">{{ $subtitle }}</p>
        @endif
    </div>
</header>

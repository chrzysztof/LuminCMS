@php
    $paths = app(\App\Cms\Content\ContentResolver::class);
    $layout = ($attrs['layout'] ?? 'cards') === 'list' ? 'list' : 'cards';
    $columns = (string) (($attrs['columns'] ?? '') ?: '3');
    $columns = in_array($columns, ['2', '3', '4'], true) ? $columns : '3';
@endphp
    <div class="lm-posts-block__items lm-posts-block__items--{{ $layout }} lm-posts-block__items--cols-{{ $columns }}">
        @foreach ($items as $item)
            @php
                $media = $item->featured_media_id ? $mediaMap->get($item->featured_media_id) : null;
                $excerpt = trim((string) $item->excerpt) !== '' ? (string) $item->excerpt : \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags(preg_replace('/\[[^\]]*\]/', ' ', (string) $item->html_cache)))), 140, '…', preserveWords: true);
                $url = url($paths->pathFor($item));
            @endphp
            <article class="lm-posts-block__item">
                @if (! empty($attrs['show_image']) && $media)
                    <a class="lm-posts-block__image" href="{{ $url }}" tabindex="-1" aria-hidden="true">{!! $media->picture('medium', '(min-width: 900px) 33vw, 100vw') !!}</a>
                @endif
                <div class="lm-posts-block__body">
                    @if (! empty($attrs['show_date']) && $item->published_at)
                        <time datetime="{{ $item->published_at->toDateString() }}">{{ $item->published_at->format('d.m.Y') }}</time>
                    @endif
                    <h3><a href="{{ $url }}">{{ $item->title }}</a></h3>
                    @if (! empty($attrs['show_excerpt']) && $excerpt !== '')
                        <p>{{ $excerpt }}</p>
                    @endif
                </div>
            </article>
        @endforeach
    </div>

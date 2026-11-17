@php
    $list = app(\App\Cms\Builder\PostsList::class)->get($attrs, $current ?? null, request()->query(\App\Cms\Builder\PostsList::QUERY), (int) request()->query(\App\Cms\Builder\PostsList::PAGE_QUERY, 1));
    $items = $list['items'];
@endphp
<section class="lm-posts-block">
    <div class="lm-container">
        @if (! empty($attrs['title']))
            <h2>{{ $attrs['title'] }}</h2>
        @endif
        @if ($list['filters'])
            <nav class="lm-posts-block__filters" aria-label="{{ __('Filtr') }}">
                <a href="{{ request()->fullUrlWithQuery([\App\Cms\Builder\PostsList::QUERY => null, \App\Cms\Builder\PostsList::PAGE_QUERY => null]) }}"@if ($list['active'] === null) aria-current="true"@endif>{{ __('Wszystkie') }}</a>
                @foreach ($list['filters'] as $filter)
                    <a href="{{ request()->fullUrlWithQuery([\App\Cms\Builder\PostsList::QUERY => $filter['slug'], \App\Cms\Builder\PostsList::PAGE_QUERY => null]) }}"@if ($list['active'] === $filter['slug']) aria-current="true"@endif>{{ $filter['name'] }}</a>
                @endforeach
            </nav>
        @endif
        @if ($items->isEmpty())
            <p class="lm-posts-block__empty">{{ __('Brak wpisów.') }}</p>
        @else
            @include('theme::partials.post-cards', ['items' => $items, 'mediaMap' => $list['media'], 'attrs' => $attrs])
            @if ($list['pages'] > 1)
                <nav class="lm-posts-block__pager" aria-label="{{ __('Strony') }}">
                    @if ($list['page'] > 1)
                        <a href="{{ request()->fullUrlWithQuery([\App\Cms\Builder\PostsList::PAGE_QUERY => $list['page'] > 2 ? $list['page'] - 1 : null]) }}" rel="prev">{{ __('Poprzednia') }}</a>
                    @endif
                    @for ($n = 1; $n <= $list['pages']; $n++)
                        <a href="{{ request()->fullUrlWithQuery([\App\Cms\Builder\PostsList::PAGE_QUERY => $n > 1 ? $n : null]) }}"@if ($n === $list['page']) aria-current="page"@endif>{{ $n }}</a>
                    @endfor
                    @if ($list['page'] < $list['pages'])
                        <a href="{{ request()->fullUrlWithQuery([\App\Cms\Builder\PostsList::PAGE_QUERY => $list['page'] + 1]) }}" rel="next">{{ __('Następna') }}</a>
                    @endif
                </nav>
            @endif
        @endif
    </div>
</section>

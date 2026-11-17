@php
    $listing = app(\App\Cms\Themes\Listing::class);
    $items = $listing->items;
@endphp
<section class="lm-posts-block lm-archive-items">
    @if (! $listing->active())
        <p class="lm-posts-block__empty lm-template-placeholder">{{ __('Tutaj pojawi się lista treści archiwum.') }}</p>
    @elseif ($items->isEmpty())
        <p class="lm-posts-block__empty">{{ __('Brak wpisów.') }}</p>
    @else
        @include('theme::partials.post-cards', ['items' => $items, 'mediaMap' => \App\Cms\Media\Media::whereIn('id', $items->pluck('featured_media_id')->filter()->all())->get()->keyBy('id'), 'attrs' => $attrs])
        @if ($items->hasPages())
            <nav class="lm-posts-block__pager" aria-label="{{ __('Strony') }}">
                @if ($items->previousPageUrl())
                    <a rel="prev" href="{{ $items->previousPageUrl() }}">{{ __('Nowsze') }}</a>
                @endif
                <a aria-current="page" href="{{ $items->url($items->currentPage()) }}">{{ $items->currentPage() }} / {{ $items->lastPage() }}</a>
                @if ($items->nextPageUrl())
                    <a rel="next" href="{{ $items->nextPageUrl() }}">{{ __('Starsze') }}</a>
                @endif
            </nav>
        @endif
    @endif
</section>

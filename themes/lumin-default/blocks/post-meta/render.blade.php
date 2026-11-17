@php
    $terms = ! empty($current) && ! empty($attrs['show_terms']) ? $current->terms()->with('taxonomy')->get() : collect();
    $archives = app(\App\Cms\Content\ArchiveResolver::class);
    $date = ! empty($current) ? $current->published_at : now();
    $author = ! empty($current) && ! empty($attrs['show_author']) ? $current->author?->name : null;
@endphp
<div class="lm-post-meta">
    @if (! empty($attrs['show_date']) && $date)
        <time datetime="{{ $date->toDateString() }}">{{ $date->format('d.m.Y') }}</time>
    @endif
    @if ($author)
        <span class="lm-post-meta__author">{{ $author }}</span>
    @endif
    @forelse ($terms as $term)
        <a class="lm-term" href="{{ $archives->urlForTerm($term) }}">{{ $term->name }}</a>
    @empty
        @if (empty($current) && ! empty($attrs['show_terms']))
            <span class="lm-term">{{ __('Kategoria') }}</span>
        @endif
    @endforelse
</div>

@extends('theme::layout')

@section('content')
    <section class="lm-container lm-archive">
        <h1>{{ $heading }}</h1>
        @if (! empty($description))
            <p class="lm-archive-lead">{{ $description }}</p>
        @endif

        @forelse ($items as $item)
            <article class="lm-archive-item">
                <h2><a href="{{ $paths->urlFor($item) }}">{{ $item->title }}</a></h2>
                @if ($item->published_at)
                    <time datetime="{{ $item->published_at->toDateString() }}">{{ $item->published_at->format('d.m.Y') }}</time>
                @endif
                @if ($item->excerpt)
                    <p>{{ $item->excerpt }}</p>
                @endif
            </article>
        @empty
            <p>{{ __('Brak wpisów.') }}</p>
        @endforelse

        @if ($items->hasPages())
            <nav class="lm-pagination" aria-label="{{ __('Strony') }}">
                @if ($items->previousPageUrl())
                    <a rel="prev" href="{{ $items->previousPageUrl() }}">← {{ __('Nowsze') }}</a>
                @endif
                <span>{{ $items->currentPage() }} / {{ $items->lastPage() }}</span>
                @if ($items->nextPageUrl())
                    <a rel="next" href="{{ $items->nextPageUrl() }}">{{ __('Starsze') }} →</a>
                @endif
            </nav>
        @endif
    </section>
@endsection

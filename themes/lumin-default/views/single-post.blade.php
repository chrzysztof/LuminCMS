@extends('theme::layout')

@section('content')
    @php $terms = $content->terms()->with('taxonomy')->get(); @endphp
    @if ($content->published_at || $terms->isNotEmpty())
        <div class="lm-container lm-post-meta">
            @if ($content->published_at)
                <time datetime="{{ $content->published_at->toDateString() }}">{{ $content->published_at->format('d.m.Y') }}</time>
            @endif
            @foreach ($terms as $term)
                <a class="lm-term" href="{{ app(\App\Cms\Content\ArchiveResolver::class)->urlForTerm($term) }}">{{ $term->name }}</a>
            @endforeach
        </div>
    @endif
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
@endsection

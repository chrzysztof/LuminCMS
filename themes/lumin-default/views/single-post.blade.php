@extends('theme::layout')

@section('content')
    @if ($content->builder_data)
        {!! $html !!}
    @else
        @php $terms = $content->terms()->with('taxonomy')->get(); @endphp
        <article class="lm-article">
            <header class="lm-article__header">
                @if ($content->published_at || $terms->isNotEmpty())
                    <div class="lm-post-meta">
                        @if ($content->published_at)
                            <time datetime="{{ $content->published_at->toDateString() }}">{{ $content->published_at->format('d.m.Y') }}</time>
                        @endif
                        @foreach ($terms as $term)
                            <a class="lm-term" href="{{ app(\App\Cms\Content\ArchiveResolver::class)->urlForTerm($term) }}">{{ $term->name }}</a>
                        @endforeach
                    </div>
                @endif
                <h1>{{ $content->title }}</h1>
                @if ($content->excerpt)
                    <p class="lm-article__lead">{{ $content->excerpt }}</p>
                @endif
            </header>
            <div class="lm-article__body">{!! $html !!}</div>
        </article>
    @endif
@endsection

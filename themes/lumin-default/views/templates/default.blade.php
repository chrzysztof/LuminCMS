@extends('theme::layout')

@section('content')
    @if ($content->builder_data)
        {!! $html !!}
    @else
        <article class="lm-article">
            <header class="lm-article__header">
                <h1>{{ $content->title }}</h1>
                @if ($content->excerpt)
                    <p class="lm-article__lead">{{ $content->excerpt }}</p>
                @endif
            </header>
            <div class="lm-article__body">{!! $html !!}</div>
        </article>
    @endif
@endsection

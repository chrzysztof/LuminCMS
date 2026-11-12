@extends('theme::layout')

@section('content')
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

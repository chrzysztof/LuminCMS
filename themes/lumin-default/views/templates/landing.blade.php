@extends('theme::layout', ['chrome' => false])

@section('content')
    @if ($html !== '')
        {!! $html !!}
    @else
        <article class="lm-prose">
            <h1>{{ $content->title }}</h1>
        </article>
    @endif
@endsection

@extends('install.layout')
@section('title', __('Requirements'))
@section('content')
    <h1>{{ __('Welcome to Lumin CMS') }}</h1>
    <p class="lead">{{ __('Let’s check that your server is ready. Installation takes about a minute.') }}</p>
    <ul class="list">
        @foreach($checks as $c)
            <li>
                <span class="mark {{ $c['ok'] ? 'ok' : ($c['required'] ? 'bad' : 'warn') }}">{{ $c['ok'] ? '✓' : ($c['required'] ? '✗' : '!') }}</span>
                <span>{{ $c['label'] }}@unless($c['ok'])<span class="small" style="display:block">{{ $c['hint'] }}</span>@endunless</span>
            </li>
        @endforeach
    </ul>
    @if($passes)
        <a class="btn" href="{{ route('install.database') }}">{{ __('Continue') }}</a>
    @else
        <p class="err">{{ __('Fix the items marked ✗, then reload this page.') }}</p>
        <a class="btn" href="{{ route('install.requirements') }}">{{ __('Check again') }}</a>
    @endif
@endsection

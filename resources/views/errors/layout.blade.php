@extends('theme::layout')

@push('head')
    <style>
        .lm-error { min-height: 60vh; display: grid; place-content: center; text-align: center; padding: 4rem 1rem; gap: 1rem; }
        .lm-error-code { font-size: clamp(4rem, 14vw, 8rem); font-weight: 800; line-height: 1; margin: 0; color: var(--lm-brand, #5b4bdb); letter-spacing: -0.04em; }
        .lm-error h1 { font-size: 1.5rem; margin: 0; }
        .lm-error p { margin: 0 auto; max-width: 32rem; opacity: .75; }
    </style>
@endpush

@section('content')
    <div class="lm-error">
        <p class="lm-error-code" aria-hidden="true">@yield('code')</p>
        <h1>@yield('heading')</h1>
        <p>@yield('message')</p>
        <div><a class="lm-btn" href="{{ url('/') }}">{{ __('Wróć na stronę główną') }}</a></div>
    </div>
@endsection

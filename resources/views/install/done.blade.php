@extends('install.layout', ['hideLang' => true])
@section('title', __('Done'))
@section('content')
    <h1>{{ __('Lumin CMS is installed') }}</h1>
    <p class="lead">{{ __('Sign in with :email to open the panel.', ['email' => $email]) }}</p>
    <a class="btn" href="{{ $login }}">{{ __('Go to sign in') }}</a>
    <p class="small" style="margin-top:20px">{{ __('The installer is now disabled. You can close this page.') }}</p>
@endsection

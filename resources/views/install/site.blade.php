@extends('install.layout')
@section('title', __('Site and administrator'))
@section('content')
    <h1>{{ __('Your site') }}</h1>
    <p class="lead">{{ __('Name the site and create the administrator account.') }}</p>
    @error('install')<div class="alert">{{ $message }}</div>@enderror
    <form method="post" action="{{ route('install.run') }}" onsubmit="this.querySelector('button').disabled = true">
        @csrf
        <label for="site_name">{{ __('Site name') }}</label>
        <input type="text" id="site_name" name="site_name" value="{{ old('site_name') }}" required>
        @error('site_name')<div class="err">{{ $message }}</div>@enderror

        <label for="url">{{ __('Site address') }}</label>
        <input type="url" id="url" name="url" value="{{ old('url', $url) }}" required>
        @error('url')<div class="err">{{ $message }}</div>@enderror

        <label for="locale">{{ __('Panel language') }}</label>
        <select id="locale" name="locale">
            @foreach(\App\Cms\I18n\Locales::SUPPORTED as $code => $name)
                <option value="{{ $code }}" @selected(old('locale', app()->getLocale()) === $code)>{{ $name }}</option>
            @endforeach
        </select>

        <label for="admin_name">{{ __('Name') }}</label>
        <input type="text" id="admin_name" name="admin_name" value="{{ old('admin_name') }}" autocomplete="name" required>
        @error('admin_name')<div class="err">{{ $message }}</div>@enderror

        <label for="admin_email">{{ __('Email') }}</label>
        <input type="email" id="admin_email" name="admin_email" value="{{ old('admin_email') }}" autocomplete="email" required>
        @error('admin_email')<div class="err">{{ $message }}</div>@enderror

        <label for="admin_password">{{ __('Password') }}</label>
        <input type="password" id="admin_password" name="admin_password" autocomplete="new-password" minlength="8" required>
        <div class="hint">{{ __('At least 8 characters.') }}</div>
        @error('admin_password')<div class="err">{{ $message }}</div>@enderror

        <label for="admin_password_confirmation">{{ __('Confirm password') }}</label>
        <input type="password" id="admin_password_confirmation" name="admin_password_confirmation" autocomplete="new-password" required>

        <label class="check"><input type="checkbox" name="starter" value="1" @checked(old('starter', true))><span>{{ __('Add sample content: home page, about page, a sample post and a main menu') }}</span></label>

        <button type="submit">{{ __('Install') }}</button>
    </form>
@endsection

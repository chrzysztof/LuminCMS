@extends('install.layout')
@section('title', __('Database'))
@section('content')
    <h1>{{ __('Database') }}</h1>
    <p class="lead">{{ __('Choose where Lumin CMS will store its data. SQLite needs no setup.') }}</p>
    @error('connection')<div class="alert">{{ $message }}</div>@enderror
    <form method="post" action="{{ route('install.database.save') }}" id="db">
        @csrf
        <label for="driver">{{ __('Database type') }}</label>
        <select name="driver" id="driver">
            @foreach($drivers as $key => $name)
                <option value="{{ $key }}" @selected(old('driver', $values['driver']) === $key)>{{ $name }}</option>
            @endforeach
        </select>
        @error('driver')<div class="err">{{ $message }}</div>@enderror
        <div data-server>
            <div class="row">
                <div><label for="host">{{ __('Host') }}</label><input type="text" id="host" name="host" value="{{ old('host', $values['host']) }}"></div>
                <div><label for="port">{{ __('Port') }}</label><input type="number" id="port" name="port" value="{{ old('port', $values['port']) }}"></div>
            </div>
            @error('host')<div class="err">{{ $message }}</div>@enderror
            @error('port')<div class="err">{{ $message }}</div>@enderror
            <label for="database">{{ __('Database name') }}</label>
            <input type="text" id="database" name="database" value="{{ old('database', $values['database']) }}">
            <div class="hint">{{ __('The database must already exist.') }}</div>
            @error('database')<div class="err">{{ $message }}</div>@enderror
            <label for="username">{{ __('Username') }}</label>
            <input type="text" id="username" name="username" autocomplete="off" value="{{ old('username', $values['username']) }}">
            @error('username')<div class="err">{{ $message }}</div>@enderror
            <label for="password">{{ __('Password') }}</label>
            <input type="password" id="password" name="password" autocomplete="off">
        </div>
        <p class="small" data-sqlite style="display:none">{{ __('The database will be stored in the file database/database.sqlite.') }}</p>
        <button type="submit">{{ __('Test connection and continue') }}</button>
    </form>
    <script>
        (function () {
            var form = document.getElementById('db'), sel = document.getElementById('driver');
            var ports = {mysql: '3306', pgsql: '5432'};
            function sync(user) {
                var sqlite = sel.value === 'sqlite';
                form.querySelector('[data-server]').style.display = sqlite ? 'none' : '';
                form.querySelector('[data-sqlite]').style.display = sqlite ? '' : 'none';
                if (user && ports[sel.value]) document.getElementById('port').value = ports[sel.value];
            }
            sel.addEventListener('change', function () { sync(true); });
            sync(false);
        })();
    </script>
@endsection

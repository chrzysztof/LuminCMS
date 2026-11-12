<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Lumin CMS') · {{ __('Installation') }}</title>
    <style>
        :root { --bg:#f6f7f9; --card:#fff; --text:#16181d; --muted:#62697a; --line:#dde1e8; --brand:#4f46e5; --brand-text:#fff; --ok:#15803d; --warn:#b45309; --bad:#b91c1c; --input:#fff; }
        @media (prefers-color-scheme: dark) { :root { --bg:#0e1014; --card:#171a21; --text:#eceef3; --muted:#9aa2b3; --line:#2a2f3a; --brand:#818cf8; --brand-text:#0e1014; --ok:#4ade80; --warn:#fbbf24; --bad:#f87171; --input:#10131a; } }
        * { box-sizing: border-box; }
        body { margin:0; background:var(--bg); color:var(--text); font:16px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; }
        .wrap { max-width:640px; margin:0 auto; padding:32px 16px 64px; }
        header { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:24px; }
        .logo { font-weight:700; font-size:20px; letter-spacing:-.01em; }
        .steps { display:flex; gap:6px; list-style:none; padding:0; margin:0 0 20px; }
        .steps li { flex:1; height:4px; border-radius:2px; background:var(--line); }
        .steps li.on { background:var(--brand); }
        .card { background:var(--card); border:1px solid var(--line); border-radius:12px; padding:24px; }
        h1 { font-size:22px; margin:0 0 4px; }
        p.lead { margin:0 0 20px; color:var(--muted); }
        label { display:block; font-weight:600; font-size:14px; margin:16px 0 6px; }
        input[type=text],input[type=url],input[type=email],input[type=password],input[type=number],select { width:100%; padding:10px 12px; font:inherit; color:var(--text); background:var(--input); border:1px solid var(--line); border-radius:8px; }
        input:focus,select:focus { outline:2px solid var(--brand); outline-offset:1px; }
        .hint { color:var(--muted); font-size:13px; margin-top:4px; }
        .row { display:grid; grid-template-columns:1fr 120px; gap:12px; }
        .check { display:flex; gap:10px; align-items:flex-start; margin-top:20px; font-weight:400; }
        .check input { margin-top:5px; }
        .err { color:var(--bad); font-size:14px; margin-top:6px; }
        .alert { border:1px solid var(--bad); color:var(--bad); border-radius:8px; padding:10px 12px; margin-bottom:16px; font-size:14px; }
        button,.btn { display:inline-block; margin-top:24px; padding:10px 20px; font:inherit; font-weight:600; color:var(--brand-text); background:var(--brand); border:0; border-radius:8px; cursor:pointer; text-decoration:none; }
        button[disabled] { opacity:.5; cursor:not-allowed; }
        .list { list-style:none; margin:0; padding:0; }
        .list li { display:flex; gap:10px; padding:8px 0; border-bottom:1px solid var(--line); font-size:15px; }
        .list li:last-child { border:0; }
        .mark { flex:none; width:20px; font-weight:700; }
        .ok { color:var(--ok); } .warn { color:var(--warn); } .bad { color:var(--bad); }
        .small { font-size:13px; color:var(--muted); }
        .lang { padding:6px 8px; font-size:14px; width:auto; }
        .sqlite-hide [data-server] { display:none; }
    </style>
</head>
<body>
<div class="wrap">
    <header>
        <div class="logo">Lumin CMS</div>
        @if(! ($hideLang ?? false))
            <form method="post" action="{{ route('install.language') }}">
                @csrf
                <select name="locale" class="lang" aria-label="{{ __('Language') }}" onchange="this.form.submit()">
                    @foreach(\App\Cms\I18n\Locales::SUPPORTED as $code => $name)
                        <option value="{{ $code }}" @selected(app()->getLocale() === $code)>{{ $name }}</option>
                    @endforeach
                </select>
                <noscript><button type="submit" style="margin:0">OK</button></noscript>
            </form>
        @endif
    </header>
    <ol class="steps" aria-hidden="true">@for($i = 1; $i <= 4; $i++)<li @class(['on' => $i <= ($step ?? 1)])></li>@endfor</ol>
    <main class="card">@yield('content')</main>
</div>
</body>
</html>

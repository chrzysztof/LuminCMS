@extends('errors.layout', ['siteName' => app(\App\Cms\Settings\Settings::class)->get('seo', 'site_name', config('app.name'))])

@section('title', 'Nie znaleziono strony')
@section('code', '404')
@section('heading', 'Nie znaleziono strony')
@section('message', 'Strona, której szukasz, nie istnieje albo została przeniesiona. Sprawdź adres lub wróć na stronę główną.')

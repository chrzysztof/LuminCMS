@extends('errors.layout', ['siteName' => app(\App\Cms\Settings\Settings::class)->get('seo', 'site_name', config('app.name'))])

@section('title', __('Strona usunięta'))
@section('code', '410')
@section('heading', __('Ta strona została trwale usunięta'))
@section('message', __('Treść pod tym adresem nie jest już dostępna i nie zostanie przywrócona.'))

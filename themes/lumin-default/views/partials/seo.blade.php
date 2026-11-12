<title>{{ $seo['title'] }}</title>
@if ($seo['description'] !== '')
    <meta name="description" content="{{ $seo['description'] }}">
@endif
<meta name="robots" content="{{ $seo['robots'] }}">
@if ($seo['canonical'])
    <link rel="canonical" href="{{ $seo['canonical'] }}">
@endif
@foreach ($seo['og'] as $property => $value)
    <meta property="{{ $property }}" content="{{ $value }}">
@endforeach
@foreach ($seo['twitter'] as $name => $value)
    <meta name="{{ $name }}" content="{{ $value }}">
@endforeach
<script type="application/ld+json">{!! json_encode($seo['jsonld'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>

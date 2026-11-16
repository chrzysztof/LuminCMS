@php
    $menus = app(\App\Cms\Menus\MenuRenderer::class);
    $location = in_array($attrs['location'] ?? '', ['primary', 'footer'], true) ? $attrs['location'] : 'primary';
    $html = $menus->forLocation($location);

    // Stopka bez własnego menu pokazuje menu główne zamiast pustego miejsca.
    if (trim($html) === '' && $location === 'footer') {
        $html = $menus->forLocation('primary');
    }
@endphp
{!! $html !!}

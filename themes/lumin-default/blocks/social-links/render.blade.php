@php
    $settings = app(\App\Cms\Settings\Settings::class);
    $networks = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'x' => 'X', 'youtube' => 'YouTube', 'linkedin' => 'LinkedIn', 'tiktok' => 'TikTok'];
    $links = array_filter(array_map(fn ($key) => (string) $settings->get('site', $key, ''), array_keys($networks)));
@endphp
@if ($links)
    <ul class="lm-social">
        @foreach ($links as $key => $url)
            <li><a href="{{ $url }}" target="_blank" rel="noopener noreferrer">{{ $networks[$key] }}</a></li>
        @endforeach
    </ul>
@elseif (request()->routeIs('cms.*'))
    {{-- Podpowiedź tylko w panelu (edytor, podgląd projektów); odwiedzający nie widzą pustego bloku. --}}
    <p class="lm-social lm-social--empty">{{ __('Dodaj linki do mediów społecznościowych w ustawieniach witryny.') }}</p>
@endif

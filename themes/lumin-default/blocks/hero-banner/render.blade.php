@php
    $image = is_array($attrs['image'] ?? null) ? $attrs['image'] : null;
    $align = in_array($attrs['align'] ?? 'left', ['left', 'center'], true) ? $attrs['align'] : 'left';
@endphp
<section class="lm-hero-banner lm-hero-banner--{{ $align }}"@if ($image) style="background-image:url('{{ $image['url'] ?? '' }}')"@endif>
    <div class="lm-container lm-hero-banner__inner">
        @if (! empty($attrs['heading']))
            <h2>{{ $attrs['heading'] }}</h2>
        @endif
        @if (! empty($attrs['text']))
            <p>{{ $attrs['text'] }}</p>
        @endif
        @if (! empty($attrs['button_text']) && ! empty($attrs['button_url']))
            <a class="lm-btn" href="{{ $attrs['button_url'] }}">{{ $attrs['button_text'] }}</a>
        @endif
    </div>
</section>

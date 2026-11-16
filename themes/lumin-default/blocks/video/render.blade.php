@php
    $url = trim((string) ($attrs['url'] ?? ''));
    $title = (string) ($attrs['title'] ?? '');
    $ratio = in_array($attrs['ratio'] ?? '16-9', ['16-9', '4-3', '1-1', '9-16'], true) ? $attrs['ratio'] : '16-9';
    $embed = null;
    $file = null;

    if (preg_match('~^https://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:[^#]*&)?v=|embed/|shorts/)|youtu\.be/)([\w-]{11})~i', $url, $m)) {
        $embed = 'https://www.youtube-nocookie.com/embed/'.$m[1];
    } elseif (preg_match('~^https://(?:www\.)?vimeo\.com/(?:video/)?(\d{6,12})~i', $url, $m)) {
        $embed = 'https://player.vimeo.com/video/'.$m[1].'?dnt=1';
    } elseif (preg_match('~^(?:https?://|/)\S+\.(?:mp4|webm)(?:\?\S*)?$~i', $url)) {
        $file = $url;
    }
@endphp
@if ($embed || $file)
    <figure class="lm-video lm-video--{{ $ratio }}">
        @if ($embed)
            <iframe src="{{ $embed }}" title="{{ $title }}" loading="lazy" allow="fullscreen; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
        @else
            <video src="{{ $file }}" controls preload="metadata" title="{{ $title }}"></video>
        @endif
    </figure>
@elseif (request()->routeIs('cms.*'))
    <p class="lm-widget-empty">{{ __('Wklej adres filmu z YouTube, Vimeo albo plik MP4.') }}</p>
@endif

@if ($trackOn && $hasTracking)
    <script type="application/json" id="lm-config">{!! json_encode(['ga' => $siteSettings['ga_id'], 'gtm' => $siteSettings['gtm_id'], 'pixel' => $siteSettings['pixel_id'], 'consent' => (bool) $siteSettings['consent_required'], 'privacy' => $siteSettings['privacy_url']], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    <script src="{{ asset('js/lumin-consent.js') }}" defer></script>
@endif
@if ($trackOn && $siteSettings['custom_head'] !== '')
    {!! $siteSettings['custom_head'] !!}
@endif

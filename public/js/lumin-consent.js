/*! Lumin: baner zgody na cookies i ładowanie analityki/pikseli dopiero po zgodzie. */
(function () {
    var el = document.getElementById('lm-config');
    if (!el) return;
    var cfg;
    try {
        cfg = JSON.parse(el.textContent);
    } catch (e) {
        return;
    }

    var COOKIE = 'lm_consent';
    function getConsent() {
        var m = document.cookie.match(
            new RegExp('(?:^|; )' + COOKIE + '=([^;]*)'),
        );
        return m ? decodeURIComponent(m[1]) : null;
    }
    function setConsent(value) {
        document.cookie =
            COOKIE +
            '=' +
            value +
            '; max-age=31536000; path=/; SameSite=Lax' +
            (location.protocol === 'https:' ? '; Secure' : '');
    }

    var loaded = false;
    function script(src, onload) {
        var s = document.createElement('script');
        s.async = true;
        s.src = src;
        if (onload) s.onload = onload;
        document.head.appendChild(s);
    }

    function load() {
        if (loaded) return;
        loaded = true;
        window.dataLayer = window.dataLayer || [];

        if (cfg.gtm) {
            window.dataLayer.push({
                'gtm.start': new Date().getTime(),
                event: 'gtm.js',
            });
            script(
                'https://www.googletagmanager.com/gtm.js?id=' +
                    encodeURIComponent(cfg.gtm),
            );
        }
        if (cfg.ga) {
            window.gtag =
                window.gtag ||
                function () {
                    window.dataLayer.push(arguments);
                };
            window.gtag('js', new Date());
            window.gtag('config', cfg.ga);
            script(
                'https://www.googletagmanager.com/gtag/js?id=' +
                    encodeURIComponent(cfg.ga),
            );
        }
        if (cfg.pixel && !window.fbq) {
            var n = (window.fbq = function () {
                n.callMethod
                    ? n.callMethod.apply(n, arguments)
                    : n.queue.push(arguments);
            });
            if (!window._fbq) window._fbq = n;
            n.push = n;
            n.loaded = true;
            n.version = '2.0';
            n.queue = [];
            script('https://connect.facebook.net/en_US/fbevents.js');
            window.fbq('init', cfg.pixel);
            window.fbq('track', 'PageView');
        }
    }

    function banner() {
        var old = document.getElementById('lm-cookie-banner');
        if (old) old.remove();
        var box = document.createElement('div');
        box.id = 'lm-cookie-banner';
        box.setAttribute('role', 'dialog');
        box.setAttribute('aria-label', 'Zgoda na pliki cookies');
        box.style.cssText =
            'position:fixed;left:1rem;right:1rem;bottom:1rem;max-width:36rem;margin:0 auto;padding:1rem 1.25rem;background:#fff;color:#111;border:1px solid #ddd;border-radius:.75rem;box-shadow:0 8px 30px rgba(0,0,0,.18);z-index:2147483000;font:14px/1.5 system-ui,sans-serif';
        var p = document.createElement('p');
        p.style.margin = '0 0 .75rem';
        p.textContent =
            'Używamy plików cookies do analityki i marketingu. Zgoda jest dobrowolna i możesz ją w każdej chwili zmienić.';
        if (cfg.privacy) {
            var a = document.createElement('a');
            a.href = cfg.privacy;
            a.textContent = ' Polityka prywatności';
            a.style.color = 'inherit';
            p.appendChild(a);
        }
        var row = document.createElement('div');
        row.style.cssText = 'display:flex;gap:.5rem;flex-wrap:wrap';
        function btn(label, primary, handler) {
            var b = document.createElement('button');
            b.type = 'button';
            b.textContent = label;
            b.style.cssText =
                'padding:.5rem 1rem;border-radius:.5rem;border:1px solid ' +
                (primary ? '#111' : '#bbb') +
                ';background:' +
                (primary ? '#111' : '#fff') +
                ';color:' +
                (primary ? '#fff' : '#111') +
                ';cursor:pointer;font:inherit';
            b.addEventListener('click', handler);
            return b;
        }
        row.appendChild(
            btn('Akceptuję', true, function () {
                setConsent('granted');
                box.remove();
                load();
            }),
        );
        row.appendChild(
            btn('Odrzucam', false, function () {
                setConsent('denied');
                box.remove();
            }),
        );
        box.appendChild(p);
        box.appendChild(row);
        document.body.appendChild(box);
    }

    function init() {
        if (!cfg.consent) {
            load();
            return;
        }
        var c = getConsent();
        if (c === 'granted') load();
        else if (c !== 'denied') banner();

        document.addEventListener('click', function (e) {
            var t =
                e.target.closest &&
                e.target.closest('[data-lm-cookie-settings]');
            if (t) {
                e.preventDefault();
                banner();
            }
        });
    }

    if (document.readyState === 'loading')
        document.addEventListener('DOMContentLoaded', init);
    else init();
})();

// Menu nawigacji: przyciski rozwijania (aria-expanded), Escape, kliknięcie poza menu i przycisk mobilny.
(function () {
    function closeAll(nav, except) {
        nav.querySelectorAll('.lm-menu__toggle[aria-expanded="true"]').forEach(
            function (b) {
                if (
                    b !== except &&
                    !b
                        .closest('.lm-menu__item')
                        .contains(except || document.body)
                ) {
                    b.setAttribute('aria-expanded', 'false');
                }
            },
        );
    }

    document.querySelectorAll('[data-lm-menu]').forEach(function (nav) {
        var burger = nav.querySelector('.lm-menu__burger');

        burger.addEventListener('click', function () {
            var open = burger.getAttribute('aria-expanded') !== 'true';
            burger.setAttribute('aria-expanded', String(open));
            nav.classList.toggle('is-open', open);
        });

        nav.addEventListener('click', function (e) {
            var btn = e.target.closest('.lm-menu__toggle');
            if (!btn) return;
            var open = btn.getAttribute('aria-expanded') !== 'true';
            closeAll(nav, btn);
            btn.setAttribute('aria-expanded', String(open));
        });

        nav.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            var open = nav.querySelector(
                '.lm-menu__toggle[aria-expanded="true"]',
            );
            if (open) {
                open.setAttribute('aria-expanded', 'false');
                open.focus();
            } else if (burger.getAttribute('aria-expanded') === 'true') {
                burger.click();
                burger.focus();
            }
        });

        document.addEventListener('click', function (e) {
            if (!nav.contains(e.target)) closeAll(nav, null);
        });
    });
})();

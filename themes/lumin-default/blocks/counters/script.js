var counters = document.querySelectorAll('[data-lm-count]');
if (counters.length && 'IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    var run = function (el) {
        var end = parseFloat(el.getAttribute('data-lm-count'));
        var decimals = (String(end).split('.')[1] || '').length;
        var start = null;
        function step(now) {
            start = start || now;
            var p = Math.min((now - start) / 1200, 1);
            el.textContent = (end * (1 - Math.pow(1 - p, 3))).toFixed(decimals);
            if (p < 1) requestAnimationFrame(step);
        }
        el.textContent = (0).toFixed(decimals);
        requestAnimationFrame(step);
    };
    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                observer.unobserve(entry.target);
                run(entry.target);
            }
        });
    }, { threshold: 0.4 });
    counters.forEach(function (el) { observer.observe(el); });
}

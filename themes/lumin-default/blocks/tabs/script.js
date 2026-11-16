document.querySelectorAll('[data-lm-tabs]').forEach(function (root) {
    var tabs = Array.prototype.slice.call(root.querySelectorAll('[role=tab]'));
    var panels = Array.prototype.slice.call(
        root.querySelectorAll('[role=tabpanel]'),
    );

    function show(index, focus) {
        tabs.forEach(function (tab, i) {
            tab.setAttribute('aria-selected', i === index ? 'true' : 'false');
            tab.tabIndex = i === index ? 0 : -1;
            panels[i].hidden = i !== index;
        });
        if (focus) tabs[index].focus();
    }

    tabs.forEach(function (tab, i) {
        tab.addEventListener('click', function () {
            show(i, false);
        });
        tab.addEventListener('keydown', function (e) {
            var next = {
                ArrowRight: i + 1,
                ArrowLeft: i - 1,
                Home: 0,
                End: tabs.length - 1,
            }[e.key];
            if (next === undefined) return;
            e.preventDefault();
            show((next + tabs.length) % tabs.length, true);
        });
    });
});

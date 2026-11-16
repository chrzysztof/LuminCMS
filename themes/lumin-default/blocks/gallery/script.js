var galleries = document.querySelectorAll('[data-lm-gallery]');
if (galleries.length && typeof HTMLDialogElement === 'function') {
    var dialog = document.createElement('dialog');
    dialog.className = 'lm-lightbox';
    dialog.setAttribute('aria-label', 'Galeria');
    dialog.innerHTML =
        '<figure class="lm-lightbox__figure"><img alt=""><figcaption></figcaption></figure>' +
        '<button type="button" class="lm-lightbox__btn lm-lightbox__close" aria-label="Zamknij">&times;</button>' +
        '<button type="button" class="lm-lightbox__btn lm-lightbox__prev" aria-label="Poprzednie">&#8249;</button>' +
        '<button type="button" class="lm-lightbox__btn lm-lightbox__next" aria-label="Następne">&#8250;</button>';
    document.body.appendChild(dialog);
    var img = dialog.querySelector('img');
    var caption = dialog.querySelector('figcaption');
    var links = [];
    var index = 0;

    function show(i) {
        index = (i + links.length) % links.length;
        var a = links[index];
        img.src = a.href;
        img.alt = a.querySelector('img').alt;
        caption.textContent = a.getAttribute('data-caption') || '';
        var single = links.length < 2;
        dialog.querySelector('.lm-lightbox__prev').hidden = single;
        dialog.querySelector('.lm-lightbox__next').hidden = single;
    }

    galleries.forEach(function (gallery) {
        var own = Array.prototype.slice.call(gallery.querySelectorAll('a'));
        own.forEach(function (a, i) {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                links = own;
                show(i);
                dialog.showModal();
            });
        });
    });

    dialog.querySelector('.lm-lightbox__close').addEventListener('click', function () { dialog.close(); });
    dialog.querySelector('.lm-lightbox__prev').addEventListener('click', function () { show(index - 1); });
    dialog.querySelector('.lm-lightbox__next').addEventListener('click', function () { show(index + 1); });
    dialog.addEventListener('click', function (e) { if (e.target === dialog) dialog.close(); });
    dialog.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowLeft') show(index - 1);
        if (e.key === 'ArrowRight') show(index + 1);
    });
}

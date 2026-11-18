/** Porównanie bez wielkości liter i polskich znaków diakrytycznych ("Zakładki" ~ "zaklad"). */
const normalize = (s: string) =>
    s
        .toLowerCase()
        .replace(/ł/g, 'l')
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .trim();

/**
 * Filtruje bloki w przyborniku GrapesJS po nazwie bloku lub kategorii.
 * Działa na DOM listy bloków, więc nie ingeruje w rejestr bloków edytora.
 * Pusty tekst przywraca pierwotny stan (w tym zwinięte kategorie).
 */
export function filterBlocks(container: HTMLElement, query: string): void {
    const q = normalize(query);

    container
        .querySelectorAll<HTMLElement>('.gjs-block-category')
        .forEach((category) => {
            const list = category.querySelector<HTMLElement>('.gjs-blocks-c');
            const title = normalize(
                category.querySelector('.gjs-title')?.textContent ?? '',
            );
            const blocks = [
                ...category.querySelectorAll<HTMLElement>('.gjs-block'),
            ];

            if (q === '') {
                blocks.forEach((b) => (b.hidden = false));
                category.hidden = false;
                if (list && list.dataset.lmSaved !== undefined) {
                    list.style.display = list.dataset.lmSaved;
                    delete list.dataset.lmSaved;
                }

                return;
            }

            const categoryMatches = title.includes(q);
            let visible = 0;

            blocks.forEach((b) => {
                const label = normalize(
                    b.querySelector('.gjs-block-label')?.textContent ??
                        b.getAttribute('title') ??
                        '',
                );
                const show = categoryMatches || label.includes(q);
                b.hidden = !show;
                if (show) visible++;
            });

            category.hidden = visible === 0;

            // Podczas szukania kategorie z wynikami są rozwinięte.
            if (list && visible > 0) {
                if (list.dataset.lmSaved === undefined) {
                    list.dataset.lmSaved = list.style.display;
                }
                list.style.display = 'grid';
            }
        });
}

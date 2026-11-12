import type { Editor } from 'grapesjs';
import { t } from '@/lib/i18n';

const icon = (path: string) =>
    `<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">${path}</svg>`;

export const placeholderImage =
    'data:image/svg+xml;utf8,' +
    encodeURIComponent(
        '<svg xmlns="http://www.w3.org/2000/svg" width="800" height="500" viewBox="0 0 800 500"><rect width="800" height="500" fill="#ececef"/><g fill="none" stroke="#9a9aa5" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"><rect x="300" y="170" width="200" height="160" rx="14"/><circle cx="360" cy="225" r="16"/><path d="M310 320l60-60 40 40 30-30 60 60"/></g></svg>',
    );

const img = `<img class="lm-image" src="${placeholderImage}" alt="">`;

type BlockDef = {
    id: string;
    label: string;
    category: string;
    icon: string;
    content: string;
};

const blocks: BlockDef[] = [
    {
        id: 'hero',
        label: t('Hero'),
        category: t('Sekcje'),
        icon: icon(
            '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 10h10M7 14h6"/>',
        ),
        content: `<section class="lm-section lm-hero"><div class="lm-container"><h1>Nagłówek, który przyciąga uwagę</h1><p>Krótki opis oferty. Powiedz odwiedzającym, co zyskają.</p><a class="lm-btn" href="#">Zacznij teraz</a></div></section>`,
    },
    {
        id: 'section',
        label: t('Sekcja'),
        category: t('Sekcje'),
        icon: icon('<rect x="3" y="5" width="18" height="14" rx="2"/>'),
        content: `<section class="lm-section"><div class="lm-container"><h2>Nagłówek sekcji</h2><p>Treść sekcji. Kliknij dwukrotnie, aby edytować.</p></div></section>`,
    },
    {
        id: 'columns-2',
        label: t('2 kolumny'),
        category: t('Sekcje'),
        icon: icon(
            '<rect x="3" y="5" width="8" height="14" rx="1.5"/><rect x="13" y="5" width="8" height="14" rx="1.5"/>',
        ),
        content: `<section class="lm-section"><div class="lm-container"><div class="lm-grid lm-cols-2"><div><h3>Pierwsza kolumna</h3><p>Opis pierwszej kolumny.</p></div><div><h3>Druga kolumna</h3><p>Opis drugiej kolumny.</p></div></div></div></section>`,
    },
    {
        id: 'cards-3',
        label: t('3 karty'),
        category: t('Sekcje'),
        icon: icon(
            '<rect x="2.5" y="6" width="5.5" height="12" rx="1.2"/><rect x="9.25" y="6" width="5.5" height="12" rx="1.2"/><rect x="16" y="6" width="5.5" height="12" rx="1.2"/>',
        ),
        content: `<section class="lm-section lm-bg-muted"><div class="lm-container"><h2 class="lm-center">Dlaczego my</h2><div class="lm-grid lm-cols-3"><div class="lm-card"><h3>Szybko</h3><p>Krótki opis zalety.</p></div><div class="lm-card"><h3>Prosto</h3><p>Krótki opis zalety.</p></div><div class="lm-card"><h3>Skutecznie</h3><p>Krótki opis zalety.</p></div></div></div></section>`,
    },
    {
        id: 'text-image',
        label: t('Tekst + obraz'),
        category: t('Sekcje'),
        icon: icon(
            '<rect x="3" y="5" width="8" height="14" rx="1.5"/><path d="M14 8h7M14 12h7M14 16h4"/>',
        ),
        content: `<section class="lm-section"><div class="lm-container"><div class="lm-grid lm-cols-2 lm-items-center"><div><h2>Opowiedz swoją historię</h2><p>Opis obok zdjęcia. Wybierz obraz z biblioteki mediów.</p><a class="lm-btn" href="#">Dowiedz się więcej</a></div>${img}</div></div></section>`,
    },
    {
        id: 'heading',
        label: t('Nagłówek'),
        category: t('Treść'),
        icon: icon('<path d="M6 5v14M18 5v14M6 12h12"/>'),
        content: `<h2>Nowy nagłówek</h2>`,
    },
    {
        id: 'paragraph',
        label: t('Akapit'),
        category: t('Treść'),
        icon: icon('<path d="M4 6h16M4 11h16M4 16h10"/>'),
        content: `<p>Nowy akapit tekstu. Kliknij dwukrotnie, aby edytować.</p>`,
    },
    {
        id: 'image',
        label: t('Obraz'),
        category: t('Treść'),
        icon: icon(
            '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="1.6"/><path d="M4 18l5-5 4 4 3-3 4 4"/>',
        ),
        content: img,
    },
    {
        id: 'button',
        label: t('Przycisk'),
        category: t('Treść'),
        icon: icon('<rect x="3" y="8" width="18" height="8" rx="4"/>'),
        content: `<a class="lm-btn" href="#">Przycisk</a>`,
    },
    {
        id: 'list',
        label: t('Lista'),
        category: t('Treść'),
        icon: icon(
            '<path d="M9 6h11M9 12h11M9 18h11M4.5 6h.01M4.5 12h.01M4.5 18h.01"/>',
        ),
        content: `<ul class="lm-list"><li>Pierwszy punkt</li><li>Drugi punkt</li><li>Trzeci punkt</li></ul>`,
    },
    {
        id: 'quote',
        label: t('Cytat'),
        category: t('Treść'),
        icon: icon(
            '<path d="M7 7h4v5H7zM13 7h4v5h-4zM7 12c0 3-1 4-2 5M13 12c0 3-1 4-2 5"/>',
        ),
        content: `<blockquote class="lm-quote">Treść cytatu lub opinii klienta.</blockquote>`,
    },
    {
        id: 'menu',
        label: t('Menu'),
        category: t('Układ'),
        icon: icon('<path d="M4 7h16M4 12h16M4 17h10"/>'),
        // Shortcode zamieni się w menu na stronie; location wskazuje lokalizację motywu, name menu po nazwie (slugu).
        content: `<div class="lm-menu-slot">[menu location="primary"]</div>`,
    },
    {
        id: 'divider',
        label: t('Separator'),
        category: t('Układ'),
        icon: icon('<path d="M3 12h18"/>'),
        content: `<hr class="lm-divider">`,
    },
    {
        id: 'spacer',
        label: t('Odstęp'),
        category: t('Układ'),
        icon: icon('<path d="M12 4v16M8 8l4-4 4 4M8 16l4 4 4-4"/>'),
        content: `<div class="lm-spacer"></div>`,
    },
];

export function registerBlocks(editor: Editor) {
    blocks.forEach((b) =>
        editor.Blocks.add(b.id, {
            label: b.label,
            category: b.category,
            media: b.icon,
            content: b.content,
            select: true,
        }),
    );
}

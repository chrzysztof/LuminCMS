import { placeholderImage } from '@/lib/builder/placeholder';

/**
 * Biblioteka gotowych sekcji i stron. Każda sekcja to zwykły, semantyczny HTML z klasami `lm-*`
 * (bez stylów inline), który edytor wczytuje jak własne bloki; strony składają się z tych sekcji.
 */

const img = `<img class="lm-image lm-rounded" src="${placeholderImage}" alt="">`;

export type SectionDef = {
    id: string;
    label: string;
    /** Ścieżki SVG (viewBox 24) schematu sekcji. */
    icon: string;
    html: string;
};

const card = (title: string, text: string) =>
    `<div class="lm-card"><h3>${title}</h3><p>${text}</p></div>`;

export const SECTIONS: SectionDef[] = [
    {
        id: 'hero-center',
        label: 'Hero wyśrodkowany',
        icon: '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M8 9h8M9 13h6"/><rect x="9" y="16" width="6" height="2" rx="1"/>',
        html: `<section class="lm-section lm-hero lm-bg-dark lm-center"><div class="lm-container"><h1 class="lm-center">Nagłówek, który przyciąga uwagę</h1><p class="lm-center">Krótki opis oferty. Powiedz odwiedzającym, co zyskają.</p><div class="lm-actions"><a class="lm-btn" href="#">Zacznij teraz</a><a class="lm-btn lm-btn-secondary" href="#">Dowiedz się więcej</a></div></div></section>`,
    },
    {
        id: 'hero-split',
        label: 'Hero z obrazem',
        icon: '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M6 9h6M6 13h4"/><rect x="14" y="8" width="5" height="8" rx="1"/>',
        html: `<section class="lm-section lm-hero"><div class="lm-container"><div class="lm-grid lm-cols-2 lm-items-center"><div><h1>Nagłówek, który przyciąga uwagę</h1><p>Krótki opis oferty. Powiedz odwiedzającym, co zyskają.</p><div class="lm-actions"><a class="lm-btn" href="#">Zacznij teraz</a><a class="lm-btn lm-btn-secondary" href="#">Cennik</a></div></div>${img}</div></div></section>`,
    },
    {
        id: 'features-6',
        label: 'Funkcje (6 kart)',
        icon: '<rect x="3" y="4" width="5" height="7" rx="1"/><rect x="9.5" y="4" width="5" height="7" rx="1"/><rect x="16" y="4" width="5" height="7" rx="1"/><rect x="3" y="13" width="5" height="7" rx="1"/><rect x="9.5" y="13" width="5" height="7" rx="1"/><rect x="16" y="13" width="5" height="7" rx="1"/>',
        html: `<section class="lm-section"><div class="lm-container"><h2 class="lm-center">Wszystko, czego potrzebujesz</h2><p class="lm-center">Krótko opisz, w czym jesteś najlepszy.</p><div class="lm-grid lm-cols-3">${card('Szybkość', 'Krótki opis pierwszej zalety.')}${card('Prostota', 'Krótki opis drugiej zalety.')}${card('Bezpieczeństwo', 'Krótki opis trzeciej zalety.')}${card('Elastyczność', 'Krótki opis czwartej zalety.')}${card('Wsparcie', 'Krótki opis piątej zalety.')}${card('Cena', 'Krótki opis szóstej zalety.')}</div></div></section>`,
    },
    {
        id: 'steps',
        label: 'Jak to działa (kroki)',
        icon: '<circle cx="6" cy="8" r="2.5"/><circle cx="12" cy="8" r="2.5"/><circle cx="18" cy="8" r="2.5"/><path d="M4 16h4M10 16h4M16 16h4"/>',
        html: `<section class="lm-section lm-bg-muted"><div class="lm-container"><h2 class="lm-center">Jak to działa</h2><div class="lm-grid lm-cols-3"><div><span class="lm-step">1</span><h3>Pierwszy krok</h3><p>Opisz, co dzieje się na początku.</p></div><div><span class="lm-step">2</span><h3>Drugi krok</h3><p>Opisz, co dzieje się potem.</p></div><div><span class="lm-step">3</span><h3>Trzeci krok</h3><p>Opisz efekt końcowy.</p></div></div></div></section>`,
    },
    {
        id: 'stats',
        label: 'Liczby',
        icon: '<path d="M5 18V12M10 18V8M15 18V10M20 18V5"/>',
        html: `<section class="lm-section lm-pad-sm"><div class="lm-container"><div class="lm-grid lm-cols-4 lm-center"><div class="lm-stat"><strong>10 000+</strong><span>Zadowolonych klientów</span></div><div class="lm-stat"><strong>15 lat</strong><span>Doświadczenia</span></div><div class="lm-stat"><strong>250</strong><span>Zrealizowanych projektów</span></div><div class="lm-stat"><strong>98%</strong><span>Poleceń</span></div></div></div></section>`,
    },
    {
        id: 'pricing',
        label: 'Cennik (3 plany)',
        icon: '<rect x="3" y="7" width="5.5" height="11" rx="1.2"/><rect x="9.25" y="4" width="5.5" height="14" rx="1.2"/><rect x="15.5" y="7" width="5.5" height="11" rx="1.2"/>',
        html: `<section class="lm-section"><div class="lm-container"><h2 class="lm-center">Prosty cennik</h2><p class="lm-center">Wybierz plan dopasowany do swoich potrzeb.</p><div class="lm-grid lm-cols-3 lm-items-center"><div class="lm-card lm-price"><h3>Start</h3><p class="lm-price__amount">49 zł<small> / mies.</small></p><ul class="lm-list"><li>Pierwsza funkcja</li><li>Druga funkcja</li><li>Trzecia funkcja</li></ul><a class="lm-btn lm-btn-secondary" href="#">Wybierz plan</a></div><div class="lm-card lm-price lm-featured lm-shadow"><h3>Pro</h3><p class="lm-price__amount">99 zł<small> / mies.</small></p><ul class="lm-list"><li>Wszystko z planu Start</li><li>Czwarta funkcja</li><li>Piąta funkcja</li></ul><a class="lm-btn" href="#">Wybierz plan</a></div><div class="lm-card lm-price"><h3>Firma</h3><p class="lm-price__amount">249 zł<small> / mies.</small></p><ul class="lm-list"><li>Wszystko z planu Pro</li><li>Szósta funkcja</li><li>Priorytetowe wsparcie</li></ul><a class="lm-btn lm-btn-secondary" href="#">Wybierz plan</a></div></div></div></section>`,
    },
    {
        id: 'testimonials',
        label: 'Opinie klientów',
        icon: '<path d="M4 6h16v9H9l-4 3v-3H4z"/><path d="M8 10h8"/>',
        html: `<section class="lm-section lm-bg-muted"><div class="lm-container"><h2 class="lm-center">Co mówią klienci</h2><div class="lm-grid lm-cols-3"><div class="lm-card"><blockquote class="lm-quote">Świetna współpraca, efekty widać od pierwszego tygodnia.</blockquote><p class="lm-author"><strong>Anna Kowalska</strong><br>Dyrektor, Firma Sp. z o.o.</p></div><div class="lm-card"><blockquote class="lm-quote">Profesjonalne podejście i szybka realizacja. Polecam.</blockquote><p class="lm-author"><strong>Piotr Nowak</strong><br>Właściciel, Sklep Online</p></div><div class="lm-card"><blockquote class="lm-quote">Wreszcie ktoś, kto rozumie, czego potrzebujemy.</blockquote><p class="lm-author"><strong>Maria Wiśniewska</strong><br>Marketing, Studio X</p></div></div></div></section>`,
    },
    {
        id: 'faq',
        label: 'FAQ (pytania)',
        icon: '<rect x="3" y="5" width="18" height="4" rx="1"/><rect x="3" y="11" width="18" height="4" rx="1"/><path d="M3 18h12"/>',
        html: `<section class="lm-section"><div class="lm-container lm-narrow"><h2 class="lm-center">Najczęstsze pytania</h2><div class="lm-faq"><details><summary>Pierwsze pytanie?</summary><p>Odpowiedź na pierwsze pytanie.</p></details><details><summary>Drugie pytanie?</summary><p>Odpowiedź na drugie pytanie.</p></details><details><summary>Trzecie pytanie?</summary><p>Odpowiedź na trzecie pytanie.</p></details></div></div></section>`,
    },
    {
        id: 'cta',
        label: 'Wezwanie do działania',
        icon: '<rect x="3" y="6" width="18" height="12" rx="2"/><rect x="8" y="12" width="8" height="3" rx="1.5"/><path d="M8 9h8"/>',
        html: `<section class="lm-section lm-bg-dark lm-center"><div class="lm-container"><h2 class="lm-center">Gotowy, żeby zacząć?</h2><p class="lm-center">Napisz do nas albo umów bezpłatną konsultację.</p><a class="lm-btn" href="#">Skontaktuj się</a></div></section>`,
    },
    {
        id: 'contact',
        label: 'Kontakt',
        icon: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 8l9 6 9-6"/>',
        html: `<section class="lm-section lm-bg-muted"><div class="lm-container"><div class="lm-grid lm-cols-2"><div><h2>Porozmawiajmy</h2><p>Odpowiadamy w ciągu jednego dnia roboczego.</p></div><div class="lm-card"><ul class="lm-list"><li><strong>E-mail:</strong> kontakt@twojadomena.pl</li><li><strong>Telefon:</strong> +48 000 000 000</li><li><strong>Adres:</strong> ul. Przykładowa 1, 00-000 Miasto</li></ul></div></div></div></section>`,
    },
];

export type PageTemplate = {
    id: string;
    label: string;
    description: string;
    sections: string[];
};

export const PAGE_TEMPLATES: PageTemplate[] = [
    {
        id: 'landing',
        label: 'Strona docelowa',
        description: 'Hero, funkcje, kroki, opinie i wezwanie do działania.',
        sections: ['hero-center', 'features-6', 'steps', 'testimonials', 'cta'],
    },
    {
        id: 'company',
        label: 'O firmie',
        description: 'Hero z obrazem, liczby, funkcje, opinie i kontakt.',
        sections: [
            'hero-split',
            'stats',
            'features-6',
            'testimonials',
            'contact',
        ],
    },
    {
        id: 'pricing',
        label: 'Cennik',
        description: 'Hero, plany cenowe, pytania i wezwanie do działania.',
        sections: ['hero-center', 'pricing', 'faq', 'cta'],
    },
    {
        id: 'contact',
        label: 'Kontakt',
        description: 'Krótki hero, dane kontaktowe i pytania.',
        sections: ['hero-center', 'contact', 'faq'],
    },
];

export const sectionHtml = (id: string): string =>
    SECTIONS.find((s) => s.id === id)?.html ?? '';

export const pageHtml = (tpl: PageTemplate): string =>
    tpl.sections.map(sectionHtml).join('');

export type SeoSite = {
    site_name: string;
    tagline: string;
    title_template: string;
    separator: string;
    base_url: string;
    url_prefix: string | null;
    body: string;
    noindex_site: boolean;
};

export const TITLE_MAX = 60;
export const DESCRIPTION_MAX = 160;

/** Lustro SeoResolver::applyTemplate (serwer jest źródłem prawdy, to tylko podgląd). */
export function applyTemplate(
    template: string,
    title: string,
    site: Pick<SeoSite, 'site_name' | 'tagline' | 'separator'>,
): string {
    return template
        .replaceAll('%title%', title)
        .replaceAll('%site%', site.site_name)
        .replaceAll('%tagline%', site.tagline)
        .replaceAll('%sep%', site.separator)
        .replace(/\s+/g, ' ')
        .trim();
}

export function limitWords(text: string, max: number): string {
    const clean = text.replace(/\s+/g, ' ').trim();
    if (clean.length <= max) return clean;

    const cut = clean.slice(0, max);

    return `${cut.slice(0, Math.max(cut.lastIndexOf(' '), 1))}…`;
}

export type Check = { ok: boolean; text: string };

export function analyse(input: {
    keyword: string;
    title: string;
    description: string;
    slug: string;
    body: string;
}): Check[] {
    const { keyword, title, description, slug, body } = input;
    const k = keyword.trim().toLowerCase();
    const has = (text: string) => k !== '' && text.toLowerCase().includes(k);
    const slugify = (t: string) =>
        t
            .toLowerCase()
            .normalize('NFD')
            .replace(/[̀-ͯ]/g, '')
            .replace(/ł/g, 'l')
            .replace(/[^a-z0-9]+/g, '-');

    const checks: Check[] = [
        {
            ok: title.length >= 30 && title.length <= TITLE_MAX,
            text: `Tytuł SEO ma 30–${TITLE_MAX} znaków (jest ${title.length}).`,
        },
        {
            ok:
                description.length >= 70 &&
                description.length <= DESCRIPTION_MAX,
            text: `Opis ma 70–${DESCRIPTION_MAX} znaków (jest ${description.length}).`,
        },
    ];

    if (k) {
        checks.push(
            { ok: has(title), text: 'Fraza kluczowa jest w tytule SEO.' },
            { ok: has(description), text: 'Fraza kluczowa jest w opisie.' },
            {
                ok: slugify(slug).includes(slugify(k).replace(/^-|-$/g, '')),
                text: 'Fraza kluczowa jest w adresie (slug).',
            },
            {
                ok: has(body.slice(0, 400)),
                text: 'Fraza kluczowa pojawia się na początku treści.',
            },
        );
    } else {
        checks.push({
            ok: false,
            text: 'Ustaw frazę kluczową, aby sprawdzić jej użycie.',
        });
    }

    return checks;
}

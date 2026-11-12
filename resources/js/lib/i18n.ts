/**
 * Tłumaczenia panelu. Tekst źródłowy (po polsku w panelu CMS, po angielsku na ekranach konta)
 * jest kluczem; słownik bieżącego języka wstawia serwer (window.__luminI18n) z lang/{język}.json.
 * Brak wpisu = pokazujemy tekst źródłowy. Parametry: t('Wiersz {n}', { n: 3 }).
 */
declare global {
    interface Window {
        __luminI18n?: Record<string, string>;
    }
}

export type Params = Record<string, string | number>;

export function t(key: string, params?: Params): string {
    const text =
        (typeof window !== 'undefined' && window.__luminI18n?.[key]) || key;

    return params
        ? text.replace(/\{(\w+)\}/g, (m, name: string) =>
              name in params ? String(params[name]) : m,
          )
        : text;
}

export function currentLocale(): string {
    return typeof document !== 'undefined'
        ? document.documentElement.lang || 'pl'
        : 'pl';
}

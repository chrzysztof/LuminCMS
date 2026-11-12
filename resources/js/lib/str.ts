/** Bezpieczna konwersja wartości pola na tekst: obiekty i tablice dają wartość zastępczą zamiast „[object Object]". */
export const str = (value: unknown, fallback = ''): string =>
    typeof value === 'string'
        ? value
        : typeof value === 'number' ||
            typeof value === 'boolean' ||
            typeof value === 'bigint'
          ? String(value)
          : fallback;

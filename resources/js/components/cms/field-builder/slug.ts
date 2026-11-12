const map: Record<string, string> = {
    ą: 'a',
    ć: 'c',
    ę: 'e',
    ł: 'l',
    ń: 'n',
    ó: 'o',
    ś: 's',
    ź: 'z',
    ż: 'z',
};

/** Nazwa pola z etykiety: małe litery, cyfry i podkreślenia, zaczyna się od litery. */
export function fieldNameFromLabel(label: string): string {
    const name = label
        .toLowerCase()
        .replace(/[ąćęłńóśźż]/g, (c) => map[c])
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '');

    return /^[a-z]/.test(name) ? name : name ? `pole_${name}` : '';
}

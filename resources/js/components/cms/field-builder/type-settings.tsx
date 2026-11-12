import {
    Row,
    SelectSetting,
    TextSetting,
    Toggle,
    nativeClass,
} from '@/components/cms/field-builder/setting-inputs';
import type { FieldDraft } from '@/types/field-builder';
import { t } from '@/lib/i18n';

/** Polskie etykiety ustawień typów. Nieznane klucze pokazujemy pod ich nazwą. */
const labels: Record<string, string> = {
    default_value: t('Wartość domyślna'),
    placeholder: t('Podpowiedź (placeholder)'),
    prepend: t('Tekst przed polem'),
    append: t('Tekst po polu'),
    maxlength: t('Maksymalna długość'),
    rows: t('Liczba wierszy'),
    new_lines: t('Nowe linie'),
    min: t('Wartość minimalna'),
    max: t('Wartość maksymalna'),
    step: t('Krok'),
    choices: t('Opcje'),
    allow_null: t('Zezwól na brak wyboru'),
    multiple: t('Wybór wielu opcji'),
    ui: t('Stylizowany interfejs'),
    return_format: t('Format zwracany w szablonie'),
    allow_custom: t('Pozwól na własne wartości'),
    save_custom: t('Dodawaj własne wartości do opcji'),
    layout: t('Układ'),
    toggle: t('Przycisk „zaznacz wszystko”'),
    other_choice: t('Dodaj opcję „Inne”'),
    save_other_choice: t('Zapisuj wartość z „Inne” jako opcję'),
    message: t('Treść'),
    ui_on_text: t('Tekst dla „tak”'),
    ui_off_text: t('Tekst dla „nie”'),
    enable_opacity: t('Pozwól na przezroczystość'),
    display_format: t('Format wyświetlania w panelu'),
    first_day: t('Pierwszy dzień tygodnia'),
    esc_html: t('Pokaż jako zwykły tekst (bez HTML)'),
    open: t('Domyślnie otwarty'),
    multi_expand: t('Wiele otwartych naraz'),
    placement: t('Położenie'),
    endpoint: t('Zakończ poprzednią grupę'),
    size: t('Rozmiar obrazu'),
    mime_types: t('Dozwolone rozszerzenia'),
    max_size: t('Maksymalny rozmiar pliku (MB)'),
    min_width: t('Minimalna szerokość (px)'),
    min_height: t('Minimalna wysokość (px)'),
    max_width: t('Maksymalna szerokość (px)'),
    max_height: t('Maksymalna wysokość (px)'),
    preview_size: t('Rozmiar podglądu'),
    insert: t('Nowe pliki dodawaj'),
    toolbar: t('Pasek narzędzi'),
    post_type: t('Dozwolone typy treści'),
    post_status: t('Dozwolone statusy'),
    field_type: t('Sposób wyboru'),
    taxonomy: t('Taksonomia (klucz)'),
    button_label: t('Etykieta przycisku dodawania'),
    collapsed: t('Pole tytułu wiersza (nazwa)'),
    center_lat: t('Domyślna szerokość geograficzna'),
    center_lng: t('Domyślna długość geograficzna'),
    zoom: t('Domyślne powiększenie (1–20)'),
    height: t('Wysokość mapy (px)'),
};

/** Dla tych typów "min"/"max" oznaczają liczbę pozycji, a nie wartość liczbową. */
const countTypes = new Set(['repeater', 'relationship', 'gallery']);

const hidden = new Set(['ajax', 'sub_fields']);

const returnFormats: Record<string, [string, string][]> = {
    select: [
        ['value', t('Wartość')],
        ['label', t('Etykieta')],
        ['array', t('Obie (wartość i etykieta)')],
    ],
    checkbox: [
        ['value', t('Wartość')],
        ['label', t('Etykieta')],
        ['array', t('Obie (wartość i etykieta)')],
    ],
    radio: [
        ['value', t('Wartość')],
        ['label', t('Etykieta')],
        ['array', t('Obie (wartość i etykieta)')],
    ],
    button_group: [
        ['value', t('Wartość')],
        ['label', t('Etykieta')],
        ['array', t('Obie (wartość i etykieta)')],
    ],
    color_picker: [
        ['string', t('Tekst (np. #ff8000)')],
        ['array', t('Tablica (red, green, blue, alpha)')],
    ],
    image: [
        ['array', t('Tablica z danymi obrazu')],
        ['url', t('Adres URL')],
        ['id', t('Identyfikator')],
    ],
    file: [
        ['array', t('Tablica z danymi pliku')],
        ['url', t('Adres URL')],
        ['id', t('Identyfikator')],
    ],
    gallery: [
        ['array', t('Tablica z danymi obrazów')],
        ['url', t('Adresy URL')],
        ['id', t('Identyfikatory')],
    ],
    oembed: [
        ['html', t('Kod HTML (iframe)')],
        ['url', t('Adres URL')],
    ],
    post_object: [
        ['object', t('Obiekt treści')],
        ['id', t('Identyfikator')],
    ],
    relationship: [
        ['object', t('Obiekty treści')],
        ['id', t('Identyfikatory')],
    ],
    taxonomy: [
        ['object', t('Obiekty terminów')],
        ['id', t('Identyfikatory')],
    ],
    user: [
        ['object', t('Dane użytkownika (id, imię)')],
        ['id', t('Identyfikator')],
    ],
    map: [
        ['array', t('Tablica (lat, lng, zoom, adres, linki)')],
        ['embed', t('Gotowy iframe OpenStreetMap')],
    ],
};

const repeaterLayouts: [string, string][] = [
    ['block', t('Bloki (zwijane)')],
    ['row', t('Wiersze')],
    ['table', t('Tabela')],
];

const enums: Record<string, [string, string][]> = {
    new_lines: [
        ['wpautop', t('Akapity automatycznie')],
        ['br', t('Znaczniki <br>')],
        ['', t('Bez formatowania')],
    ],
    layout: [
        ['vertical', t('Pionowy')],
        ['horizontal', t('Poziomy')],
    ],
    placement: [
        ['top', t('Na górze')],
        ['left', t('Z lewej')],
    ],
    insert: [
        ['append', t('Na końcu')],
        ['prepend', t('Na początku')],
    ],
    toolbar: [
        ['full', t('Pełny')],
        ['basic', t('Podstawowy')],
    ],
    field_type: [
        ['multi_select', t('Wiele terminów')],
        ['select', t('Jeden termin')],
    ],
    first_day: [
        ['1', t('Poniedziałek')],
        ['0', t('Niedziela')],
    ],
};

/** Opcje wyboru jako tekst: jedna linia = "wartość : etykieta" (albo samo "wartość"). */
export function choicesToText(choices: unknown): string {
    if (Array.isArray(choices)) return choices.map(String).join('\n');
    if (choices && typeof choices === 'object') {
        return Object.entries(choices as Record<string, string>)
            .map(([value, label]) =>
                value === label ? value : `${value} : ${label}`,
            )
            .join('\n');
    }

    return '';
}

export function textToChoices(text: string): Record<string, string> | [] {
    const result: Record<string, string> = {};

    text.split('\n')
        .map((l) => l.trim())
        .filter(Boolean)
        .forEach((line) => {
            const [value, ...rest] = line.split(':');
            const key = value.trim();
            if (key)
                result[key] = rest.length ? rest.join(':').trim() || key : key;
        });

    return Object.keys(result).length ? result : [];
}

type Props = {
    field: FieldDraft;
    defaults: Record<string, unknown>;
    onChange: (key: string, value: unknown) => void;
};

export function TypeSettings({ field, defaults, onChange }: Props) {
    const keys = Object.keys(defaults).filter((k) => !hidden.has(k));

    if (keys.length === 0) return null;

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            {keys.map((key) => {
                const label =
                    key === 'min' && countTypes.has(field.type)
                        ? t('Minimalna liczba pozycji')
                        : key === 'max' && countTypes.has(field.type)
                          ? t('Maksymalna liczba pozycji')
                          : (labels[key] ?? key);
                const def = defaults[key];
                const value = key in field ? field[key] : def;

                if (key === 'choices') {
                    return (
                        <Row
                            key={key}
                            label={label}
                            hint={t('Jedna opcja w linii: wartość : etykieta')}
                            className="sm:col-span-2"
                        >
                            <textarea
                                rows={5}
                                className={`${nativeClass} h-auto py-2 font-mono`}
                                defaultValue={choicesToText(value)}
                                placeholder={'tak : Tak\nnie : Nie'}
                                onBlur={(e) =>
                                    onChange(key, textToChoices(e.target.value))
                                }
                            />
                        </Row>
                    );
                }

                if (key === 'message') {
                    return (
                        <Row key={key} label={label} className="sm:col-span-2">
                            <textarea
                                rows={3}
                                className={`${nativeClass} h-auto py-2`}
                                value={String(value ?? '')}
                                onChange={(e) => onChange(key, e.target.value)}
                            />
                        </Row>
                    );
                }

                if (key === 'return_format' && returnFormats[field.type]) {
                    return (
                        <SelectSetting
                            key={key}
                            label={label}
                            value={value}
                            options={returnFormats[field.type]}
                            onChange={(v) => onChange(key, v)}
                        />
                    );
                }

                if (key === 'layout' && field.type === 'repeater') {
                    return (
                        <SelectSetting
                            key={key}
                            label={label}
                            value={value}
                            options={repeaterLayouts}
                            onChange={(v) => onChange(key, v)}
                        />
                    );
                }

                if (enums[key]) {
                    const asNumber = key === 'first_day';

                    return (
                        <SelectSetting
                            key={key}
                            label={label}
                            value={value}
                            options={enums[key]}
                            onChange={(v) =>
                                onChange(key, asNumber ? Number(v) : v)
                            }
                        />
                    );
                }

                if (typeof def === 'boolean') {
                    return (
                        <Toggle
                            key={key}
                            label={label}
                            checked={Boolean(value)}
                            onChange={(v) => onChange(key, v)}
                        />
                    );
                }

                if (Array.isArray(def) && key !== 'choices') {
                    return (
                        <Row
                            key={key}
                            label={label}
                            hint={t('Jedna wartość w linii')}
                        >
                            <textarea
                                rows={3}
                                className={`${nativeClass} h-auto py-2`}
                                defaultValue={(Array.isArray(value)
                                    ? value
                                    : []
                                ).join('\n')}
                                onBlur={(e) =>
                                    onChange(
                                        key,
                                        e.target.value
                                            .split('\n')
                                            .map((l) => l.trim())
                                            .filter(Boolean),
                                    )
                                }
                            />
                        </Row>
                    );
                }

                const numeric =
                    typeof def === 'number' ||
                    [
                        'min',
                        'max',
                        'step',
                        'maxlength',
                        'rows',
                        'max_size',
                        'min_width',
                        'min_height',
                        'max_width',
                        'max_height',
                    ].includes(key);

                return (
                    <TextSetting
                        key={key}
                        label={label}
                        value={value}
                        type={numeric ? 'number' : 'text'}
                        onChange={(v) =>
                            onChange(key, numeric && v !== '' ? Number(v) : v)
                        }
                    />
                );
            })}
        </div>
    );
}

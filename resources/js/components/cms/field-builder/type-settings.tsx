import {
    Row,
    SelectSetting,
    TextSetting,
    Toggle,
    nativeClass,
} from '@/components/cms/field-builder/setting-inputs';
import type { FieldDraft } from '@/types/field-builder';

/** Polskie etykiety ustawień typów. Nieznane klucze pokazujemy pod ich nazwą. */
const labels: Record<string, string> = {
    default_value: 'Wartość domyślna',
    placeholder: 'Podpowiedź (placeholder)',
    prepend: 'Tekst przed polem',
    append: 'Tekst po polu',
    maxlength: 'Maksymalna długość',
    rows: 'Liczba wierszy',
    new_lines: 'Nowe linie',
    min: 'Wartość minimalna',
    max: 'Wartość maksymalna',
    step: 'Krok',
    choices: 'Opcje',
    allow_null: 'Zezwól na brak wyboru',
    multiple: 'Wybór wielu opcji',
    ui: 'Stylizowany interfejs',
    return_format: 'Format zwracany w szablonie',
    allow_custom: 'Pozwól na własne wartości',
    save_custom: 'Dodawaj własne wartości do opcji',
    layout: 'Układ',
    toggle: 'Przycisk „zaznacz wszystko”',
    other_choice: 'Dodaj opcję „Inne”',
    save_other_choice: 'Zapisuj wartość z „Inne” jako opcję',
    message: 'Treść',
    ui_on_text: 'Tekst dla „tak”',
    ui_off_text: 'Tekst dla „nie”',
    enable_opacity: 'Pozwól na przezroczystość',
    display_format: 'Format wyświetlania w panelu',
    first_day: 'Pierwszy dzień tygodnia',
    esc_html: 'Pokaż jako zwykły tekst (bez HTML)',
    open: 'Domyślnie otwarty',
    multi_expand: 'Wiele otwartych naraz',
    placement: 'Położenie',
    endpoint: 'Zakończ poprzednią grupę',
    size: 'Rozmiar obrazu',
    mime_types: 'Dozwolone rozszerzenia',
    max_size: 'Maksymalny rozmiar pliku (MB)',
    min_width: 'Minimalna szerokość (px)',
    min_height: 'Minimalna wysokość (px)',
    max_width: 'Maksymalna szerokość (px)',
    max_height: 'Maksymalna wysokość (px)',
    preview_size: 'Rozmiar podglądu',
    insert: 'Nowe pliki dodawaj',
};

const hidden = new Set(['ajax']);

const returnFormats: Record<string, [string, string][]> = {
    select: [
        ['value', 'Wartość'],
        ['label', 'Etykieta'],
        ['array', 'Obie (wartość i etykieta)'],
    ],
    checkbox: [
        ['value', 'Wartość'],
        ['label', 'Etykieta'],
        ['array', 'Obie (wartość i etykieta)'],
    ],
    radio: [
        ['value', 'Wartość'],
        ['label', 'Etykieta'],
        ['array', 'Obie (wartość i etykieta)'],
    ],
    button_group: [
        ['value', 'Wartość'],
        ['label', 'Etykieta'],
        ['array', 'Obie (wartość i etykieta)'],
    ],
    color_picker: [
        ['string', 'Tekst (np. #ff8000)'],
        ['array', 'Tablica (red, green, blue, alpha)'],
    ],
    image: [
        ['array', 'Tablica z danymi obrazu'],
        ['url', 'Adres URL'],
        ['id', 'Identyfikator'],
    ],
    file: [
        ['array', 'Tablica z danymi pliku'],
        ['url', 'Adres URL'],
        ['id', 'Identyfikator'],
    ],
    gallery: [
        ['array', 'Tablica z danymi obrazów'],
        ['url', 'Adresy URL'],
        ['id', 'Identyfikatory'],
    ],
};

const enums: Record<string, [string, string][]> = {
    new_lines: [
        ['wpautop', 'Akapity automatycznie'],
        ['br', 'Znaczniki <br>'],
        ['', 'Bez formatowania'],
    ],
    layout: [
        ['vertical', 'Pionowy'],
        ['horizontal', 'Poziomy'],
    ],
    placement: [
        ['top', 'Na górze'],
        ['left', 'Z lewej'],
    ],
    insert: [
        ['append', 'Na końcu'],
        ['prepend', 'Na początku'],
    ],
    first_day: [
        ['1', 'Poniedziałek'],
        ['0', 'Niedziela'],
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
                const label = labels[key] ?? key;
                const def = defaults[key];
                const value = key in field ? field[key] : def;

                if (key === 'choices') {
                    return (
                        <Row
                            key={key}
                            label={label}
                            hint="Jedna opcja w linii: wartość : etykieta"
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

                if (Array.isArray(def) && key === 'default_value') {
                    return (
                        <Row
                            key={key}
                            label={label}
                            hint="Jedna wartość w linii"
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

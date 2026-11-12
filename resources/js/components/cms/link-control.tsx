import { Input } from '@/components/ui/input';
import { t } from '@/lib/i18n';
import type { FieldDefinition } from '@/types/cms';

type LinkValue = { url?: string; title?: string; target?: string };

type Props = {
    field: FieldDefinition;
    value: unknown;
    onChange: (value: unknown) => void;
    id?: string;
};

/** Odnośnik: adres, tekst i otwieranie w nowej karcie. Pusty adres zapisuje się jako brak wartości. */
export function LinkControl({ value, onChange, id }: Props) {
    const v: LinkValue =
        value && typeof value === 'object' ? (value as LinkValue) : {};
    const set = (patch: LinkValue) => onChange({ ...v, ...patch });

    return (
        <div className="space-y-2">
            <Input
                id={id}
                type="text"
                inputMode="url"
                value={v.url ?? ''}
                placeholder="https://…, /strona, #kotwica, mailto:…"
                onChange={(e) => set({ url: e.target.value })}
            />
            <Input
                type="text"
                value={v.title ?? ''}
                placeholder={t('Tekst odnośnika')}
                onChange={(e) => set({ title: e.target.value })}
            />
            <label className="flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    checked={v.target === '_blank'}
                    onChange={(e) =>
                        set({ target: e.target.checked ? '_blank' : '' })
                    }
                />
                {t('Otwórz w nowej karcie')}
            </label>
        </div>
    );
}

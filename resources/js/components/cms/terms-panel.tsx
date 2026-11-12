import { Link } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cmsUrl } from '@/lib/cms-urls';
import { t } from '@/lib/i18n';

export type TaxonomyOption = {
    key: string;
    label: string;
    label_plural: string;
    terms: { id: number; name: string; depth: number }[];
};

type Props = {
    taxonomies: TaxonomyOption[];
    value: number[];
    canManage: boolean;
    onChange: (ids: number[]) => void;
};

/** Wybór terminów (kategorie, tagi i własne taksonomie) w panelu bocznym edytora treści. */
export function TermsPanel({ taxonomies, value, canManage, onChange }: Props) {
    const toggle = (id: number) =>
        onChange(
            value.includes(id) ? value.filter((v) => v !== id) : [...value, id],
        );

    return (
        <>
            {taxonomies.map((tax) => (
                <Card key={tax.key} className="gap-4">
                    <CardHeader>
                        <CardTitle className="text-base">
                            {tax.label_plural}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {tax.terms.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t('Brak terminów.')}
                            </p>
                        ) : (
                            <div className="max-h-56 space-y-1.5 overflow-y-auto pr-1">
                                {tax.terms.map((term) => (
                                    <label
                                        key={term.id}
                                        className="flex items-center gap-2 text-sm"
                                        style={{
                                            paddingLeft: term.depth * 16,
                                        }}
                                    >
                                        <input
                                            type="checkbox"
                                            checked={value.includes(term.id)}
                                            onChange={() => toggle(term.id)}
                                        />
                                        {term.name}
                                    </label>
                                ))}
                            </div>
                        )}
                        {canManage && (
                            <Link
                                href={`${cmsUrl.taxonomies}?taxonomy=${tax.key}`}
                                className="text-xs text-brand underline-offset-2 hover:underline"
                            >
                                {t('Zarządzaj terminami')}
                            </Link>
                        )}
                    </CardContent>
                </Card>
            ))}
        </>
    );
}

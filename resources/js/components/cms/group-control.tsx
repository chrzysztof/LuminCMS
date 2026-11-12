import { FieldList } from '@/components/cms/fields';
import { cn } from '@/lib/utils';
import type { FieldDefinition, MediaItem } from '@/types/cms';

type Props = {
    field: FieldDefinition;
    value: unknown;
    onChange: (value: unknown) => void;
    media: Record<number, MediaItem>;
    rememberMedia: (items: MediaItem[]) => void;
    errors: Record<string, string | undefined>;
    errorKey: string;
    groupId?: number;
    path: string;
};

/** Pola grupy w jednym obiekcie; układ "block" w ramce, "row" i "table" bez niej. */
export function GroupControl({
    field,
    value,
    onChange,
    media,
    rememberMedia,
    errors,
    errorKey,
    groupId,
    path,
}: Props) {
    const values =
        value && typeof value === 'object' && !Array.isArray(value)
            ? (value as Record<string, unknown>)
            : {};
    const subFields = (
        Array.isArray(field.sub_fields) ? field.sub_fields : []
    ) as FieldDefinition[];

    return (
        <div
            className={cn(
                String(field.layout ?? 'block') === 'block' &&
                    'rounded-lg border bg-card/50 p-3',
            )}
        >
            <FieldList
                fields={subFields}
                values={values}
                errors={errors}
                errorKey={errorKey}
                media={media}
                rememberMedia={rememberMedia}
                groupId={groupId}
                path={path}
                onChange={(name, v) => onChange({ ...values, [name]: v })}
            />
        </div>
    );
}

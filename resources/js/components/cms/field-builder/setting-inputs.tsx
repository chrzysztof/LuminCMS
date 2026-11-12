import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

export const nativeClass =
    'border-input bg-transparent flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30';

export function Row({
    label,
    hint,
    children,
    className,
}: {
    label: string;
    hint?: string;
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <div className={cn('grid gap-1.5', className)}>
            <Label>{label}</Label>
            {children}
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
        </div>
    );
}

export function Toggle({
    label,
    checked,
    onChange,
    hint,
}: {
    label: string;
    checked: boolean;
    onChange: (v: boolean) => void;
    hint?: string;
}) {
    return (
        <div className="flex items-start gap-3">
            <button
                type="button"
                role="switch"
                aria-checked={checked}
                aria-label={label}
                onClick={() => onChange(!checked)}
                className={cn(
                    'relative mt-0.5 h-5 w-9 shrink-0 rounded-full transition-colors',
                    checked ? 'bg-brand' : 'bg-input',
                )}
            >
                <span
                    className={cn(
                        'absolute top-0.5 size-4 rounded-full bg-white shadow transition-all',
                        checked ? 'left-[1.1rem]' : 'left-0.5',
                    )}
                />
            </button>
            <span className="text-sm">
                {label}
                {hint && (
                    <span className="block text-xs text-muted-foreground">
                        {hint}
                    </span>
                )}
            </span>
        </div>
    );
}

export function TextSetting({
    label,
    value,
    onChange,
    type = 'text',
    hint,
}: {
    label: string;
    value: unknown;
    onChange: (v: string) => void;
    type?: string;
    hint?: string;
}) {
    return (
        <Row label={label} hint={hint}>
            <Input
                type={type}
                value={
                    value === null || value === undefined
                        ? ''
                        : String(value as string | number)
                }
                onChange={(e) => onChange(e.target.value)}
            />
        </Row>
    );
}

export function SelectSetting({
    label,
    value,
    options,
    onChange,
}: {
    label: string;
    value: unknown;
    options: [string, string][];
    onChange: (v: string) => void;
}) {
    return (
        <Row label={label}>
            <select
                className={nativeClass}
                value={String(value ?? '')}
                onChange={(e) => onChange(e.target.value)}
            >
                {options.map(([v, l]) => (
                    <option key={v} value={v}>
                        {l}
                    </option>
                ))}
            </select>
        </Row>
    );
}

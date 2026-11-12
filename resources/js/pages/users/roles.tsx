import { Head, router, useForm } from '@inertiajs/react';
import { Check, Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { cmsUrl } from '@/lib/cms-urls';

type Role = {
    key: string;
    label: string;
    description: string;
    users: number;
    capabilities: string[];
};

type Props = {
    roles: Role[];
    capabilities: string[];
    labels: Record<string, string>;
    settings: { default_role: string; registration_enabled: boolean };
};

const nativeClass =
    'border-input bg-transparent flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30';

/** Uprawnienia treści występują w parach post/page; opisujemy je czytelnie. */
function describe(cap: string, labels: Record<string, string>): string {
    if (labels[cap]) return labels[cap];

    const m = cap.match(
        /^(edit|edit_others|edit_published|publish|delete|delete_others|delete_published)_(posts|pages)$/,
    );
    if (!m) return cap;

    const verbs: Record<string, string> = {
        edit: 'Pisanie i edycja własnych',
        edit_others: 'Edycja cudzych',
        edit_published: 'Edycja opublikowanych',
        publish: 'Publikowanie',
        delete: 'Usuwanie własnych',
        delete_others: 'Usuwanie cudzych',
        delete_published: 'Usuwanie opublikowanych',
    };

    return `${verbs[m[1]]} ${m[2] === 'posts' ? 'wpisów' : 'stron'}`;
}

export default function UserRoles({
    roles,
    capabilities,
    labels,
    settings,
}: Props) {
    const form = useForm({
        default_role: settings.default_role,
        registration_enabled: settings.registration_enabled,
    });

    return (
        <>
            <Head title="Role i ustawienia użytkowników" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Role i ustawienia
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Role działają jak w WordPressie. Każdemu użytkownikowi
                        przypisujesz jedną z nich.
                    </p>
                </div>

                <Card className="gap-4">
                    <CardHeader>
                        <CardTitle className="text-base">Rejestracja</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={form.data.registration_enabled}
                                onChange={(e) =>
                                    form.setData(
                                        'registration_enabled',
                                        e.target.checked,
                                    )
                                }
                            />
                            Każdy może się zarejestrować (strona /register)
                        </label>
                        <div className="grid max-w-xs gap-1.5">
                            <Label htmlFor="default_role">
                                Domyślna rola nowych użytkowników
                            </Label>
                            <select
                                id="default_role"
                                className={nativeClass}
                                value={form.data.default_role}
                                onChange={(e) =>
                                    form.setData('default_role', e.target.value)
                                }
                            >
                                {roles.map((r) => (
                                    <option key={r.key} value={r.key}>
                                        {r.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <Button
                            type="button"
                            disabled={form.processing}
                            onClick={() =>
                                form.put(cmsUrl.userSettings, {
                                    preserveScroll: true,
                                })
                            }
                        >
                            {form.processing && (
                                <Loader2 className="animate-spin" />
                            )}
                            Zapisz ustawienia
                        </Button>
                    </CardContent>
                </Card>

                <Card className="gap-0 overflow-x-auto py-0">
                    <table className="w-full min-w-[640px] text-sm">
                        <thead>
                            <tr className="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase">
                                <th className="px-4 py-2.5 font-medium">
                                    Uprawnienie
                                </th>
                                {roles.map((r) => (
                                    <th
                                        key={r.key}
                                        className="px-3 py-2.5 text-center font-medium"
                                    >
                                        <button
                                            type="button"
                                            className="uppercase hover:underline"
                                            onClick={() =>
                                                router.get(cmsUrl.userIndex, {
                                                    role: r.key,
                                                })
                                            }
                                            title={r.description}
                                        >
                                            {r.label}
                                        </button>
                                        <div className="font-normal normal-case">
                                            {r.users} os.
                                        </div>
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {capabilities.map((cap) => (
                                <tr
                                    key={cap}
                                    className="border-b last:border-b-0"
                                >
                                    <td className="px-4 py-2">
                                        {describe(cap, labels)}
                                        <span className="ml-2 font-mono text-xs text-muted-foreground">
                                            {cap}
                                        </span>
                                    </td>
                                    {roles.map((r) => (
                                        <td
                                            key={r.key}
                                            className="px-3 py-2 text-center"
                                        >
                                            {r.capabilities.includes(cap) && (
                                                <Check className="mx-auto size-4 text-brand-foreground" />
                                            )}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </Card>
            </div>
        </>
    );
}

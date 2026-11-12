import { usePage } from '@inertiajs/react';

type Shared = {
    auth: {
        capabilities?: string[];
        role?: string | null;
        role_label?: string | null;
    };
};

/** Uprawnienia zalogowanego użytkownika (role jak w WordPressie). */
export function useCan() {
    const { capabilities = [] } = usePage<Shared>().props.auth;

    return (capability: string) => capabilities.includes(capability);
}

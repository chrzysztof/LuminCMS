import { t } from '@/lib/i18n';
/** Małe wywołania JSON do API panelu (sesja + token XSRF z ciasteczka). */
function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

export class ApiError extends Error {
    constructor(
        message: string,
        public status: number,
        public errors: Record<string, string[]> = {},
    ) {
        super(message);
    }
}

export async function api<T = unknown>(
    url: string,
    options: { method?: string; body?: unknown } = {},
): Promise<T> {
    const isForm = options.body instanceof FormData;
    const response = await fetch(url, {
        method: options.method ?? 'GET',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
            ...(options.body && !isForm
                ? { 'Content-Type': 'application/json' }
                : {}),
        },
        body: options.body
            ? isForm
                ? (options.body as FormData)
                : JSON.stringify(options.body)
            : undefined,
    });

    if (response.status === 204) {
        return undefined as T;
    }

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        const errors = (data.errors ?? {}) as Record<string, string[]>;
        const first = Object.values(errors)[0]?.[0];

        throw new ApiError(
            first ?? data.message ?? t('Wystąpił błąd.'),
            response.status,
            errors,
        );
    }

    return data as T;
}

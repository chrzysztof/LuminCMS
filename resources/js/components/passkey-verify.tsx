import type { UrlMethodPair } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { usePasskeyVerify } from '@laravel/passkeys/react';
import { KeyRound } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';

type Props = {
    routes?: {
        options: UrlMethodPair;
        submit: UrlMethodPair;
    };
    label?: string;
    loadingLabel?: string;
    separator?: string;
    below?: boolean;
};

export default function PasskeyVerify({
    routes,
    label,
    loadingLabel,
    separator,
    below = false,
}: Props = {}) {
    const { verify, isLoading, error, isSupported } = usePasskeyVerify({
        ...(routes && {
            routes: {
                options: routes.options.url,
                submit: routes.submit.url,
            },
        }),
        onSuccess: (response) => {
            router.visit(response.redirect ?? '/dashboard');
        },
    });

    if (!isSupported) {
        return null;
    }

    const button = (
        <div className="grid gap-2">
            <Button
                type="button"
                variant="outline"
                className="w-full bg-card"
                onClick={verify}
                disabled={isLoading}
            >
                {isLoading ? <Spinner /> : <KeyRound className="h-4 w-4" />}
                {isLoading
                    ? (loadingLabel ?? t('Authenticating...'))
                    : (label ?? t('Sign in with a passkey'))}
            </Button>
            {error && <InputError message={error} className="text-center" />}
        </div>
    );
    const separatorEl = (
        <div className="relative my-6">
            <div className="absolute inset-0 flex items-center">
                <Separator className="w-full" />
            </div>
            <div className="relative flex justify-center text-xs uppercase">
                <span className="bg-background px-2 text-muted-foreground">
                    {separator ?? t('Or continue with email')}
                </span>
            </div>
        </div>
    );

    if (below) {
        return (
            <>
                {separatorEl}
                {button}
            </>
        );
    }

    return (
        <>
            {button}
            {separatorEl}
        </>
    );
}

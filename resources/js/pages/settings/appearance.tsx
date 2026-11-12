import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import { LanguageSwitcher } from '@/components/language-switcher';
import { edit as editAppearance } from '@/routes/appearance';
import { t } from '@/lib/i18n';

export default function Appearance() {
    return (
        <>
            <Head title={t('Appearance settings')} />

            <h1 className="sr-only">{t('Appearance settings')}</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('Appearance settings')}
                    description={t(
                        'Update the appearance settings for your account',
                    )}
                />
                <AppearanceTabs />
                <div className="space-y-2">
                    <h3 className="text-sm font-medium">{t('Language')}</h3>
                    <LanguageSwitcher />
                </div>
            </div>
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [
        {
            title: t('Appearance settings'),
            href: editAppearance(),
        },
    ],
};

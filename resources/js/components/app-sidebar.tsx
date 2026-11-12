import { Link, usePage } from '@inertiajs/react';
import {
    Boxes,
    ShieldCheck,
    Users,
    ArrowRightLeft,
    Globe,
    ListTree,
    Search,
    FileText,
    Image as ImageIcon,
    LayoutGrid,
    Newspaper,
    Tags,
    Code2,
    LayoutTemplate,
    Palette,
    Paintbrush,
    Menu as MenuIcon,
    type LucideIcon,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { cmsUrl } from '@/lib/cms-urls';
import { useCan } from '@/lib/can';
import type { NavItem } from '@/types';
import type { ContentTypeInfo } from '@/types/cms';
import { t } from '@/lib/i18n';

const typeIcons: Record<string, LucideIcon> = {
    'file-text': FileText,
    newspaper: Newspaper,
};

export function AppSidebar() {
    const { contentTypes = [] } = usePage<{
        contentTypes?: ContentTypeInfo[];
    }>().props;

    const contentItems: NavItem[] = contentTypes.map((type) => ({
        title: type.label_plural,
        href: cmsUrl.contentIndex(type.key),
        icon: typeIcons[type.icon ?? ''] ?? Boxes,
    }));

    const can = useCan();

    const mainItems: NavItem[] = [
        { title: t('Pulpit'), href: dashboard(), icon: LayoutGrid },
    ];
    const structureItems: NavItem[] = [
        { title: t('Pola własne'), href: cmsUrl.fieldIndex, icon: ListTree },
    ];
    const taxonomyItems: NavItem[] = can('manage_categories')
        ? [
              {
                  title: t('Kategorie i tagi'),
                  href: cmsUrl.taxonomies,
                  icon: Tags,
              },
          ]
        : [];
    const shortcodeItems: NavItem[] = can('edit_posts')
        ? [{ title: t('Shortcody'), href: cmsUrl.shortcodes, icon: Code2 }]
        : [];
    const appearanceItems: NavItem[] = can('manage_options')
        ? [
              { title: t('Motywy'), href: cmsUrl.themes, icon: Paintbrush },
              { title: t('Menu'), href: cmsUrl.menus, icon: MenuIcon },
              { title: t('Części'), href: cmsUrl.parts, icon: LayoutTemplate },
              { title: t('Dostosuj'), href: cmsUrl.customize, icon: Palette },
          ]
        : [];
    const settingsItems: NavItem[] = [
        ...(can('manage_options')
            ? [{ title: t('Witryna'), href: cmsUrl.siteSettings, icon: Globe }]
            : []),
        ...(can('manage_seo')
            ? [
                  { title: 'SEO', href: cmsUrl.seoSettings, icon: Search },
                  {
                      title: t('Przekierowania'),
                      href: cmsUrl.redirectIndex,
                      icon: ArrowRightLeft,
                  },
              ]
            : []),
    ];
    const libraryItems: NavItem[] = can('upload_files')
        ? [{ title: t('Media'), href: cmsUrl.library, icon: ImageIcon }]
        : [];
    const userItems: NavItem[] = [
        ...(can('list_users')
            ? [{ title: t('Użytkownicy'), href: cmsUrl.userIndex, icon: Users }]
            : []),
        ...(can('manage_options')
            ? [
                  {
                      title: t('Role i ustawienia'),
                      href: cmsUrl.userRoles,
                      icon: ShieldCheck,
                  },
              ]
            : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainItems} />
                {contentItems.length +
                    taxonomyItems.length +
                    shortcodeItems.length >
                    0 && (
                    <NavMain
                        label={t('Treści')}
                        items={[
                            ...contentItems,
                            ...taxonomyItems,
                            ...shortcodeItems,
                        ]}
                    />
                )}
                {appearanceItems.length > 0 && (
                    <NavMain label={t('Wygląd')} items={appearanceItems} />
                )}
                {libraryItems.length > 0 && (
                    <NavMain label={t('Biblioteka')} items={libraryItems} />
                )}
                {can('manage_options') && (
                    <NavMain label={t('Struktura')} items={structureItems} />
                )}
                {userItems.length > 0 && (
                    <NavMain label={t('Użytkownicy')} items={userItems} />
                )}
                {settingsItems.length > 0 && (
                    <NavMain label={t('Ustawienia')} items={settingsItems} />
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

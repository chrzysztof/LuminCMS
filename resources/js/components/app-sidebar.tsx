import { Link, usePage } from '@inertiajs/react';
import {
    Boxes,
    ListTree,
    Search,
    FileText,
    Image as ImageIcon,
    LayoutGrid,
    Newspaper,
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
import type { NavItem } from '@/types';
import type { ContentTypeInfo } from '@/types/cms';

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

    const mainItems: NavItem[] = [
        { title: 'Pulpit', href: dashboard(), icon: LayoutGrid },
    ];
    const structureItems: NavItem[] = [
        { title: 'Pola własne', href: cmsUrl.fieldIndex, icon: ListTree },
    ];
    const settingsItems: NavItem[] = [
        { title: 'SEO', href: cmsUrl.seoSettings, icon: Search },
    ];
    const libraryItems: NavItem[] = [
        { title: 'Media', href: cmsUrl.library, icon: ImageIcon },
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
                {contentItems.length > 0 && (
                    <NavMain label="Treści" items={contentItems} />
                )}
                <NavMain label="Biblioteka" items={libraryItems} />
                <NavMain label="Struktura" items={structureItems} />
                <NavMain label="Ustawienia" items={settingsItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

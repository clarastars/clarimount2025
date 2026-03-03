<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { BookOpen, Folder, LayoutGrid, Building, MapPin, Users, Package, HardDrive, FileText, Building2, Settings, Scale, Clock } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const { t } = useI18n();

const mainNavItems = computed((): NavItem[] => [
    {
        title: t('nav.dashboard'),
        href: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: t('nav.companies'),
        href: '/companies',
        icon: Building,
    },
    {
        title: t('nav.departments'),
        href: '/departments',
        icon: Building2,
    },
    {
        title: t('nav.employees'),
        href: '/employees',
        icon: Users,
    },
]);

const assetInventoryNavItems = computed((): NavItem[] => [
    {
        title: t('nav.locations'),
        href: '/locations',
        icon: MapPin,
    },
    {
        title: t('nav.assets'),
        href: '/assets',
        icon: HardDrive,
    },
    {
        title: t('nav.asset_templates'),
        href: '/asset-templates',
        icon: FileText,
    },
    {
        title: t('nav.asset_categories'),
        href: '/asset-categories',
        icon: Package,
    },
]);

const settingsNavItems = computed((): NavItem[] => [
    {
        title: t('nav.shifts'),
        href: '/shifts',
        icon: Clock,
    },
    {
        title: t('nav.labor_law_rules'),
        href: '/labor-law-rules',
        icon: Scale,
    },
]);

const footerNavItems = computed((): NavItem[] => [
    {
        title: t('nav.githubRepo'),
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: Folder,
    },
    {
        title: t('nav.documentation'),
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
]);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" asChild>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
            <NavMain :items="assetInventoryNavItems" :label="t('nav.asset_inventory')" />
            <NavMain :items="settingsNavItems" :label="t('nav.settings')" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>

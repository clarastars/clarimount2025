<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { BookOpen, Folder, LayoutGrid, Building, MapPin, Users, Package, HardDrive, FileText, Building2, Scale, Clock, Mail } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const { t } = useI18n();
const page = usePage();
const isEmployee = computed(() => (page.props.auth as { is_employee?: boolean })?.is_employee ?? false);

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
    {
        title: t('nav.email_test'),
        href: '/settings/email-test',
        icon: Mail,
    },
    {
        title: t('settings.operational_month'),
        href: '/settings/operational-month',
        icon: Clock,
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
            <NavMain v-if="isEmployee" :items="mainNavItems.filter((item) => item.href === '/dashboard')" />
            <template v-else>
                <NavMain :items="mainNavItems" />
                <NavMain :items="assetInventoryNavItems" :label="t('nav.asset_inventory')" />
                <NavMain :items="settingsNavItems" :label="t('nav.settings')" />
            </template>
        </SidebarContent>

        <SidebarFooter>
            <NavFooter v-if="!isEmployee" :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>

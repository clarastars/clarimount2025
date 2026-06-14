<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';

const { t } = useI18n();
const page = usePage();
const authProps = computed(() => (page.props.auth as {
    can_access_settings?: boolean;
    is_employee?: boolean;
}) ?? {});

const canAccessSettings = computed(() => authProps.value.can_access_settings ?? false);
const isEmployeePortal = computed(() => authProps.value.is_employee ?? false);

const sidebarNavItems = computed((): NavItem[] => {
    const personal: NavItem[] = [
        {
            title: t('settings.profile'),
            href: '/settings/profile',
        },
        {
            title: t('settings.password'),
            href: '/settings/password',
        },
        {
            title: t('settings.appearance'),
            href: '/settings/appearance',
        },
    ];

    if (isEmployeePortal.value && ! canAccessSettings.value) {
        return personal;
    }

    const items: NavItem[] = [
        ...personal,
        {
            title: t('settings.email_test'),
            href: '/settings/email-test',
        },
        {
            title: t('settings.operational_month'),
            href: '/settings/operational-month',
        },
        {
            title: t('settings.employee_global_search'),
            href: '/settings/employee-global-search',
        },
    ];

    if (canAccessSettings.value) {
        items.push(
            {
                title: t('settings.permissions_teams'),
                href: '/settings/permissions-teams',
            },
            {
                title: t('settings.salary_run_approvals'),
                href: '/settings/salary-run-approvals',
            },
            {
                title: t('settings.leave_approvals'),
                href: '/settings/leave-approvals',
            },
        );
    }

    return items;
});

const currentPath = page.props.ziggy?.location ? new URL(page.props.ziggy.location).pathname : '';

const compactLayout = computed(
    () => isEmployeePortal.value && ! canAccessSettings.value && currentPath === '/settings/password',
);
</script>

<template>
    <div class="px-4 py-6">
        <template v-if="!compactLayout">
            <Heading :title="t('settings.title')" :description="t('settings.description')" />
        </template>

        <div
            :class="[
                compactLayout ? 'max-w-md' : 'flex flex-col space-y-8 md:space-y-0 lg:flex-row lg:space-y-0 lg:space-x-12',
            ]"
        >
            <aside v-if="!compactLayout" class="w-full max-w-xl lg:w-48">
                <nav class="flex flex-col space-y-1 space-x-0">
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="item.href"
                        variant="ghost"
                        :class="['w-full justify-start', { 'bg-muted': currentPath === item.href }]"
                        as-child
                    >
                        <Link :href="item.href">
                            {{ item.title }}
                        </Link>
                    </Button>
                </nav>
            </aside>

            <Separator v-if="!compactLayout" class="my-6 md:hidden" />

            <div :class="compactLayout ? '' : 'flex-1 md:max-w-2xl'">
                <section :class="compactLayout ? '' : 'max-w-xl space-y-12'">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>

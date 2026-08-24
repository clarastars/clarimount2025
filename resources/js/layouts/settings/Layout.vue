<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';

interface Props {
    contentWidth?: 'default' | 'wide';
}

const props = withDefaults(defineProps<Props>(), {
    contentWidth: 'default',
});

const { t } = useI18n();
const page = usePage();
const authProps = computed(() => (page.props.auth as {
    can_access_settings?: boolean;
    can_manage_leave_types?: boolean;
    is_employee?: boolean;
    is_super_admin?: boolean;
}) ?? {});

const canAccessSettings = computed(() => authProps.value.can_access_settings ?? false);
const canManageLeaveTypes = computed(() => authProps.value.can_manage_leave_types ?? false);
const canOpenSettingsArea = computed(() => canAccessSettings.value || canManageLeaveTypes.value);
const isEmployeePortal = computed(() => authProps.value.is_employee ?? false);
const isSuperAdmin = computed(() => authProps.value.is_super_admin ?? false);

const sidebarNavItems = computed((): NavItem[] => {
    const personal: NavItem[] = [
        {
            title: t('settings.profile'),
            href: '/settings/profile',
        },
    ];

    if (! isEmployeePortal.value || canAccessSettings.value) {
        personal.push({
            title: t('settings.password'),
            href: '/settings/password',
        });
    }

    personal.push({
        title: t('settings.appearance'),
        href: '/settings/appearance',
    });

    if (isEmployeePortal.value && ! canOpenSettingsArea.value) {
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
            {
                title: t('settings.salary_certificate_approvals'),
                href: '/settings/salary-certificate-approvals',
            },
            {
                title: t('settings.entitlement_settlement_approvals'),
                href: '/settings/entitlement-settlement-approvals',
            },
            {
                title: t('settings.salary_certificate_fee'),
                href: '/settings/salary-certificate-fee',
            },
            {
                title: t('settings.user_login'),
                href: '/settings/user-login',
            },
        );
    }

    if (canOpenSettingsArea.value) {
        items.push({
            title: t('settings.leave_types'),
            href: '/settings/leave-types',
        });
    }

    if (isSuperAdmin.value) {
        items.push({
            title: t('settings.missing_hire_date_export'),
            href: '/settings/missing-hire-date-export',
        });
    }

    return items;
});

const currentPath = page.props.ziggy?.location ? new URL(page.props.ziggy.location).pathname : '';
const contentWidthClass = computed(() =>
    props.contentWidth === 'wide' ? 'max-w-6xl' : 'max-w-xl',
);
</script>

<template>
    <div class="px-4 py-6">
        <Heading :title="t('settings.title')" :description="t('settings.description')" />

        <div class="flex flex-col space-y-8 md:space-y-0 lg:flex-row lg:space-y-0 lg:space-x-12">
            <aside class="w-full max-w-xl lg:w-48">
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

            <Separator class="my-6 md:hidden" />

            <div :class="['flex-1', props.contentWidth === 'wide' ? 'md:max-w-6xl' : 'md:max-w-2xl']">
                <section :class="[contentWidthClass, 'space-y-12']">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>

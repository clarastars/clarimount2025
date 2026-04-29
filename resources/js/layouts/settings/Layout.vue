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
const canAccessSettings = computed(() => ((page.props.auth as { can_access_settings?: boolean })?.can_access_settings ?? false));

const sidebarNavItems = computed((): NavItem[] => [
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
    ...(canAccessSettings.value
        ? [
              {
                  title: t('settings.permissions_teams'),
                  href: '/settings/permissions-teams',
              },
          ]
        : []),
]);

const currentPath = page.props.ziggy?.location ? new URL(page.props.ziggy.location).pathname : '';
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

            <div class="flex-1 md:max-w-2xl">
                <section class="max-w-xl space-y-12">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>

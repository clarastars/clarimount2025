<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import UpcomingBirthdaysNotice from '@/components/UpcomingBirthdaysNotice.vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';
import type { BreadcrumbItem } from '@/types';
import { Card, CardContent } from '@/components/ui/card';
import { Handshake } from 'lucide-vue-next';

const { t } = useI18n();

interface UpcomingBirthday {
    id: number;
    full_name: string;
    company_name?: string | null;
    department_name?: string | null;
    birth_date: string;
    birthday_month_day: string;
    days_until: number;
    is_today: boolean;
}

interface Props {
    employee: {
        id: number;
        first_name: string;
        last_name: string;
        full_name: string;
    };
    dashboardSubtitle?: string;
    upcomingBirthdays?: UpcomingBirthday[];
}

const props = withDefaults(defineProps<Props>(), {
    upcomingBirthdays: () => [],
});

const breadcrumbs = computed((): BreadcrumbItem[] => []);
</script>

<template>
    <Head :title="t('nav.dashboard')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col items-center gap-6 p-6 pt-4">
            <div class="w-full max-w-2xl">
                <UpcomingBirthdaysNotice :items="upcomingBirthdays" />
            </div>

            <Card class="w-full max-w-2xl border-border/60 shadow-xl bg-gradient-to-b from-background to-muted/30">
                <CardContent class="flex flex-col items-center gap-4 py-12 text-center">
                    <div class="rounded-full bg-primary/10 p-4 text-primary">
                        <Handshake class="h-8 w-8" />
                    </div>
                    <h1 class="text-3xl font-bold tracking-tight text-foreground">
                        {{ t('dashboard.employee_welcome') }}{{ props.employee?.first_name ? `، ${props.employee.first_name}` : '' }}
                    </h1>
                    <p class="text-muted-foreground max-w-xl">
                        {{ props.dashboardSubtitle ?? t('dashboard.employee_subtitle') }}
                    </p>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

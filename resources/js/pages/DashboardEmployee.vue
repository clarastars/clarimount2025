<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';
import type { BreadcrumbItem } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { CalendarDays } from 'lucide-vue-next';

const { t } = useI18n();

interface Props {
    employee: {
        id: number;
        first_name: string;
        last_name: string;
        full_name: string;
        annual_leave_balance: number;
        remaining_annual_leave_balance: number;
    };
}

const props = defineProps<Props>();

const breadcrumbs = computed((): BreadcrumbItem[] => []);
</script>

<template>
    <Head :title="t('nav.dashboard')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6 pt-0">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-foreground">
                    {{ t('dashboard.employee_welcome') }}{{ props.employee?.first_name ? `، ${props.employee.first_name}` : '' }}
                </h1>
                <p class="text-muted-foreground mt-1">
                    {{ t('dashboard.employee_subtitle') }}
                </p>
            </div>

            <Card class="max-w-md border-blue-200 dark:border-blue-800 bg-blue-50/50 dark:bg-blue-950/20">
                <CardHeader class="pb-2">
                    <CardTitle class="flex items-center gap-2 text-base font-medium">
                        <CalendarDays class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                        {{ t('dashboard.leave_balance') }}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-3xl font-bold text-foreground">
                        {{ props.employee?.remaining_annual_leave_balance ?? props.employee?.annual_leave_balance ?? 0 }}
                        <span class="text-lg font-normal text-muted-foreground ms-1">{{ t('dashboard.days') }}</span>
                    </div>
                    <p class="text-sm text-muted-foreground mt-1">
                        {{ t('dashboard.leave_balance_description') }}
                    </p>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

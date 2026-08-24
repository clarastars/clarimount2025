<template>
    <Head :title="t('entitlement_settlement.history_title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-6xl space-y-6 px-4 py-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">{{ t('entitlement_settlement.history_title') }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ employee.full_name }}
                        <span v-if="employee.employee_id" class="font-mono"> — #{{ employee.employee_id }}</span>
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" as-child>
                        <Link :href="route('employees.show', employee.id)">{{ t('common.cancel') }}</Link>
                    </Button>
                    <Button as-child>
                        <Link :href="route('employees.entitlement-settlement.create', employee.id)">
                            {{ t('employees.settle_entitlements') }}
                        </Link>
                    </Button>
                </div>
            </div>

            <Card v-if="settlements.length === 0">
                <CardContent class="py-12 text-center text-muted-foreground">
                    {{ t('entitlement_settlement.no_previous') }}
                </CardContent>
            </Card>

            <Card v-else>
                <CardHeader>
                    <CardTitle>{{ t('entitlement_settlement.history_title') }} ({{ settlements.length }})</CardTitle>
                </CardHeader>
                <CardContent class="overflow-x-auto p-0">
                    <table class="w-full min-w-[640px] text-sm">
                        <thead class="border-b bg-muted/40 text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 text-start font-medium">{{ t('entitlement_settlement.settlement_date') }}</th>
                                <th class="px-4 py-3 text-start font-medium">{{ t('entitlement_settlement.reason') }}</th>
                                <th class="px-4 py-3 text-start font-medium">{{ t('entitlement_settlement.status') }}</th>
                                <th class="px-4 py-3 text-start font-medium">{{ t('entitlement_settlement.total_dues') }}</th>
                                <th class="px-4 py-3 text-start font-medium">{{ t('entitlement_settlement.total_deductions') }}</th>
                                <th class="px-4 py-3 text-start font-medium">{{ t('entitlement_settlement.net_due') }}</th>
                                <th class="px-4 py-3 text-start font-medium">{{ t('entitlement_settlement.created_by') }}</th>
                                <th class="px-4 py-3 text-end font-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in settlements"
                                :key="item.id"
                                class="border-b last:border-0 hover:bg-muted/20"
                            >
                                <td class="px-4 py-3 whitespace-nowrap">{{ formatDate(item.settlement_date) }}</td>
                                <td class="px-4 py-3 max-w-[220px] truncate" :title="item.reason">{{ item.reason }}</td>
                                <td class="px-4 py-3">
                                    <span :class="statusBadgeClass(item.status)">
                                        {{ t(`entitlement_settlement.status_${item.status}`) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 tabular-nums whitespace-nowrap">{{ formatCurrency(item.total_dues) }}</td>
                                <td class="px-4 py-3 tabular-nums whitespace-nowrap">{{ formatCurrency(item.total_deductions) }}</td>
                                <td class="px-4 py-3 font-semibold tabular-nums whitespace-nowrap text-blue-700">
                                    {{ formatCurrency(item.net_due) }}
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ item.created_by_name || '—' }}</td>
                                <td class="px-4 py-3 text-end">
                                    <Button variant="outline" size="sm" as-child>
                                        <Link :href="route('employees.entitlement-settlement.show', [employee.id, item.id])">
                                            {{ t('entitlement_settlement.view') }}
                                        </Link>
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { BreadcrumbItem } from '@/types';

type SettlementSummary = {
    id: number;
    settlement_date: string | null;
    reason: string;
    status: 'pending' | 'approved' | 'rejected';
    total_dues: number;
    total_deductions: number;
    net_due: number;
    created_by_name?: string | null;
};

const props = defineProps<{
    employee: { id: number; full_name: string; employee_id?: string | null };
    settlements: SettlementSummary[];
}>();

const { t, locale } = useI18n();

const breadcrumbs = computed((): BreadcrumbItem[] => [
    { title: t('nav.dashboard'), href: '/dashboard' },
    { title: t('employees.title'), href: '/employees' },
    { title: props.employee.full_name, href: route('employees.show', props.employee.id) },
    { title: t('entitlement_settlement.history_title'), href: route('employees.entitlement-settlement.index', props.employee.id) },
]);

const formatCurrency = (amount: number) => `${Number(amount).toFixed(2)} SAR`;

const formatDate = (value?: string | null) => {
    if (!value) {
        return '—';
    }

    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);
    if (!match) {
        return value;
    }

    const [, year, month, day] = match;

    return new Intl.DateTimeFormat(locale.value === 'ar' ? 'ar-SA' : 'en-GB').format(
        new Date(Number(year), Number(month) - 1, Number(day)),
    );
};

const statusBadgeClass = (status: string) => {
    const base = 'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium';

    if (status === 'approved') {
        return `${base} bg-emerald-100 text-emerald-800`;
    }

    if (status === 'rejected') {
        return `${base} bg-red-100 text-red-800`;
    }

    return `${base} bg-amber-100 text-amber-800`;
};
</script>

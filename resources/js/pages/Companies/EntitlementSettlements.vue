<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { HandCoins } from 'lucide-vue-next';

import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDisplayDate } from '@/lib/formatDate';
import type { BreadcrumbItem } from '@/types';

interface SettlementItem {
    id: number;
    settlement_date: string | null;
    reason: string;
    status: 'pending' | 'approved' | 'rejected';
    total_dues: number;
    total_deductions: number;
    net_due: number;
    created_at?: string | null;
    reviewed_at?: string | null;
    created_by_name?: string | null;
    current_step_title?: string | null;
    url: string;
    employee: {
        id: number;
        full_name: string;
        employee_id?: string | null;
        job_title?: string | null;
    };
}

interface CompanyItem {
    id: number;
    name_en: string;
    name_ar: string;
}

const props = withDefaults(defineProps<{
    company: CompanyItem;
    pendingSettlements?: SettlementItem[];
    approvedSettlements?: SettlementItem[];
    rejectedSettlements?: SettlementItem[];
    hasApprovalWorkflow?: boolean;
}>(), {
    pendingSettlements: () => [],
    approvedSettlements: () => [],
    rejectedSettlements: () => [],
    hasApprovalWorkflow: false,
});

const { t, locale } = useI18n();
const requestsTab = ref<'pending' | 'approved' | 'rejected'>('pending');

const companyName = computed(() => {
    if (locale.value === 'ar' && props.company.name_ar) {
        return props.company.name_ar;
    }

    return props.company.name_en || props.company.name_ar;
});

const breadcrumbs = computed((): BreadcrumbItem[] => [
    { title: t('nav.dashboard'), href: '/dashboard' },
    { title: t('companies.title'), href: '/companies' },
    { title: companyName.value, href: `/companies/${props.company.id}` },
    {
        title: t('entitlement_settlement.company_title'),
        href: route('companies.entitlement-settlements.index', props.company.id),
    },
]);

const activeSettlements = computed(() => {
    if (requestsTab.value === 'approved') {
        return props.approvedSettlements;
    }

    if (requestsTab.value === 'rejected') {
        return props.rejectedSettlements;
    }

    return props.pendingSettlements;
});

const emptyMessage = computed(() => {
    if (requestsTab.value === 'approved') {
        return t('entitlement_settlement.no_approved_company');
    }

    if (requestsTab.value === 'rejected') {
        return t('entitlement_settlement.no_rejected_company');
    }

    return t('entitlement_settlement.no_pending_company');
});

const formatCurrency = (amount: number | null | undefined) =>
    `${Number(amount ?? 0).toLocaleString(locale.value === 'ar' ? 'ar-SA' : 'en-GB', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })} SAR`;

const formatDate = (value?: string | null) => formatDisplayDate(value, locale.value, '—');

const statusBadgeClass = (status: string) => {
    const base = 'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium';

    if (status === 'approved') {
        return `${base} bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300`;
    }

    if (status === 'rejected') {
        return `${base} bg-red-100 text-red-800 dark:bg-red-950/40 dark:text-red-300`;
    }

    return `${base} bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300`;
};
</script>

<template>
    <Head :title="t('entitlement_settlement.company_title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-6xl space-y-6 px-4 py-6">
            <div>
                <div class="flex items-center gap-2">
                    <HandCoins class="h-6 w-6 text-muted-foreground" />
                    <h1 class="text-2xl font-bold tracking-tight">
                        {{ t('entitlement_settlement.company_title') }}
                    </h1>
                </div>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ companyName }} — {{ t('entitlement_settlement.company_description') }}
                </p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('entitlement_settlement.company_list_title') }}</CardTitle>
                    <CardDescription>{{ t('entitlement_settlement.company_list_description') }}</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="flex flex-wrap gap-2 border-b pb-3">
                        <Button
                            size="sm"
                            :variant="requestsTab === 'pending' ? 'default' : 'outline'"
                            @click="requestsTab = 'pending'"
                        >
                            {{ t('entitlement_settlement.tab_pending') }}
                            <Badge v-if="pendingSettlements.length > 0" variant="secondary" class="ms-2">
                                {{ pendingSettlements.length }}
                            </Badge>
                        </Button>
                        <Button
                            size="sm"
                            :variant="requestsTab === 'approved' ? 'default' : 'outline'"
                            @click="requestsTab = 'approved'"
                        >
                            {{ t('entitlement_settlement.tab_approved') }}
                            <Badge v-if="approvedSettlements.length > 0" variant="secondary" class="ms-2">
                                {{ approvedSettlements.length }}
                            </Badge>
                        </Button>
                        <Button
                            size="sm"
                            :variant="requestsTab === 'rejected' ? 'default' : 'outline'"
                            @click="requestsTab = 'rejected'"
                        >
                            {{ t('entitlement_settlement.tab_rejected') }}
                            <Badge v-if="rejectedSettlements.length > 0" variant="secondary" class="ms-2">
                                {{ rejectedSettlements.length }}
                            </Badge>
                        </Button>
                    </div>

                    <div v-if="activeSettlements.length === 0" class="py-10 text-center text-sm text-muted-foreground">
                        {{ emptyMessage }}
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full min-w-[720px] text-sm">
                            <thead class="border-b bg-muted/40 text-muted-foreground">
                                <tr>
                                    <th class="px-3 py-3 text-start font-medium">{{ t('entitlement_settlement.employee_name') }}</th>
                                    <th class="px-3 py-3 text-start font-medium">{{ t('entitlement_settlement.settlement_date') }}</th>
                                    <th class="px-3 py-3 text-start font-medium">{{ t('entitlement_settlement.reason') }}</th>
                                    <th class="px-3 py-3 text-start font-medium">{{ t('entitlement_settlement.status') }}</th>
                                    <th
                                        v-if="requestsTab === 'pending' && hasApprovalWorkflow"
                                        class="px-3 py-3 text-start font-medium"
                                    >
                                        {{ t('entitlement_settlement.approval_current_step_short') }}
                                    </th>
                                    <th class="px-3 py-3 text-start font-medium">{{ t('entitlement_settlement.net_due') }}</th>
                                    <th class="px-3 py-3 text-start font-medium">{{ t('entitlement_settlement.created_by') }}</th>
                                    <th class="px-3 py-3 text-end font-medium"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in activeSettlements"
                                    :key="item.id"
                                    class="border-b last:border-0 hover:bg-muted/20"
                                >
                                    <td class="px-3 py-3">
                                        <p class="font-medium">{{ item.employee.full_name }}</p>
                                        <p v-if="item.employee.employee_id" class="text-xs text-muted-foreground font-mono">
                                            #{{ item.employee.employee_id }}
                                        </p>
                                        <p v-if="item.employee.job_title" class="text-xs text-muted-foreground">
                                            {{ item.employee.job_title }}
                                        </p>
                                    </td>
                                    <td class="px-3 py-3 whitespace-nowrap">{{ formatDate(item.settlement_date) }}</td>
                                    <td class="px-3 py-3 max-w-[200px] truncate" :title="item.reason">{{ item.reason || '—' }}</td>
                                    <td class="px-3 py-3">
                                        <span :class="statusBadgeClass(item.status)">
                                            {{ t(`entitlement_settlement.status_${item.status}`) }}
                                        </span>
                                    </td>
                                    <td
                                        v-if="requestsTab === 'pending' && hasApprovalWorkflow"
                                        class="px-3 py-3 text-muted-foreground"
                                    >
                                        {{ item.current_step_title || '—' }}
                                    </td>
                                    <td class="px-3 py-3 font-semibold tabular-nums whitespace-nowrap text-blue-700 dark:text-blue-400">
                                        {{ formatCurrency(item.net_due) }}
                                    </td>
                                    <td class="px-3 py-3 text-muted-foreground">{{ item.created_by_name || '—' }}</td>
                                    <td class="px-3 py-3 text-end">
                                        <Button variant="outline" size="sm" as-child>
                                            <Link :href="item.url">
                                                {{ t('entitlement_settlement.view') }}
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

<template>
    <Head :title="t('entitlement_settlement.detail_title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-6xl space-y-6 px-4 py-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">{{ t('entitlement_settlement.detail_title') }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ employee.full_name }} — {{ formatDate(settlement.settlement_date) }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" as-child>
                        <Link :href="route('employees.entitlement-settlement.index', employee.id)">
                            {{ t('entitlement_settlement.history_title') }}
                        </Link>
                    </Button>
                    <Button as-child>
                        <Link :href="route('employees.entitlement-settlement.create', employee.id)">
                            {{ t('employees.settle_entitlements') }}
                        </Link>
                    </Button>
                </div>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('entitlement_settlement.employee_info') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="rounded-lg border bg-muted/20 px-4 py-3">
                            <p class="text-xs text-muted-foreground">{{ t('entitlement_settlement.settlement_date') }}</p>
                            <p class="mt-1 text-sm font-medium">{{ formatDate(settlement.settlement_date) }}</p>
                        </div>
                        <div class="rounded-lg border bg-muted/20 px-4 py-3">
                            <p class="text-xs text-muted-foreground">{{ t('entitlement_settlement.reason') }}</p>
                            <p class="mt-1 text-sm font-medium">{{ settlement.reason }}</p>
                        </div>
                        <div class="rounded-lg border bg-muted/20 px-4 py-3">
                            <p class="text-xs text-muted-foreground">{{ t('entitlement_settlement.last_settlement_date') }}</p>
                            <p class="mt-1 text-sm font-medium">{{ formatDate(settlement.last_settlement_date) }}</p>
                        </div>
                        <div class="rounded-lg border bg-muted/20 px-4 py-3">
                            <p class="text-xs text-muted-foreground">{{ t('entitlement_settlement.service_days') }}</p>
                            <p class="mt-1 text-sm font-medium">{{ formatNumber(settlement.service_days) }}</p>
                        </div>
                        <div class="rounded-lg border bg-muted/20 px-4 py-3">
                            <p class="text-xs text-muted-foreground">{{ t('entitlement_settlement.gross_salary') }}</p>
                            <p class="mt-1 text-sm font-medium">{{ formatCurrency(settlement.gross_salary) }}</p>
                        </div>
                        <div class="rounded-lg border bg-muted/20 px-4 py-3">
                            <p class="text-xs text-muted-foreground">{{ t('entitlement_settlement.created_by') }}</p>
                            <p class="mt-1 text-sm font-medium">{{ settlement.created_by_name || '—' }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div class="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-emerald-700">{{ t('entitlement_settlement.dues') }}</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div
                            v-for="row in duesRows"
                            :key="row.label"
                            class="flex items-start justify-between gap-3"
                        >
                            <div class="min-w-0">
                                <p class="text-sm font-medium">{{ row.label }}</p>
                                <p v-if="row.hint" class="text-xs text-muted-foreground">{{ row.hint }}</p>
                            </div>
                            <p class="shrink-0 font-semibold tabular-nums">{{ formatCurrency(row.value) }}</p>
                        </div>
                        <div class="flex items-center justify-between border-t pt-3 font-semibold">
                            <span>{{ t('entitlement_settlement.total_dues') }}</span>
                            <span>{{ formatCurrency(settlement.total_dues) }}</span>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-red-700">{{ t('entitlement_settlement.deductions') }}</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div
                            v-for="row in deductionRows"
                            :key="row.label"
                            class="flex items-start justify-between gap-3"
                        >
                            <div class="min-w-0">
                                <p class="text-sm font-medium">{{ row.label }}</p>
                                <p v-if="row.hint" class="text-xs text-muted-foreground">{{ row.hint }}</p>
                            </div>
                            <p class="shrink-0 font-semibold tabular-nums">{{ formatCurrency(row.value) }}</p>
                        </div>
                        <div class="flex items-center justify-between border-t pt-3 font-semibold">
                            <span>{{ t('entitlement_settlement.total_deductions') }}</span>
                            <span>{{ formatCurrency(settlement.total_deductions) }}</span>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card class="border-blue-200 bg-blue-50/40">
                <CardContent class="py-6">
                    <p class="text-sm text-muted-foreground">{{ t('entitlement_settlement.net_due') }}</p>
                    <p class="text-3xl font-bold text-blue-700">{{ formatCurrency(settlement.net_due) }}</p>
                    <p v-if="settlement.notes" class="mt-4 whitespace-pre-wrap text-sm text-muted-foreground">
                        <span class="font-medium text-foreground">{{ t('entitlement_settlement.notes') }}:</span>
                        {{ settlement.notes }}
                    </p>
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

type SettlementDetail = {
    id: number;
    settlement_date: string | null;
    reason: string;
    last_settlement_date?: string | null;
    service_days: number;
    gross_salary: number;
    remaining_leave_days: number;
    salary_unpaid_days: number;
    used_annual_leave_days: number;
    end_of_service_bonus: number;
    travel_tickets: number;
    due_commissions: number;
    salary_dues: number;
    annual_leave_dues: number;
    other_dues: number;
    total_dues: number;
    advances_deduction: number;
    custody_deduction: number;
    excess_leave_deduction: number;
    social_insurance_deduction: number;
    used_annual_leave_deduction: number;
    total_deductions: number;
    net_due: number;
    notes?: string | null;
    created_by_name?: string | null;
};

const props = defineProps<{
    employee: { id: number; full_name: string; employee_id?: string | null };
    settlement: SettlementDetail;
}>();

const { t, locale } = useI18n();

const breadcrumbs = computed((): BreadcrumbItem[] => [
    { title: t('nav.dashboard'), href: '/dashboard' },
    { title: t('employees.title'), href: '/employees' },
    { title: props.employee.full_name, href: route('employees.show', props.employee.id) },
    { title: t('entitlement_settlement.history_title'), href: route('employees.entitlement-settlement.index', props.employee.id) },
    {
        title: t('entitlement_settlement.detail_title'),
        href: route('employees.entitlement-settlement.show', [props.employee.id, props.settlement.id]),
    },
]);

const duesRows = computed(() => [
    { label: t('entitlement_settlement.end_of_service_bonus'), value: props.settlement.end_of_service_bonus },
    { label: t('entitlement_settlement.travel_tickets'), value: props.settlement.travel_tickets },
    { label: t('entitlement_settlement.due_commissions'), value: props.settlement.due_commissions },
    {
        label: t('entitlement_settlement.salary_dues'),
        value: props.settlement.salary_dues,
        hint: `${formatNumber(props.settlement.salary_unpaid_days)} ${t('leaves.days')}`,
    },
    {
        label: t('entitlement_settlement.annual_leave_dues'),
        value: props.settlement.annual_leave_dues,
        hint: `${formatNumber(props.settlement.remaining_leave_days)} ${t('leaves.days')}`,
    },
    { label: t('entitlement_settlement.other_dues'), value: props.settlement.other_dues },
]);

const deductionRows = computed(() => [
    { label: t('entitlement_settlement.advances'), value: props.settlement.advances_deduction },
    { label: t('entitlement_settlement.custody'), value: props.settlement.custody_deduction },
    { label: t('entitlement_settlement.excess_leave'), value: props.settlement.excess_leave_deduction },
    { label: t('entitlement_settlement.social_insurance'), value: props.settlement.social_insurance_deduction },
    {
        label: t('entitlement_settlement.used_annual_leave'),
        value: props.settlement.used_annual_leave_deduction,
        hint: `${formatNumber(props.settlement.used_annual_leave_days)} ${t('leaves.days')}`,
    },
]);

const formatCurrency = (amount: number) => `${Number(amount).toFixed(2)} SAR`;

const formatNumber = (value: number | string | null | undefined) => {
    const numeric = Number(value ?? 0);
    return new Intl.NumberFormat(locale.value === 'ar' ? 'ar-SA' : 'en-US', {
        maximumFractionDigits: 2,
    }).format(numeric);
};

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
</script>

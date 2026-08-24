<template>
    <Head :title="t('entitlement_settlement.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-6xl space-y-6 px-4 py-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">{{ t('entitlement_settlement.title') }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ employee.full_name }} — {{ t('entitlement_settlement.description') }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" as-child>
                        <Link :href="route('employees.entitlement-settlement.index', employee.id)">
                            {{ t('entitlement_settlement.history_title') }}
                            <span
                                v-if="previousSettlementsCount > 0"
                                class="ms-1 rounded-full bg-slate-100 px-1.5 py-0.5 text-xs tabular-nums text-slate-700"
                            >
                                {{ previousSettlementsCount }}
                            </span>
                        </Link>
                    </Button>
                    <Button variant="outline" as-child>
                        <Link :href="route('employees.show', employee.id)">{{ t('common.cancel') }}</Link>
                    </Button>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>{{ t('entitlement_settlement.settlement_date') }}</CardTitle>
                    </CardHeader>
                    <CardContent class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label for="settlement_date">{{ t('entitlement_settlement.settlement_date') }}</Label>
                            <Input
                                id="settlement_date"
                                v-model="form.settlement_date"
                                type="date"
                                class="mt-1"
                                required
                            />
                            <p v-if="form.errors.settlement_date" class="mt-1 text-sm text-red-500">
                                {{ form.errors.settlement_date }}
                            </p>
                            <p v-if="isRefreshing" class="mt-1 text-xs text-muted-foreground">
                                {{ t('entitlement_settlement.refreshing') }}
                            </p>
                        </div>
                        <div>
                            <Label for="reason">{{ t('entitlement_settlement.reason') }}</Label>
                            <Input
                                id="reason"
                                v-model="form.reason"
                                type="text"
                                class="mt-1"
                                :placeholder="t('entitlement_settlement.reason_placeholder')"
                                required
                            />
                            <p v-if="form.errors.reason" class="mt-1 text-sm text-red-500">
                                {{ form.errors.reason }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{{ t('entitlement_settlement.employee_info') }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div class="rounded-lg border bg-muted/20 px-4 py-3">
                                <p class="text-xs text-muted-foreground">{{ t('employees.full_name') }}</p>
                                <p class="mt-1 text-sm font-medium">{{ preview.employee.full_name }}</p>
                            </div>
                            <div class="rounded-lg border bg-muted/20 px-4 py-3">
                                <p class="text-xs text-muted-foreground">{{ t('entitlement_settlement.nationality') }}</p>
                                <p class="mt-1 text-sm font-medium">{{ preview.employee.nationality || '—' }}</p>
                            </div>
                            <div class="rounded-lg border bg-muted/20 px-4 py-3">
                                <p class="text-xs text-muted-foreground">{{ t('entitlement_settlement.department') }}</p>
                                <p class="mt-1 text-sm font-medium">{{ preview.employee.department || '—' }}</p>
                            </div>
                            <div class="rounded-lg border bg-muted/20 px-4 py-3">
                                <p class="text-xs text-muted-foreground">{{ t('entitlement_settlement.hire_date') }}</p>
                                <p class="mt-1 text-sm font-medium">{{ formatDate(preview.employee.hire_date) }}</p>
                            </div>
                            <div class="rounded-lg border bg-muted/20 px-4 py-3">
                                <p class="text-xs text-muted-foreground">{{ t('entitlement_settlement.last_settlement_date') }}</p>
                                <p class="mt-1 text-sm font-medium">{{ formatDate(preview.last_settlement_date) }}</p>
                            </div>
                            <div class="rounded-lg border bg-muted/20 px-4 py-3">
                                <p class="text-xs text-muted-foreground">{{ t('entitlement_settlement.service_days') }}</p>
                                <p class="mt-1 text-sm font-medium">{{ formatNumber(preview.service_days) }}</p>
                            </div>
                            <div class="rounded-lg border bg-muted/20 px-4 py-3">
                                <p class="text-xs text-muted-foreground">{{ t('entitlement_settlement.annual_leave_entitlement') }}</p>
                                <p class="mt-1 text-sm font-medium">
                                    {{ formatNumber(preview.employee.annual_leave_balance) }} {{ t('leaves.days') }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{{ t('entitlement_settlement.salary_breakdown') }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div class="rounded-lg border bg-muted/20 px-4 py-3">
                                <p class="text-xs text-muted-foreground">{{ t('entitlement_settlement.basic_salary') }}</p>
                                <p class="mt-1 text-sm font-medium">{{ formatCurrency(preview.salary_breakdown.basic_salary) }}</p>
                            </div>
                            <div class="rounded-lg border bg-muted/20 px-4 py-3">
                                <p class="text-xs text-muted-foreground">{{ t('entitlement_settlement.allowance_housing') }}</p>
                                <p class="mt-1 text-sm font-medium">{{ formatCurrency(preview.salary_breakdown.allowance_housing) }}</p>
                            </div>
                            <div class="rounded-lg border bg-muted/20 px-4 py-3">
                                <p class="text-xs text-muted-foreground">{{ t('entitlement_settlement.allowance_transportation') }}</p>
                                <p class="mt-1 text-sm font-medium">{{ formatCurrency(preview.salary_breakdown.allowance_transportation) }}</p>
                            </div>
                            <div class="rounded-lg border bg-muted/20 px-4 py-3">
                                <p class="text-xs text-muted-foreground">{{ t('entitlement_settlement.allowance_personal_car') }}</p>
                                <p class="mt-1 text-sm font-medium">{{ formatCurrency(preview.salary_breakdown.allowance_personal_car) }}</p>
                            </div>
                            <div class="rounded-lg border bg-emerald-50 px-4 py-3">
                                <p class="text-xs text-muted-foreground">{{ t('entitlement_settlement.gross_salary') }}</p>
                                <p class="mt-1 text-base font-semibold text-emerald-700">{{ formatCurrency(preview.salary_breakdown.gross_salary) }}</p>
                            </div>
                            <div class="rounded-lg border bg-muted/20 px-4 py-3">
                                <p class="text-xs text-muted-foreground">{{ t('entitlement_settlement.gross_daily_wage') }}</p>
                                <p class="mt-1 text-sm font-medium">{{ formatCurrency(preview.salary_breakdown.gross_daily_wage ?? 0) }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div class="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-emerald-700">{{ t('entitlement_settlement.dues') }}</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div v-for="field in manualDuesFields" :key="field.key" class="space-y-1">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-sm font-medium">{{ field.label }}</p>
                                    <span class="shrink-0 rounded bg-amber-100 px-2 py-0.5 text-[10px] text-amber-800">
                                        {{ t('entitlement_settlement.manual_field') }}
                                    </span>
                                </div>
                                <Input
                                    v-model="form[field.key]"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="tabular-nums"
                                />
                            </div>

                            <div class="space-y-1">
                                <div class="flex items-center justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-medium">{{ t('entitlement_settlement.salary_dues') }}</p>
                                        <p v-if="salaryDuesHint" class="text-xs text-muted-foreground">{{ salaryDuesHint }}</p>
                                    </div>
                                    <span class="shrink-0 rounded bg-slate-100 px-2 py-0.5 text-[10px] text-slate-600">
                                        {{ t('entitlement_settlement.auto_field') }}
                                    </span>
                                </div>
                                <p class="text-lg font-semibold tabular-nums">{{ formatCurrency(preview.dues.salary_dues) }}</p>
                            </div>

                            <div class="space-y-1">
                                <div class="flex items-center justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-medium">{{ t('entitlement_settlement.annual_leave_dues') }}</p>
                                        <p class="text-xs text-muted-foreground">{{ annualLeaveHint }}</p>
                                    </div>
                                    <span class="shrink-0 rounded bg-slate-100 px-2 py-0.5 text-[10px] text-slate-600">
                                        {{ t('entitlement_settlement.auto_field') }}
                                    </span>
                                </div>
                                <p class="text-lg font-semibold tabular-nums">{{ formatCurrency(preview.dues.annual_leave_dues) }}</p>
                            </div>

                            <div class="flex items-center justify-between border-t pt-4 font-semibold">
                                <span>{{ t('entitlement_settlement.total_dues') }}</span>
                                <span>{{ formatCurrency(totalDues) }}</span>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle class="text-red-700">{{ t('entitlement_settlement.deductions') }}</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-1">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-sm font-medium">{{ t('entitlement_settlement.advances') }}</p>
                                    <span class="shrink-0 rounded bg-slate-100 px-2 py-0.5 text-[10px] text-slate-600">
                                        {{ t('entitlement_settlement.auto_field') }}
                                    </span>
                                </div>
                                <p class="text-lg font-semibold tabular-nums">{{ formatCurrency(preview.deductions.advances) }}</p>
                            </div>

                            <div v-for="field in manualDeductionFields" :key="field.key" class="space-y-1">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-sm font-medium">{{ field.label }}</p>
                                    <span class="shrink-0 rounded bg-amber-100 px-2 py-0.5 text-[10px] text-amber-800">
                                        {{ t('entitlement_settlement.manual_field') }}
                                    </span>
                                </div>
                                <Input
                                    v-model="form[field.key]"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="tabular-nums"
                                />
                            </div>

                            <div class="space-y-1">
                                <div class="flex items-center justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-medium">{{ t('entitlement_settlement.used_annual_leave') }}</p>
                                        <p class="text-xs text-muted-foreground">{{ usedLeaveHint }}</p>
                                    </div>
                                    <span class="shrink-0 rounded bg-slate-100 px-2 py-0.5 text-[10px] text-slate-600">
                                        {{ t('entitlement_settlement.auto_field') }}
                                    </span>
                                </div>
                                <p class="text-lg font-semibold tabular-nums">
                                    {{ formatCurrency(preview.deductions.used_annual_leave_deduction) }}
                                </p>
                            </div>

                            <div class="flex items-center justify-between border-t pt-4 font-semibold">
                                <span>{{ t('entitlement_settlement.total_deductions') }}</span>
                                <span>{{ formatCurrency(totalDeductions) }}</span>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card class="border-blue-200 bg-blue-50/40">
                    <CardContent class="flex flex-col gap-4 py-6 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-muted-foreground">{{ t('entitlement_settlement.net_due') }}</p>
                            <p class="text-3xl font-bold text-blue-700">{{ formatCurrency(netDue) }}</p>
                            <p class="mt-2 text-xs text-muted-foreground">
                                {{
                                    hasApprovalWorkflow
                                        ? t('entitlement_settlement.workflow_hint')
                                        : t('entitlement_settlement.no_workflow_hint')
                                }}
                            </p>
                        </div>
                        <Button type="submit" size="lg" :disabled="form.processing">
                            {{ form.processing ? t('common.saving') : t('entitlement_settlement.save') }}
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{{ t('entitlement_settlement.notes') }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <textarea
                            v-model="form.notes"
                            rows="3"
                            class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            :placeholder="t('entitlement_settlement.notes_placeholder')"
                        />
                    </CardContent>
                </Card>
            </form>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { BreadcrumbItem } from '@/types';

type Preview = {
    settlement_date: string;
    employee: {
        full_name: string;
        nationality?: string | null;
        department?: string | null;
        hire_date?: string | null;
        annual_leave_balance?: number;
    };
    last_settlement_date?: string | null;
    service_days: number;
    salary_breakdown: {
        basic_salary: number;
        allowance_housing: number;
        allowance_transportation: number;
        allowance_personal_car: number;
        gross_salary: number;
        gross_daily_wage?: number | null;
    };
    dues: {
        salary_dues: number;
        salary_unpaid_days: number;
        salary_unpaid_from?: string | null;
        salary_unpaid_to?: string | null;
        annual_leave_dues: number;
        remaining_leave_days: number;
    };
    deductions: {
        advances: number;
        used_annual_leave_deduction: number;
        used_annual_leave_days: number;
    };
    notes?: string | null;
};

const props = defineProps<{
    employee: { id: number; full_name: string; employee_id?: string | null };
    preview: Preview;
    defaults: { settlement_date: string; reason: string };
    previous_settlements_count?: number;
    has_approval_workflow?: boolean;
}>();

const { t, locale } = useI18n();
const isRefreshing = ref(false);
const previousSettlementsCount = computed(() => props.previous_settlements_count ?? 0);
const hasApprovalWorkflow = computed(() => props.has_approval_workflow ?? false);

const breadcrumbs = computed((): BreadcrumbItem[] => [
    { title: t('nav.dashboard'), href: '/dashboard' },
    { title: t('employees.title'), href: '/employees' },
    { title: props.employee.full_name, href: route('employees.show', props.employee.id) },
    { title: t('entitlement_settlement.title'), href: route('employees.entitlement-settlement.create', props.employee.id) },
]);

const form = useForm({
    settlement_date: props.defaults.settlement_date,
    reason: props.defaults.reason,
    end_of_service_bonus: 0,
    travel_tickets: 0,
    due_commissions: 0,
    other_dues: 0,
    custody_deduction: 0,
    excess_leave_deduction: 0,
    social_insurance_deduction: 0,
    notes: props.preview.notes ?? '',
});

const preview = computed(() => props.preview);

let previewRefreshTimer: ReturnType<typeof setTimeout> | null = null;
let isPreviewRequest = false;

watch(
    () => form.settlement_date,
    (newDate) => {
        if (!newDate || isPreviewRequest || newDate === props.preview.settlement_date) {
            return;
        }

        if (previewRefreshTimer) {
            clearTimeout(previewRefreshTimer);
        }

        previewRefreshTimer = setTimeout(() => {
            refreshPreview(newDate);
        }, 250);
    },
);

const manualDuesFields = computed(() => [
    { key: 'end_of_service_bonus' as const, label: t('entitlement_settlement.end_of_service_bonus') },
    { key: 'travel_tickets' as const, label: t('entitlement_settlement.travel_tickets') },
    { key: 'due_commissions' as const, label: t('entitlement_settlement.due_commissions') },
    { key: 'other_dues' as const, label: t('entitlement_settlement.other_dues') },
]);

const manualDeductionFields = computed(() => [
    { key: 'custody_deduction' as const, label: t('entitlement_settlement.custody') },
    { key: 'excess_leave_deduction' as const, label: t('entitlement_settlement.excess_leave') },
    { key: 'social_insurance_deduction' as const, label: t('entitlement_settlement.social_insurance') },
]);

const parseAmount = (value: unknown) => {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : 0;
};

const totalDues = computed(() =>
    roundMoney(
        parseAmount(form.end_of_service_bonus)
            + parseAmount(form.travel_tickets)
            + parseAmount(form.due_commissions)
            + preview.value.dues.salary_dues
            + preview.value.dues.annual_leave_dues
            + parseAmount(form.other_dues),
    ),
);

const totalDeductions = computed(() =>
    roundMoney(
        preview.value.deductions.advances
            + parseAmount(form.custody_deduction)
            + parseAmount(form.excess_leave_deduction)
            + parseAmount(form.social_insurance_deduction)
            + preview.value.deductions.used_annual_leave_deduction,
    ),
);

const netDue = computed(() => roundMoney(totalDues.value - totalDeductions.value));

const salaryDuesHint = computed(() => {
    const { salary_unpaid_days, salary_unpaid_from, salary_unpaid_to } = preview.value.dues;
    if (!salary_unpaid_from || !salary_unpaid_to) {
        return '';
    }

    return `${formatNumber(salary_unpaid_days)} ${t('leaves.days')} (${formatDate(salary_unpaid_from)} → ${formatDate(salary_unpaid_to)})`;
});

const annualLeaveHint = computed(
    () => `${formatNumber(preview.value.dues.remaining_leave_days)} ${t('leaves.days')}`,
);

const usedLeaveHint = computed(
    () => `${formatNumber(preview.value.deductions.used_annual_leave_days)} ${t('leaves.days')}`,
);

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

const roundMoney = (value: number) => Math.round(value * 100) / 100;

function refreshPreview(settlementDate = form.settlement_date) {
    if (!settlementDate) {
        return;
    }

    isRefreshing.value = true;
    isPreviewRequest = true;

    router.get(
        route('employees.entitlement-settlement.create', props.employee.id),
        {
            settlement_date: settlementDate,
            reason: form.reason,
            end_of_service_bonus: form.end_of_service_bonus,
            travel_tickets: form.travel_tickets,
            due_commissions: form.due_commissions,
            other_dues: form.other_dues,
            custody_deduction: form.custody_deduction,
            excess_leave_deduction: form.excess_leave_deduction,
            social_insurance_deduction: form.social_insurance_deduction,
            notes: form.notes,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onFinish: () => {
                isRefreshing.value = false;
                isPreviewRequest = false;
            },
        },
    );
}

function submit() {
    form.post(route('employees.entitlement-settlement.store', props.employee.id));
}
</script>

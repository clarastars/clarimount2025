<template>
    <Head :title="t('entitlement_settlement.detail_title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-6xl space-y-6 px-4 py-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <h1 class="text-2xl font-bold tracking-tight">{{ t('entitlement_settlement.detail_title') }}</h1>
                        <span :class="statusBadgeClass(settlement.status)">
                            {{ t(`entitlement_settlement.status_${settlement.status}`) }}
                        </span>
                    </div>
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

            <Card v-if="has_approval_workflow && approval_steps.length">
                <CardHeader>
                    <CardTitle>{{ t('entitlement_settlement.approvals_section') }}</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div
                        v-for="step in approval_steps"
                        :key="step.id"
                        class="rounded-lg border p-4 space-y-3"
                    >
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="font-medium">{{ step.title }}</p>
                                <p class="text-xs text-muted-foreground">
                                    <span v-if="step.team_name">{{ step.team_name }} · </span>
                                    <span v-if="step.approved_at">
                                        {{ step.approver_name }} — {{ formatDateTime(step.approved_at) }}
                                    </span>
                                    <span v-else-if="step.waiting_previous">
                                        {{ t('entitlement_settlement.approval_waiting_previous') }}
                                    </span>
                                    <span v-else-if="settlement.status === 'pending'">
                                        {{ t('entitlement_settlement.status_pending') }}
                                    </span>
                                </p>
                            </div>
                            <div v-if="step.can_approve || step.can_reject" class="flex flex-wrap gap-2">
                                <Button
                                    v-if="step.can_approve"
                                    size="sm"
                                    :disabled="approvalProcessing"
                                    @click="approveStep(step.id)"
                                >
                                    {{ t('entitlement_settlement.approve_step') }}
                                </Button>
                                <Button
                                    v-if="step.can_reject"
                                    size="sm"
                                    variant="destructive"
                                    :disabled="approvalProcessing"
                                    @click="openReject(step.id)"
                                >
                                    {{ t('entitlement_settlement.reject_step') }}
                                </Button>
                            </div>
                        </div>

                        <div v-if="rejectingStepId === step.id" class="space-y-2 border-t pt-3">
                            <Label>{{ t('entitlement_settlement.approval_reject_reason') }}</Label>
                            <textarea
                                v-model="rejectReason"
                                rows="3"
                                class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                :placeholder="t('entitlement_settlement.approval_reject_reason_placeholder')"
                            />
                            <div class="flex gap-2">
                                <Button
                                    size="sm"
                                    variant="destructive"
                                    :disabled="approvalProcessing || rejectReason.trim().length < 5"
                                    @click="rejectStep(step.id)"
                                >
                                    {{ t('entitlement_settlement.reject_step') }}
                                </Button>
                                <Button size="sm" variant="outline" @click="cancelReject">
                                    {{ t('common.cancel') }}
                                </Button>
                            </div>
                        </div>
                    </div>

                    <p v-if="settlement.status === 'rejected' && settlement.review_notes" class="text-sm text-red-700">
                        {{ settlement.review_notes }}
                    </p>
                </CardContent>
            </Card>

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
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import type { BreadcrumbItem } from '@/types';

type ApprovalStep = {
    id: number;
    title: string;
    sort_order: number;
    team_id?: number | null;
    team_name?: string | null;
    approved_at?: string | null;
    approver_name?: string | null;
    can_approve: boolean;
    can_reject: boolean;
    waiting_previous: boolean;
};

type SettlementDetail = {
    id: number;
    settlement_date: string | null;
    reason: string;
    status: 'pending' | 'approved' | 'rejected';
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
    review_notes?: string | null;
};

const props = defineProps<{
    employee: { id: number; full_name: string; employee_id?: string | null };
    settlement: SettlementDetail;
    has_approval_workflow?: boolean;
    approval_steps?: ApprovalStep[];
}>();

const { t, locale } = useI18n();
const approvalProcessing = ref(false);
const rejectingStepId = ref<number | null>(null);
const rejectReason = ref('');

const has_approval_workflow = computed(() => props.has_approval_workflow ?? false);
const approval_steps = computed(() => props.approval_steps ?? []);

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

const formatDateTime = (value?: string | null) => {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat(locale.value === 'ar' ? 'ar-SA' : 'en-GB', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
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

function approveStep(stepId: number) {
    approvalProcessing.value = true;
    router.post(
        route('employees.entitlement-settlement.approve-step', [
            props.employee.id,
            props.settlement.id,
            stepId,
        ]),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                approvalProcessing.value = false;
            },
        },
    );
}

function openReject(stepId: number) {
    rejectingStepId.value = stepId;
    rejectReason.value = '';
}

function cancelReject() {
    rejectingStepId.value = null;
    rejectReason.value = '';
}

function rejectStep(stepId: number) {
    approvalProcessing.value = true;
    router.post(
        route('employees.entitlement-settlement.reject-step', [
            props.employee.id,
            props.settlement.id,
            stepId,
        ]),
        { reason: rejectReason.value },
        {
            preserveScroll: true,
            onFinish: () => {
                approvalProcessing.value = false;
                cancelReject();
            },
        },
    );
}
</script>

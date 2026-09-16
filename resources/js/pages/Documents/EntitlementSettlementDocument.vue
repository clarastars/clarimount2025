<template>
    <div class="min-h-screen bg-slate-100 print:bg-white">
        <Head :title="t('entitlement_settlement.print_title')" />

        <div class="mx-auto max-w-5xl px-4 py-6 print:max-w-none print:px-0 print:py-0">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3 print:hidden">
                <div>
                    <h1 class="text-xl font-bold text-slate-900">{{ t('entitlement_settlement.print_title') }}</h1>
                    <p class="text-sm text-slate-600">
                        {{ employee.full_name }} — {{ formatDate(settlement.settlement_date) }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" as-child>
                        <a :href="route('employees.entitlement-settlement.show', [employee.id, settlement.id])">
                            {{ t('entitlement_settlement.back_to_detail') }}
                        </a>
                    </Button>
                    <Button @click="printDocument">
                        {{ t('entitlement_settlement.print') }}
                    </Button>
                </div>
            </div>

            <div class="settlement-print-sheet bg-white text-slate-900 shadow-lg print:shadow-none">
                <div class="border-b-2 border-slate-800 px-8 py-6 print:px-4 print:py-4">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-sm font-semibold tracking-wide text-slate-500">{{ company.name }}</p>
                            <h1 class="mt-1 text-2xl font-bold">{{ t('entitlement_settlement.print_heading') }}</h1>
                            <p class="mt-2 text-sm text-slate-600">
                                {{ t('entitlement_settlement.document_number') }}:
                                <span class="font-semibold tabular-nums" dir="ltr">#{{ String(settlement.id).padStart(4, '0') }}</span>
                            </p>
                        </div>
                        <div class="text-start sm:text-end">
                            <span :class="statusBadgeClass(settlement.status)">
                                {{ t(`entitlement_settlement.status_${settlement.status}`) }}
                            </span>
                            <p class="mt-3 text-xs text-slate-500">
                                {{ t('entitlement_settlement.printed_at') }}:
                                <span dir="ltr">{{ formatDateTime(generated_at) }}</span>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="space-y-6 px-8 py-6 print:px-4 print:py-4">
                    <section>
                        <h2 class="mb-3 border-b border-slate-200 pb-2 text-sm font-bold uppercase tracking-wide text-slate-700">
                            {{ t('entitlement_settlement.employee_info') }}
                        </h2>
                        <div class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm md:grid-cols-3">
                            <div>
                                <p class="text-xs text-slate-500">{{ t('entitlement_settlement.employee_name') }}</p>
                                <p class="font-medium">{{ employee.full_name }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500">{{ t('entitlement_settlement.employee_code') }}</p>
                                <p class="font-medium" dir="ltr">{{ employee.employee_id || '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500">{{ t('entitlement_settlement.department') }}</p>
                                <p class="font-medium">{{ employee.department || '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500">{{ t('entitlement_settlement.settlement_date') }}</p>
                                <p class="font-medium">{{ formatDate(settlement.settlement_date) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500">{{ t('entitlement_settlement.reason') }}</p>
                                <p class="font-medium">{{ settlement.reason || '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500">{{ t('entitlement_settlement.last_settlement_date') }}</p>
                                <p class="font-medium">{{ formatDate(settlement.last_settlement_date) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500">{{ t('entitlement_settlement.service_days') }}</p>
                                <p class="font-medium">{{ formatNumber(settlement.service_days) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500">{{ t('entitlement_settlement.gross_salary') }}</p>
                                <p class="font-medium">{{ formatCurrency(settlement.gross_salary) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500">{{ t('entitlement_settlement.created_by') }}</p>
                                <p class="font-medium">{{ settlement.created_by_name || '—' }}</p>
                            </div>
                        </div>
                    </section>

                    <section v-if="has_approval_workflow && approval_steps.length">
                        <div class="mb-3 flex flex-wrap items-end justify-between gap-2 border-b border-slate-200 pb-2">
                            <h2 class="text-sm font-bold uppercase tracking-wide text-slate-700">
                                {{ t('entitlement_settlement.approvals_section') }}
                            </h2>
                            <p class="text-xs text-slate-600">
                                {{
                                    t('entitlement_settlement.approval_progress_summary', {
                                        approved: approval_summary.approved_count,
                                        total: approval_summary.total_steps,
                                        remaining: approval_summary.remaining_count,
                                    })
                                }}
                            </p>
                        </div>

                        <p
                            v-if="approval_summary.current_step_title && settlement.status === 'pending'"
                            class="mb-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900"
                        >
                            {{ t('entitlement_settlement.approval_current_step', { step: approval_summary.current_step_title }) }}
                        </p>

                        <table class="w-full border-collapse text-sm">
                            <thead>
                                <tr class="bg-slate-50">
                                    <th class="border border-slate-300 px-2 py-2 text-start font-semibold">#</th>
                                    <th class="border border-slate-300 px-2 py-2 text-start font-semibold">{{ t('entitlement_settlement.approval_step') }}</th>
                                    <th class="border border-slate-300 px-2 py-2 text-start font-semibold">{{ t('entitlement_settlement.approval_role') }}</th>
                                    <th class="border border-slate-300 px-2 py-2 text-start font-semibold">{{ t('entitlement_settlement.status') }}</th>
                                    <th class="border border-slate-300 px-2 py-2 text-start font-semibold">{{ t('entitlement_settlement.approver') }}</th>
                                    <th class="border border-slate-300 px-2 py-2 text-start font-semibold">{{ t('entitlement_settlement.approved_at') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(step, index) in approval_steps" :key="step.id">
                                    <td class="border border-slate-300 px-2 py-2 tabular-nums">{{ index + 1 }}</td>
                                    <td class="border border-slate-300 px-2 py-2 font-medium">{{ step.title }}</td>
                                    <td class="border border-slate-300 px-2 py-2">{{ step.team_name || '—' }}</td>
                                    <td class="border border-slate-300 px-2 py-2">
                                        <span :class="stepStatusClass(stepStatus(step))">
                                            {{ stepStatusLabel(step) }}
                                        </span>
                                    </td>
                                    <td class="border border-slate-300 px-2 py-2">{{ step.approver_name || '—' }}</td>
                                    <td class="border border-slate-300 px-2 py-2">{{ formatDateTime(step.approved_at) }}</td>
                                </tr>
                            </tbody>
                        </table>

                        <p
                            v-if="settlement.status === 'rejected' && settlement.review_notes"
                            class="mt-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800"
                        >
                            <span class="font-semibold">{{ t('entitlement_settlement.approval_reject_reason') }}:</span>
                            {{ settlement.review_notes }}
                        </p>
                    </section>

                    <section v-else>
                        <h2 class="mb-2 border-b border-slate-200 pb-2 text-sm font-bold uppercase tracking-wide text-slate-700">
                            {{ t('entitlement_settlement.approvals_section') }}
                        </h2>
                        <p class="text-sm text-slate-600">{{ t('entitlement_settlement.no_workflow_print_note') }}</p>
                    </section>

                    <section class="grid gap-4 md:grid-cols-2">
                        <div>
                            <h2 class="mb-3 border-b border-emerald-200 pb-2 text-sm font-bold text-emerald-800">
                                {{ t('entitlement_settlement.dues') }}
                            </h2>
                            <table class="w-full border-collapse text-sm">
                                <tbody>
                                    <tr v-for="row in duesRows" :key="row.label">
                                        <td class="border border-slate-300 px-2 py-1.5">
                                            <span>{{ row.label }}</span>
                                            <span v-if="row.hint" class="mt-0.5 block text-xs text-slate-500">{{ row.hint }}</span>
                                        </td>
                                        <td class="border border-slate-300 px-2 py-1.5 text-end font-semibold tabular-nums">
                                            {{ formatCurrency(row.value) }}
                                        </td>
                                    </tr>
                                    <tr class="bg-emerald-50">
                                        <td class="border border-slate-300 px-2 py-2 font-bold">{{ t('entitlement_settlement.total_dues') }}</td>
                                        <td class="border border-slate-300 px-2 py-2 text-end font-bold tabular-nums">
                                            {{ formatCurrency(settlement.total_dues) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div>
                            <h2 class="mb-3 border-b border-red-200 pb-2 text-sm font-bold text-red-800">
                                {{ t('entitlement_settlement.deductions') }}
                            </h2>
                            <table class="w-full border-collapse text-sm">
                                <tbody>
                                    <tr v-for="row in deductionRows" :key="row.label">
                                        <td class="border border-slate-300 px-2 py-1.5">
                                            <span>{{ row.label }}</span>
                                            <span v-if="row.hint" class="mt-0.5 block text-xs text-slate-500">{{ row.hint }}</span>
                                        </td>
                                        <td class="border border-slate-300 px-2 py-1.5 text-end font-semibold tabular-nums">
                                            {{ formatCurrency(row.value) }}
                                        </td>
                                    </tr>
                                    <tr class="bg-red-50">
                                        <td class="border border-slate-300 px-2 py-2 font-bold">{{ t('entitlement_settlement.total_deductions') }}</td>
                                        <td class="border border-slate-300 px-2 py-2 text-end font-bold tabular-nums">
                                            {{ formatCurrency(settlement.total_deductions) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section class="rounded-lg border-2 border-blue-700 bg-blue-50 px-4 py-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="text-sm font-semibold text-blue-900">{{ t('entitlement_settlement.net_due') }}</p>
                            <p class="text-2xl font-bold tabular-nums text-blue-800">{{ formatCurrency(settlement.net_due) }}</p>
                        </div>
                        <p v-if="settlement.notes" class="mt-3 whitespace-pre-wrap border-t border-blue-200 pt-3 text-sm text-slate-700">
                            <span class="font-semibold">{{ t('entitlement_settlement.notes') }}:</span>
                            {{ settlement.notes }}
                        </p>
                    </section>

                    <section v-if="(settlement.attachments ?? []).length">
                        <h2 class="mb-2 border-b border-slate-200 pb-2 text-sm font-bold uppercase tracking-wide text-slate-700">
                            {{ t('entitlement_settlement.attachments') }}
                        </h2>
                        <ul class="list-disc space-y-1 ps-5 text-sm text-slate-700">
                            <li v-for="attachment in settlement.attachments" :key="attachment.path">
                                {{ attachment.name }}
                            </li>
                        </ul>
                    </section>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';

type ApprovalStep = {
    id: number;
    title: string;
    sort_order: number;
    team_id?: number | null;
    team_name?: string | null;
    approved_at?: string | null;
    approver_name?: string | null;
    status?: 'approved' | 'current' | 'waiting';
    waiting_previous?: boolean;
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
    penalties_deduction: number;
    used_annual_leave_deduction: number;
    total_deductions: number;
    net_due: number;
    notes?: string | null;
    created_by_name?: string | null;
    review_notes?: string | null;
    attachments?: Array<{ path: string; url: string; name: string }>;
};

const props = defineProps<{
    employee: {
        id: number;
        full_name: string;
        employee_id?: string | null;
        department?: string | null;
        job_title?: string | null;
    };
    company: { id: number; name: string };
    settlement: SettlementDetail;
    has_approval_workflow?: boolean;
    approval_steps?: ApprovalStep[];
    approval_summary: {
        total_steps: number;
        approved_count: number;
        remaining_count: number;
        current_step_title?: string | null;
    };
    generated_at: string;
}>();

const { t, locale } = useI18n();

const has_approval_workflow = computed(() => props.has_approval_workflow ?? false);
const approval_steps = computed(() => props.approval_steps ?? []);

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
    { label: t('entitlement_settlement.penalties'), value: props.settlement.penalties_deduction },
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
    const base = 'inline-flex rounded-full px-3 py-1 text-xs font-semibold';

    if (status === 'approved') {
        return `${base} bg-emerald-100 text-emerald-800`;
    }

    if (status === 'rejected') {
        return `${base} bg-red-100 text-red-800`;
    }

    return `${base} bg-amber-100 text-amber-800`;
};

const stepStatus = (step: ApprovalStep): 'approved' | 'current' | 'waiting' => {
    if (step.status === 'approved' || step.status === 'current' || step.status === 'waiting') {
        return step.status;
    }

    if (step.approved_at) {
        return 'approved';
    }

    if (step.waiting_previous) {
        return 'waiting';
    }

    return props.settlement.status === 'pending' ? 'current' : 'waiting';
};

const stepStatusLabel = (step: ApprovalStep) => {
    const status = stepStatus(step);

    if (status === 'approved') {
        return t('entitlement_settlement.step_status_approved');
    }

    if (status === 'current') {
        return t('entitlement_settlement.step_status_current');
    }

    return t('entitlement_settlement.step_status_waiting');
};

const stepStatusClass = (status: 'approved' | 'current' | 'waiting') => {
    if (status === 'approved') {
        return 'font-semibold text-emerald-700';
    }

    if (status === 'current') {
        return 'font-semibold text-amber-700';
    }

    return 'text-slate-500';
};

function printDocument() {
    window.print();
}
</script>

<style>
@media print {
    @page {
        size: A4;
        margin: 12mm;
    }

    body {
        background: white !important;
    }

    .settlement-print-sheet {
        break-inside: avoid;
    }

    table {
        break-inside: avoid;
    }
}
</style>

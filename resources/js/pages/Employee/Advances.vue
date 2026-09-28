<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { CheckCircle2, Circle, Clock, Wallet } from 'lucide-vue-next';

import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { BreadcrumbItem } from '@/types';

interface ApprovalProgressStep {
    id: number;
    title: string;
    sort_order: number;
    team_name: string | null;
    status: 'approved' | 'current' | 'waiting';
    approved_at: string | null;
    approver_name: string | null;
}

interface ApprovalProgress {
    steps: ApprovalProgressStep[];
    approved_count: number;
    total_steps: number;
    remaining_steps: number;
    current_step_title: string | null;
    latest_rejection?: {
        reason: string;
        step_title: string | null;
        rejector_name: string | null;
        rejected_at: string;
    } | null;
}

interface ScheduleRow {
    month_index: number;
    amount: number;
}

interface AdvanceRequestRow {
    id: number;
    amount: number;
    monthly_deduction: number;
    reason: string;
    months_count: number;
    repayment_schedule: ScheduleRow[];
    status: string;
    review_notes?: string | null;
    created_at?: string | null;
    reviewed_at?: string | null;
    approval_progress?: ApprovalProgress | null;
}

interface EmployeeSummary {
    id: number;
    full_name: string;
    company_name?: string | null;
    gross_monthly: number;
}

interface EntitlementSummary {
    has_hire_date: boolean;
    hire_date: string | null;
    months_of_service: number | null;
    can_request: boolean;
    block_reason: string | null;
    annual_max_amount: number;
    used_amount: number;
    remaining_amount: number;
    max_installments: number;
    period_start: string | null;
    period_end: string | null;
    tier?: {
        id: number;
        min_months: number;
        max_months: number | null;
        max_amount: number;
        max_installments: number;
    } | null;
}

interface EntitlementRule {
    id: number;
    min_months: number;
    max_months: number | null;
    max_amount: number;
    max_installments: number;
    sort_order: number;
    is_active: boolean;
}

const props = withDefaults(defineProps<{
    employee: EmployeeSummary;
    requests: AdvanceRequestRow[];
    entitlement: EntitlementSummary;
    rules?: EntitlementRule[];
    hasPendingRequest: boolean;
}>(), {
    rules: () => [],
});

const { t, locale } = useI18n();
const page = usePage();

const breadcrumbs = computed((): BreadcrumbItem[] => [
    { title: t('nav.dashboard'), href: '/dashboard' },
    { title: t('advances.my_requests_title'), href: route('employee.advances.index') },
]);

const createFormOpen = ref(false);
const cancellingRequestId = ref<number | null>(null);
const expandedApprovalRequestIds = ref<number[]>([]);

const form = useForm({
    amount: '' as string | number,
    installments: '' as string | number,
    reason: '',
});

const flashSuccess = computed(() => (page.props.flash as { success?: string } | undefined)?.success);

const selectedAmount = computed(() => Number(form.amount) || 0);
const selectedInstallments = computed(() => Number(form.installments) || 0);

const installmentOptions = computed(() => {
    const max = props.entitlement.max_installments || 0;
    return Array.from({ length: max }, (_, index) => index + 1);
});

const canOpenRequest = computed(() =>
    props.entitlement.can_request
    && props.entitlement.remaining_amount > 0
    && !props.hasPendingRequest
    && props.employee.gross_monthly > 0,
);

const currentTierId = computed(() => props.entitlement.tier?.id ?? null);

const tenureLabel = (rule: EntitlementRule): string => {
    if (rule.max_months === null) {
        return t('settings.advance_entitlement_tenure_open', { min: rule.min_months });
    }

    return t('settings.advance_entitlement_tenure_range', {
        min: rule.min_months,
        max: rule.max_months,
    });
};

const isCurrentRule = (rule: EntitlementRule): boolean =>
    currentTierId.value !== null && rule.id === currentTierId.value;

const blockMessage = computed(() => {
    if (!props.entitlement.has_hire_date) {
        return t('advances.missing_hire_date');
    }

    if (props.hasPendingRequest) {
        return t('advances.pending_request_exists');
    }

    if (props.entitlement.block_reason === 'no_matching_tier') {
        return t('advances.no_matching_tier');
    }

    if (props.entitlement.block_reason === 'no_remaining_entitlement') {
        return t('advances.no_remaining_entitlement');
    }

    if (props.employee.gross_monthly <= 0) {
        return t('advances.no_amounts_available');
    }

    return null;
});

const formatCurrency = (amount: number) => `${Number(amount).toLocaleString(locale.value === 'ar' ? 'ar-SA' : 'en-GB', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
})} SAR`;

const previewPlan = computed((): { months: number; schedule: ScheduleRow[]; monthly: number } | null => {
    const amount = selectedAmount.value;
    const installments = selectedInstallments.value;

    if (amount <= 0 || installments < 1) {
        return null;
    }

    const baseMonthly = Math.round((amount / installments) * 100) / 100;
    const schedule: ScheduleRow[] = [];
    let allocated = 0;

    for (let index = 1; index <= installments; index += 1) {
        const installment = index === installments
            ? Math.round((amount - allocated) * 100) / 100
            : baseMonthly;

        if (installment <= 0) {
            continue;
        }

        if (index < installments) {
            allocated = Math.round((allocated + installment) * 100) / 100;
        }

        schedule.push({ month_index: schedule.length + 1, amount: installment });
    }

    if (schedule.length === 0) {
        return null;
    }

    return {
        months: schedule.length,
        schedule,
        monthly: schedule[0].amount,
    };
});

const statusLabel = (status: string) => {
    const key = `advances.request_status_${status}`;
    const translated = t(key);
    return translated === key ? status : translated;
};

const statusVariant = (status: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
    if (status === 'approved') return 'default';
    if (status === 'rejected') return 'destructive';
    if (status === 'cancelled') return 'outline';
    return 'secondary';
};

const isApprovalDetailsOpen = (requestId: number): boolean =>
    expandedApprovalRequestIds.value.includes(requestId);

const toggleApprovalDetails = (requestId: number) => {
    if (isApprovalDetailsOpen(requestId)) {
        expandedApprovalRequestIds.value = expandedApprovalRequestIds.value.filter((id) => id !== requestId);
        return;
    }

    expandedApprovalRequestIds.value = [...expandedApprovalRequestIds.value, requestId];
};

function openCreateForm() {
    createFormOpen.value = true;
}

function closeCreateForm() {
    createFormOpen.value = false;
    form.reset();
    form.clearErrors();
}

const submit = () => {
    form.post(route('employee.advances.store'), {
        onSuccess: () => closeCreateForm(),
    });
};

const cancelRequest = (requestId: number) => {
    if (!window.confirm(t('advances.cancel_request_confirm'))) {
        return;
    }

    cancellingRequestId.value = requestId;
    router.delete(route('employee.advances.destroy', requestId), {
        preserveScroll: true,
        onFinish: () => {
            cancellingRequestId.value = null;
        },
    });
};

const formatShortDate = (iso: string | null | undefined): string => {
    if (!iso) {
        return '';
    }

    try {
        return new Date(iso).toLocaleDateString(locale.value === 'ar' ? 'ar-SA' : 'en-GB', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        });
    } catch {
        return iso;
    }
};

const formatDateOnly = (value: string | null | undefined): string => {
    if (!value) {
        return '—';
    }

    try {
        return new Date(`${value}T00:00:00`).toLocaleDateString(locale.value === 'ar' ? 'ar-SA' : 'en-GB', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        });
    } catch {
        return value;
    }
};

const stepStatusLabel = (step: ApprovalProgressStep): string => {
    if (step.status === 'approved' && step.approver_name) {
        return t('leaves.approval_step_approved_by', { name: step.approver_name });
    }

    if (step.status === 'current') {
        return t('leaves.approval_step_pending');
    }

    return t('leaves.approval_step_waiting');
};
</script>

<template>
    <Head :title="t('advances.my_requests_title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 py-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold flex items-center gap-2">
                        <Wallet class="h-5 w-5 text-amber-600" />
                        {{ t('advances.my_requests_title') }}
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ employee.full_name }}
                        <span v-if="employee.company_name"> — {{ employee.company_name }}</span>
                    </p>
                </div>
                <Button :disabled="!canOpenRequest" @click="openCreateForm">
                    <Wallet class="mr-2 h-4 w-4" />
                    {{ t('advances.request_new') }}
                </Button>
            </div>

            <div v-if="flashSuccess" class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                {{ flashSuccess }}
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('advances.entitlement_title') }}</CardTitle>
                    <CardDescription>{{ t('advances.entitlement_description') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="!entitlement.has_hire_date" class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        {{ t('advances.missing_hire_date') }}
                    </div>
                    <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 text-sm">
                        <div>
                            <p class="text-muted-foreground">{{ t('advances.hire_date') }}</p>
                            <p class="font-medium">{{ formatDateOnly(entitlement.hire_date) }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">{{ t('advances.months_of_service') }}</p>
                            <p class="font-medium">{{ entitlement.months_of_service ?? 0 }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">{{ t('advances.entitlement_period') }}</p>
                            <p class="font-medium">
                                {{ formatDateOnly(entitlement.period_start) }} — {{ formatDateOnly(entitlement.period_end) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">{{ t('advances.annual_max_amount') }}</p>
                            <p class="font-medium">{{ formatCurrency(entitlement.annual_max_amount) }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">{{ t('advances.used_amount') }}</p>
                            <p class="font-medium">{{ formatCurrency(entitlement.used_amount) }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">{{ t('advances.remaining_amount') }}</p>
                            <p class="font-medium">{{ formatCurrency(entitlement.remaining_amount) }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">{{ t('advances.max_installments') }}</p>
                            <p class="font-medium">{{ entitlement.max_installments || '—' }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('advances.rules_title') }}</CardTitle>
                    <CardDescription>{{ t('advances.rules_description') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <p v-if="rules.length === 0" class="text-sm text-muted-foreground">
                        {{ t('advances.rules_empty') }}
                    </p>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-muted-foreground">
                                    <th class="py-2 px-2 text-start font-medium">{{ t('settings.advance_entitlement_tenure') }}</th>
                                    <th class="py-2 px-2 text-start font-medium">{{ t('settings.advance_entitlement_max_amount') }}</th>
                                    <th class="py-2 px-2 text-start font-medium">{{ t('settings.advance_entitlement_max_installments') }}</th>
                                    <th class="py-2 px-2 text-start font-medium">{{ t('common.status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="rule in rules"
                                    :key="rule.id"
                                    class="border-b last:border-0"
                                    :class="isCurrentRule(rule) ? 'bg-amber-50/80' : ''"
                                >
                                    <td class="py-3 px-2 font-medium">
                                        {{ tenureLabel(rule) }}
                                    </td>
                                    <td class="py-3 px-2">{{ formatCurrency(rule.max_amount) }}</td>
                                    <td class="py-3 px-2">{{ rule.max_installments }}</td>
                                    <td class="py-3 px-2">
                                        <Badge v-if="isCurrentRule(rule)" variant="default">
                                            {{ t('advances.rules_current_tier') }}
                                        </Badge>
                                        <span v-else class="text-muted-foreground">—</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-3 text-xs text-muted-foreground">
                        {{ t('advances.rules_reset_note') }}
                    </p>
                </CardContent>
            </Card>

            <p v-if="blockMessage" class="rounded-md border px-4 py-3 text-sm text-muted-foreground">
                {{ blockMessage }}
            </p>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('advances.my_requests') }}</CardTitle>
                    <CardDescription>{{ t('advances.my_requests_description') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <p v-if="requests.length === 0" class="text-sm text-muted-foreground py-6 text-center">
                        {{ t('advances.no_requests_yet') }}
                    </p>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-muted-foreground">
                                    <th class="py-3 px-2 text-start font-medium">{{ t('advances.amount') }}</th>
                                    <th class="py-3 px-2 text-start font-medium">{{ t('advances.monthly_deduction') }}</th>
                                    <th class="py-3 px-2 text-start font-medium">{{ t('advances.months_count') }}</th>
                                    <th class="py-3 px-2 text-start font-medium">{{ t('advances.reason') }}</th>
                                    <th class="py-3 px-2 text-start font-medium">{{ t('advances.request_status') }}</th>
                                    <th class="py-3 px-2 text-start font-medium">{{ t('common.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="request in requests" :key="request.id">
                                    <tr class="border-b last:border-0 align-top">
                                        <td class="py-3 px-2">{{ formatCurrency(request.amount) }}</td>
                                        <td class="py-3 px-2">{{ formatCurrency(request.monthly_deduction) }}</td>
                                        <td class="py-3 px-2">{{ request.months_count }}</td>
                                        <td class="py-3 px-2 max-w-[220px]">
                                            <span class="line-clamp-2">{{ request.reason }}</span>
                                        </td>
                                        <td class="py-3 px-2">
                                            <Badge :variant="statusVariant(request.status)">{{ statusLabel(request.status) }}</Badge>
                                        </td>
                                        <td class="py-3 px-2">
                                            <div class="flex flex-wrap gap-2">
                                                <Button
                                                    v-if="request.approval_progress && request.approval_progress.total_steps > 0"
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    @click="toggleApprovalDetails(request.id)"
                                                >
                                                    {{ isApprovalDetailsOpen(request.id) ? t('common.close') : t('advances.request_details') }}
                                                </Button>
                                                <Button
                                                    v-if="request.status === 'pending'"
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    :disabled="cancellingRequestId === request.id"
                                                    @click="cancelRequest(request.id)"
                                                >
                                                    {{ t('advances.cancel_request') }}
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="isApprovalDetailsOpen(request.id) && request.approval_progress" :key="`${request.id}-details`">
                                        <td colspan="6" class="bg-muted/30 px-4 py-4">
                                            <div class="space-y-4">
                                                <div v-if="request.repayment_schedule?.length" class="rounded-md border bg-background px-3 py-3 space-y-2">
                                                    <p class="text-sm font-medium mb-2">{{ t('advances.repayment_plan') }}</p>
                                                    <p class="text-sm text-muted-foreground">
                                                        {{ t('advances.repayment_plan_summary', {
                                                            amount: formatCurrency(request.amount),
                                                            months: request.months_count,
                                                            monthly: formatCurrency(request.monthly_deduction),
                                                        }) }}
                                                    </p>
                                                    <ul class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                                        <li
                                                            v-for="row in request.repayment_schedule"
                                                            :key="`req-${request.id}-${row.month_index}`"
                                                            class="rounded-md border px-3 py-2 text-xs"
                                                        >
                                                            {{ t('advances.repayment_month', { index: row.month_index }) }}
                                                            <span class="block font-medium">{{ formatCurrency(row.amount) }}</span>
                                                        </li>
                                                    </ul>
                                                </div>

                                                <div v-if="request.approval_progress">
                                                    <ol class="space-y-3">
                                                        <li
                                                            v-for="(step, index) in request.approval_progress.steps"
                                                            :key="step.id"
                                                            class="flex gap-3"
                                                        >
                                                            <div class="mt-0.5">
                                                                <CheckCircle2 v-if="step.status === 'approved'" class="h-4 w-4 text-green-600" />
                                                                <Clock v-else-if="step.status === 'current'" class="h-4 w-4 text-amber-600" />
                                                                <Circle v-else class="h-4 w-4 text-muted-foreground" />
                                                            </div>
                                                            <div>
                                                                <div class="flex flex-wrap items-center gap-1 text-sm">
                                                                    <span class="font-medium">
                                                                        {{ index + 1 }}. {{ step.title }}
                                                                    </span>
                                                                    <span v-if="step.team_name" class="text-xs text-muted-foreground">
                                                                        ({{ step.team_name }})
                                                                    </span>
                                                                </div>
                                                                <p class="text-xs text-muted-foreground mt-0.5">
                                                                    {{ stepStatusLabel(step) }}
                                                                    <span v-if="step.approved_at">
                                                                        — {{ formatShortDate(step.approved_at) }}
                                                                    </span>
                                                                </p>
                                                            </div>
                                                        </li>
                                                    </ol>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <Dialog :open="createFormOpen" @update:open="(open) => (open ? openCreateForm() : closeCreateForm())">
                <DialogContent class="max-w-lg max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>{{ t('advances.request_new') }}</DialogTitle>
                        <DialogDescription>
                            {{ t('advances.request_description_entitlement', {
                                max: formatCurrency(entitlement.remaining_amount),
                                installments: entitlement.max_installments,
                            }) }}
                        </DialogDescription>
                    </DialogHeader>

                    <form class="space-y-4" @submit.prevent="submit">
                        <div class="space-y-2">
                            <Label for="amount">{{ t('advances.amount') }}</Label>
                            <Input
                                id="amount"
                                v-model="form.amount"
                                type="number"
                                min="1"
                                step="0.01"
                                :max="entitlement.remaining_amount"
                                required
                            />
                            <p class="text-xs text-muted-foreground">
                                {{ t('advances.amount_max_hint', { max: formatCurrency(entitlement.remaining_amount) }) }}
                            </p>
                            <p v-if="form.errors.amount" class="text-sm text-red-600">{{ form.errors.amount }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="installments">{{ t('advances.installments') }}</Label>
                            <select
                                id="installments"
                                v-model="form.installments"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                required
                            >
                                <option value="" disabled>{{ t('advances.select_installments') }}</option>
                                <option v-for="option in installmentOptions" :key="`installment-${option}`" :value="option">
                                    {{ option }}
                                </option>
                            </select>
                            <p v-if="form.errors.installments" class="text-sm text-red-600">{{ form.errors.installments }}</p>
                        </div>

                        <div v-if="previewPlan" class="rounded-md border bg-muted/40 px-3 py-3 space-y-2">
                            <p class="text-sm font-medium">{{ t('advances.repayment_plan') }}</p>
                            <p class="text-sm text-muted-foreground">
                                {{ t('advances.repayment_plan_summary', {
                                    amount: formatCurrency(selectedAmount),
                                    months: previewPlan.months,
                                    monthly: formatCurrency(previewPlan.monthly),
                                }) }}
                            </p>
                            <ul class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                <li
                                    v-for="row in previewPlan.schedule"
                                    :key="`preview-${row.month_index}`"
                                    class="rounded-md border bg-background px-3 py-2 text-xs"
                                >
                                    <span class="text-muted-foreground">
                                        {{ t('advances.repayment_month', { index: row.month_index }) }}
                                    </span>
                                    <span class="block font-medium">{{ formatCurrency(row.amount) }}</span>
                                </li>
                            </ul>
                        </div>

                        <div class="space-y-2">
                            <Label for="reason">{{ t('advances.reason') }}</Label>
                            <textarea
                                id="reason"
                                v-model="form.reason"
                                rows="3"
                                required
                                :placeholder="t('advances.reason_placeholder')"
                                class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            />
                            <p v-if="form.errors.reason" class="text-sm text-red-600">{{ form.errors.reason }}</p>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" @click="closeCreateForm">
                                {{ t('common.cancel') }}
                            </Button>
                            <Button type="submit" :disabled="form.processing || !previewPlan">
                                {{ form.processing ? t('common.saving') : t('advances.submit_request') }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>

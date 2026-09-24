<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { CheckCircle2, Circle, Clock, Wallet } from 'lucide-vue-next';

import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
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

const props = defineProps<{
    employee: EmployeeSummary;
    requests: AdvanceRequestRow[];
    amountOptions: number[];
    monthlyDeductionOptions?: number[];
    hasPendingRequest: boolean;
}>();

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
    monthly_deduction: '' as string | number,
    reason: '',
});

const flashSuccess = computed(() => (page.props.flash as { success?: string } | undefined)?.success);

const amountOptions = computed(() => props.amountOptions ?? []);

const selectedAmount = computed(() => Number(form.amount) || 0);

const monthlyOptions = computed(() => {
    const cappedByGross = props.monthlyDeductionOptions ?? amountOptions.value;

    return cappedByGross.filter((option) => selectedAmount.value === 0 || option <= selectedAmount.value);
});

watch(selectedAmount, (amount) => {
    if (amount > 0 && Number(form.monthly_deduction) > amount) {
        form.monthly_deduction = '';
    }
});

const formatCurrency = (amount: number) => `${Number(amount).toLocaleString(locale.value === 'ar' ? 'ar-SA' : 'en-GB', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
})} SAR`;

const previewPlan = computed((): { months: number; schedule: ScheduleRow[] } | null => {
    const amount = selectedAmount.value;
    const monthly = Number(form.monthly_deduction) || 0;

    if (amount <= 0 || monthly <= 0 || monthly > amount) {
        return null;
    }

    const schedule: ScheduleRow[] = [];
    let remaining = amount;
    let index = 0;

    while (remaining > 0.001 && index < 240) {
        index += 1;
        const installment = Math.min(monthly, remaining);
        schedule.push({ month_index: index, amount: Math.round(installment * 100) / 100 });
        remaining = Math.round((remaining - installment) * 100) / 100;
    }

    return { months: schedule.length, schedule };
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
                <Button :disabled="amountOptions.length === 0 || monthlyOptions.length === 0 || hasPendingRequest" @click="openCreateForm">
                    <Wallet class="mr-2 h-4 w-4" />
                    {{ t('advances.request_new') }}
                </Button>
            </div>

            <div v-if="flashSuccess" class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                {{ flashSuccess }}
            </div>

            <p v-if="monthlyOptions.length === 0" class="rounded-md border px-4 py-3 text-sm text-muted-foreground">
                {{ t('advances.no_amounts_available') }}
            </p>
            <p v-else-if="hasPendingRequest" class="rounded-md border px-4 py-3 text-sm text-muted-foreground">
                {{ t('advances.pending_request_exists') }}
            </p>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('advances.my_requests') }}</CardTitle>
                    <CardDescription>{{ t('advances.my_requests_description') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="requests.length === 0" class="text-sm text-muted-foreground py-6 text-center">
                        {{ t('advances.no_requests_yet') }}
                    </div>
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
                                    <tr class="border-b last:border-0">
                                        <td class="py-3 px-2 font-medium">{{ formatCurrency(request.amount) }}</td>
                                        <td class="py-3 px-2">{{ formatCurrency(request.monthly_deduction) }}</td>
                                        <td class="py-3 px-2">{{ request.months_count }}</td>
                                        <td class="py-3 px-2 max-w-xs whitespace-pre-wrap">{{ request.reason }}</td>
                                        <td class="py-3 px-2">
                                            <Badge :variant="statusVariant(request.status)">
                                                {{ statusLabel(request.status) }}
                                            </Badge>
                                            <p v-if="request.review_notes" class="text-xs text-muted-foreground mt-1">{{ request.review_notes }}</p>
                                            <p
                                                v-if="request.approval_progress"
                                                class="text-xs text-muted-foreground mt-1.5"
                                            >
                                                {{ t('leaves.approval_progress_summary', {
                                                    approved: request.approval_progress.approved_count,
                                                    total: request.approval_progress.total_steps,
                                                    remaining: request.approval_progress.remaining_steps,
                                                }) }}
                                            </p>
                                        </td>
                                        <td class="py-3 px-2">
                                            <div class="flex flex-wrap gap-2">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    @click="toggleApprovalDetails(request.id)"
                                                >
                                                    {{ isApprovalDetailsOpen(request.id) ? t('common.close') : t('advances.request_details') }}
                                                </Button>
                                                <Button
                                                    v-if="request.status === 'pending'"
                                                    size="sm"
                                                    variant="outline"
                                                    class="text-destructive hover:text-destructive"
                                                    :disabled="cancellingRequestId === request.id"
                                                    @click="cancelRequest(request.id)"
                                                >
                                                    {{ t('advances.cancel_request') }}
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr
                                        v-if="isApprovalDetailsOpen(request.id)"
                                        class="border-b last:border-0 bg-muted/20"
                                    >
                                        <td colspan="6" class="px-2 pb-4 pt-1">
                                            <div class="rounded-lg border bg-background p-3 space-y-4">
                                                <div>
                                                    <p class="text-sm font-medium mb-2">{{ t('advances.repayment_plan') }}</p>
                                                    <p class="text-xs text-muted-foreground mb-2">
                                                        {{ t('advances.repayment_plan_summary', {
                                                            amount: formatCurrency(request.amount),
                                                            months: request.months_count,
                                                            monthly: formatCurrency(request.monthly_deduction),
                                                        }) }}
                                                    </p>
                                                    <ul class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                                        <li
                                                            v-for="row in request.repayment_schedule"
                                                            :key="row.month_index"
                                                            class="rounded-md border px-3 py-2 text-xs"
                                                        >
                                                            <span class="text-muted-foreground">
                                                                {{ t('advances.repayment_month', { index: row.month_index }) }}
                                                            </span>
                                                            <span class="block font-medium">{{ formatCurrency(row.amount) }}</span>
                                                        </li>
                                                    </ul>
                                                </div>

                                                <div v-if="request.approval_progress" class="space-y-3 border-t pt-3">
                                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                                        <p class="text-sm font-medium">{{ t('leaves.approval_workflow_title') }}</p>
                                                        <p
                                                            v-if="request.approval_progress.current_step_title"
                                                            class="text-xs text-amber-700 dark:text-amber-400"
                                                        >
                                                            {{ t('leaves.approval_progress_current_step', {
                                                                step: request.approval_progress.current_step_title,
                                                            }) }}
                                                        </p>
                                                    </div>

                                                    <p
                                                        v-if="request.approval_progress.latest_rejection"
                                                        class="text-xs text-red-700 dark:text-red-400 rounded-md border border-red-200 bg-red-50/80 px-3 py-2 dark:border-red-900 dark:bg-red-950/30"
                                                    >
                                                        {{ t('leaves.approval_rejection_short', {
                                                            step: request.approval_progress.latest_rejection.step_title ?? '—',
                                                            reason: request.approval_progress.latest_rejection.reason,
                                                        }) }}
                                                    </p>

                                                    <ol class="space-y-2">
                                                        <li
                                                            v-for="(step, index) in request.approval_progress.steps"
                                                            :key="step.id"
                                                            class="flex items-start gap-2 text-sm"
                                                        >
                                                            <CheckCircle2
                                                                v-if="step.status === 'approved'"
                                                                class="h-4 w-4 shrink-0 text-green-600 mt-0.5"
                                                            />
                                                            <Clock
                                                                v-else-if="step.status === 'current'"
                                                                class="h-4 w-4 shrink-0 text-amber-600 mt-0.5"
                                                            />
                                                            <Circle
                                                                v-else
                                                                class="h-4 w-4 shrink-0 text-muted-foreground mt-0.5"
                                                            />

                                                            <div class="min-w-0 flex-1">
                                                                <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                                                    <span
                                                                        class="font-medium"
                                                                        :class="{
                                                                            'text-green-700 dark:text-green-400': step.status === 'approved',
                                                                            'text-amber-700 dark:text-amber-400': step.status === 'current',
                                                                            'text-muted-foreground': step.status === 'waiting',
                                                                        }"
                                                                    >
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

            <Dialog :open="createFormOpen" @update:open="(open: boolean) => (open ? openCreateForm() : closeCreateForm())">
                <DialogContent class="max-w-lg max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>{{ t('advances.request_new') }}</DialogTitle>
                        <DialogDescription>{{ t('advances.request_description') }}</DialogDescription>
                    </DialogHeader>

                    <form class="space-y-4" @submit.prevent="submit">
                        <div class="space-y-2">
                            <Label for="amount">{{ t('advances.amount') }}</Label>
                            <select
                                id="amount"
                                v-model="form.amount"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                required
                            >
                                <option value="" disabled>{{ t('advances.select_amount') }}</option>
                                <option v-for="option in amountOptions" :key="`amount-${option}`" :value="option">
                                    {{ formatCurrency(option) }}
                                </option>
                            </select>
                            <p v-if="form.errors.amount" class="text-sm text-red-600">{{ form.errors.amount }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="monthly_deduction">{{ t('advances.monthly_deduction') }}</Label>
                            <select
                                id="monthly_deduction"
                                v-model="form.monthly_deduction"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                required
                            >
                                <option value="" disabled>{{ t('advances.select_monthly_deduction') }}</option>
                                <option v-for="option in monthlyOptions" :key="`monthly-${option}`" :value="option">
                                    {{ formatCurrency(option) }}
                                </option>
                            </select>
                            <p v-if="form.errors.monthly_deduction" class="text-sm text-red-600">{{ form.errors.monthly_deduction }}</p>
                        </div>

                        <div v-if="previewPlan" class="rounded-md border bg-muted/40 px-3 py-3 space-y-2">
                            <p class="text-sm font-medium">{{ t('advances.repayment_plan') }}</p>
                            <p class="text-sm text-muted-foreground">
                                {{ t('advances.repayment_plan_summary', {
                                    amount: formatCurrency(selectedAmount),
                                    months: previewPlan.months,
                                    monthly: formatCurrency(Number(form.monthly_deduction)),
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

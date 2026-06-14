<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { CalendarDays, CalendarPlus, CheckCircle2, Circle, Clock } from 'lucide-vue-next';

import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import LeaveFormFields from '@/components/leaves/LeaveFormFields.vue';
import type { BreadcrumbItem } from '@/types';

interface LeaveRow {
    id: number;
    leave_type: string;
    start_date: string;
    end_date: string;
    days: number;
    deduct_from_balance: boolean;
    is_paid: boolean;
    notes?: string | null;
}

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

interface LeaveRequestRow extends LeaveRow {
    status: string;
    review_notes?: string | null;
    created_at?: string | null;
    reviewed_at?: string | null;
    approval_progress?: ApprovalProgress | null;
}

interface EmployeeSummary {
    id: number;
    full_name: string;
    annual_leave_balance: number | string | null;
    leave_accrued_balance: number | string | null;
    remaining_annual_leave_balance: number | string | null;
    monthly_leave_accrual: number;
    company_name?: string | null;
}

const props = defineProps<{
    employee: EmployeeSummary;
    approvedLeaves: LeaveRow[];
    leaveRequests: LeaveRequestRow[];
    leaveTypes: string[];
}>();

const { t, locale } = useI18n();
const page = usePage();

const breadcrumbs = computed((): BreadcrumbItem[] => [
    { title: t('nav.dashboard'), href: '/dashboard' },
    { title: t('leaves.my_leaves'), href: route('employee.leaves.index') },
]);

const createFormOpen = ref(false);

const form = useForm({
    leave_type: '',
    start_date: '',
    end_date: '',
    deduct_from_balance: false,
    is_paid: true,
    notes: '',
    attachment: null as File | null,
});

const leaveTypeLabel = (type: string) => {
    const key = `leaves.type_${type}`;
    const translated = t(key);
    return translated === key ? type : translated;
};

const statusLabel = (status: string) => {
    const key = `leaves.request_status_${status}`;
    const translated = t(key);
    return translated === key ? status : translated;
};

const statusVariant = (status: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
    if (status === 'approved') return 'default';
    if (status === 'rejected') return 'destructive';
    if (status === 'cancelled') return 'outline';
    return 'secondary';
};

const cancellingRequestId = ref<number | null>(null);
const expandedApprovalRequestIds = ref<number[]>([]);

const isApprovalDetailsOpen = (requestId: number): boolean =>
    expandedApprovalRequestIds.value.includes(requestId);

const toggleApprovalDetails = (requestId: number) => {
    if (isApprovalDetailsOpen(requestId)) {
        expandedApprovalRequestIds.value = expandedApprovalRequestIds.value.filter((id) => id !== requestId);
        return;
    }

    expandedApprovalRequestIds.value = [...expandedApprovalRequestIds.value, requestId];
};

const cancelRequest = (requestId: number) => {
    if (!window.confirm(t('leaves.cancel_request_confirm'))) {
        return;
    }

    cancellingRequestId.value = requestId;
    router.delete(route('employee.leaves.destroy', requestId), {
        preserveScroll: true,
        onFinish: () => {
            cancellingRequestId.value = null;
        },
    });
};

const displayValue = (value: unknown) => (value === null || value === undefined || value === '' ? '—' : value);

function openCreateForm() {
    createFormOpen.value = true;
}

function closeCreateForm() {
    createFormOpen.value = false;
    form.reset();
    form.clearErrors();
}

function onAttachmentChange(file: File | null) {
    form.attachment = file;
}

const submit = () => {
    form.post(route('employee.leaves.store'), {
        forceFormData: true,
        onSuccess: () => closeCreateForm(),
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
    <Head :title="t('leaves.my_leaves')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 py-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold flex items-center gap-2">
                        <CalendarDays class="h-5 w-5 text-amber-600" />
                        {{ t('leaves.my_leaves') }}
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ employee.full_name }}
                        <span v-if="employee.company_name"> — {{ employee.company_name }}</span>
                    </p>
                </div>
                <Button @click="openCreateForm">
                    <CalendarPlus class="mr-2 h-4 w-4" />
                    {{ t('leaves.request_leave') }}
                </Button>
            </div>

            <div v-if="(page.props.flash as { success?: string })?.success" class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                {{ (page.props.flash as { success?: string }).success }}
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>{{ t('leaves.annual_leave_balance') }}</CardDescription>
                        <CardTitle class="text-2xl">{{ displayValue(employee.annual_leave_balance) }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-xs text-muted-foreground">
                            {{ t('leaves.monthly_leave_accrual') }}: {{ employee.monthly_leave_accrual.toFixed(2) }} {{ t('leaves.days') }}
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>{{ t('leaves.accrued_leave_balance') }}</CardDescription>
                        <CardTitle class="text-2xl">{{ displayValue(employee.leave_accrued_balance) }}</CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>{{ t('leaves.remaining_balance') }}</CardDescription>
                        <CardTitle class="text-2xl">{{ displayValue(employee.remaining_annual_leave_balance) }}</CardTitle>
                    </CardHeader>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('leaves.my_requests') }}</CardTitle>
                    <CardDescription>{{ t('leaves.my_requests_description') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="leaveRequests.length === 0" class="text-sm text-muted-foreground py-6 text-center">
                        {{ t('leaves.no_requests_yet') }}
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-muted-foreground">
                                    <th class="py-3 px-2 text-start font-medium">{{ t('leaves.leave_type') }}</th>
                                    <th class="py-3 px-2 text-start font-medium">{{ t('leaves.start_date') }}</th>
                                    <th class="py-3 px-2 text-start font-medium">{{ t('leaves.end_date') }}</th>
                                    <th class="py-3 px-2 text-start font-medium">{{ t('leaves.days') }}</th>
                                    <th class="py-3 px-2 text-start font-medium">{{ t('leaves.request_status') }}</th>
                                    <th class="py-3 px-2 text-start font-medium">{{ t('leaves.notes') }}</th>
                                    <th class="py-3 px-2 text-start font-medium">{{ t('common.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="request in leaveRequests" :key="request.id">
                                    <tr class="border-b last:border-0">
                                        <td class="py-3 px-2">{{ leaveTypeLabel(request.leave_type) }}</td>
                                        <td class="py-3 px-2">{{ request.start_date }}</td>
                                        <td class="py-3 px-2">{{ request.end_date }}</td>
                                        <td class="py-3 px-2">{{ request.days }}</td>
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
                                        <td class="py-3 px-2">{{ displayValue(request.notes) }}</td>
                                        <td class="py-3 px-2">
                                            <div class="flex flex-wrap gap-2">
                                                <Button
                                                    v-if="request.approval_progress"
                                                    size="sm"
                                                    variant="outline"
                                                    @click="toggleApprovalDetails(request.id)"
                                                >
                                                    {{ isApprovalDetailsOpen(request.id) ? t('common.close') : t('leaves.request_details') }}
                                                </Button>
                                                <Button
                                                    v-if="request.status === 'pending'"
                                                    size="sm"
                                                    variant="outline"
                                                    class="text-destructive hover:text-destructive"
                                                    :disabled="cancellingRequestId === request.id"
                                                    @click="cancelRequest(request.id)"
                                                >
                                                    {{ t('leaves.cancel_request') }}
                                                </Button>
                                                <span
                                                    v-if="!request.approval_progress && request.status !== 'pending'"
                                                    class="text-muted-foreground"
                                                >—</span>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr
                                        v-if="request.approval_progress && isApprovalDetailsOpen(request.id)"
                                        class="border-b last:border-0 bg-muted/20"
                                    >
                                        <td colspan="7" class="px-2 pb-4 pt-1">
                                            <div class="rounded-lg border bg-background p-3 space-y-3">
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
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('leaves.leave_history') }}</CardTitle>
                    <CardDescription>{{ t('leaves.approved_leaves_description') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="approvedLeaves.length === 0" class="text-sm text-muted-foreground py-6 text-center">
                        {{ t('leaves.no_leaves_yet') }}
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-muted-foreground">
                                    <th class="py-3 px-2 text-start font-medium">{{ t('leaves.leave_type') }}</th>
                                    <th class="py-3 px-2 text-start font-medium">{{ t('leaves.start_date') }}</th>
                                    <th class="py-3 px-2 text-start font-medium">{{ t('leaves.end_date') }}</th>
                                    <th class="py-3 px-2 text-start font-medium">{{ t('leaves.days') }}</th>
                                    <th class="py-3 px-2 text-start font-medium">{{ t('leaves.paid_leave_label') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="leave in approvedLeaves" :key="leave.id" class="border-b last:border-0">
                                    <td class="py-3 px-2">{{ leaveTypeLabel(leave.leave_type) }}</td>
                                    <td class="py-3 px-2">{{ leave.start_date }}</td>
                                    <td class="py-3 px-2">{{ leave.end_date }}</td>
                                    <td class="py-3 px-2">{{ leave.days }}</td>
                                    <td class="py-3 px-2">
                                        <Badge :variant="leave.is_paid ? 'default' : 'secondary'">
                                            {{ leave.is_paid ? t('leaves.paid_yes') : t('leaves.paid_no') }}
                                        </Badge>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <Dialog :open="createFormOpen" @update:open="(open: boolean) => (open ? openCreateForm() : closeCreateForm())">
                <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>{{ t('leaves.request_leave') }}</DialogTitle>
                        <DialogDescription>{{ t('leaves.request_leave_description') }}</DialogDescription>
                    </DialogHeader>

                    <form @submit.prevent="submit" class="space-y-6">
                        <LeaveFormFields :form="form" @attachment-change="onAttachmentChange" />

                        <DialogFooter>
                            <Button type="button" variant="outline" @click="closeCreateForm">
                                {{ t('common.cancel') }}
                            </Button>
                            <Button type="submit" :disabled="form.processing">
                                <span v-if="form.processing">{{ t('common.saving') }}</span>
                                <span v-else>{{ t('leaves.submit_request') }}</span>
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>

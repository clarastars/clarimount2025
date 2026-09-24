<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Wallet } from 'lucide-vue-next';

import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { showFlashFeedback } from '@/lib/flashFeedback';
import type { BreadcrumbItem } from '@/types';

interface ApprovalStepState {
    id: number;
    title: string;
    sort_order: number;
    team_id: number | null;
    team_name: string | null;
    approved_at: string | null;
    approver_name: string | null;
    can_approve: boolean;
    can_reject: boolean;
    waiting_previous: boolean;
    is_final_step?: boolean;
}

interface LatestRejectionState {
    id: number;
    reason: string;
    rejected_at: string;
    rejector_name: string | null;
    step_title: string | null;
    cleared_approvals_count: number;
}

interface ScheduleRow {
    month_index: number;
    amount: number;
}

interface AdvanceRequestItem {
    id: number;
    amount: number;
    monthly_deduction: number;
    reason: string;
    months_count: number;
    repayment_schedule: ScheduleRow[];
    status?: string;
    review_notes?: string | null;
    created_at?: string | null;
    reviewed_at?: string | null;
    reviewer_name?: string | null;
    employee: {
        id: number;
        full_name: string;
        job_title?: string | null;
        hire_date?: string | null;
        gross_monthly: number;
    };
    approval_steps?: ApprovalStepState[];
    latest_rejection?: LatestRejectionState | null;
}

interface CompanyItem {
    id: number;
    name_en: string;
    name_ar: string;
}

const props = withDefaults(defineProps<{
    company: CompanyItem;
    pendingRequests?: AdvanceRequestItem[];
    approvedRequests?: AdvanceRequestItem[];
    rejectedRequests?: AdvanceRequestItem[];
    canReviewRequests?: boolean;
    hasApprovalWorkflow?: boolean;
    isReadOnly?: boolean;
}>(), {
    pendingRequests: () => [],
    approvedRequests: () => [],
    rejectedRequests: () => [],
    canReviewRequests: false,
    hasApprovalWorkflow: false,
    isReadOnly: false,
});

const { t, locale } = useI18n();

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
    { title: t('advances.company_title'), href: route('companies.advances.index', props.company.id) },
]);

const detailsDialogOpen = ref(false);
const rejectDialogOpen = ref(false);
const approveDialogOpen = ref(false);
const selectedRequest = ref<AdvanceRequestItem | null>(null);
const rejectingStepId = ref<number | null>(null);
const approvingStepId = ref<number | null>(null);
const approvingRequestId = ref<number | null>(null);
const requestsTab = ref<'pending' | 'approved' | 'rejected'>('pending');

const activeRequests = computed(() => {
    if (requestsTab.value === 'approved') {
        return props.approvedRequests;
    }

    if (requestsTab.value === 'rejected') {
        return props.rejectedRequests;
    }

    return props.pendingRequests;
});

const emptyRequestsMessage = computed(() => {
    if (requestsTab.value === 'approved') {
        return t('advances.no_approved_requests');
    }

    if (requestsTab.value === 'rejected') {
        return t('advances.no_rejected_requests');
    }

    return t('advances.no_pending_requests');
});

const statusLabel = (status: string) => {
    const key = `advances.request_status_${status}`;
    const translated = t(key);
    return translated === key ? status : translated;
};

const statusVariant = (status: string): 'default' | 'secondary' | 'destructive' => {
    if (status === 'approved') return 'default';
    if (status === 'rejected') return 'destructive';
    return 'secondary';
};

const formatCurrency = (amount: number | null | undefined) =>
    `${Number(amount ?? 0).toLocaleString(locale.value === 'ar' ? 'ar-SA' : 'en-GB', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })} SAR`;

const approvingRequest = computed(() =>
    props.pendingRequests.find((item) => item.id === approvingRequestId.value)
    ?? (selectedRequest.value?.id === approvingRequestId.value ? selectedRequest.value : null),
);

const showDirectReviewActions = computed(() =>
    props.canReviewRequests && ! props.hasApprovalWorkflow,
);

function actionableStep(request: AdvanceRequestItem): ApprovalStepState | null {
    return request.approval_steps?.find((step) => step.can_approve || step.can_reject) ?? null;
}

function canReviewOnList(request: AdvanceRequestItem): boolean {
    if (requestsTab.value !== 'pending') {
        return false;
    }

    if (showDirectReviewActions.value) {
        return true;
    }

    return actionableStep(request) !== null;
}

function listApproveLabel(request: AdvanceRequestItem): string {
    if (! props.hasApprovalWorkflow) {
        return t('advances.approve_request');
    }

    const step = actionableStep(request);

    return step?.is_final_step ? t('advances.approve_request') : t('salary_runs.approval_approve');
}

function approveFromList(request: AdvanceRequestItem) {
    if (! props.hasApprovalWorkflow) {
        openApproveDialog(request);
        return;
    }

    const step = actionableStep(request);
    if (! step?.can_approve) {
        return;
    }

    selectedRequest.value = request;
    approveWorkflowStep(step);
}

function rejectFromList(request: AdvanceRequestItem) {
    selectedRequest.value = request;

    if (! props.hasApprovalWorkflow) {
        openDirectRejectDialog();
        return;
    }

    const step = actionableStep(request);
    if (! step?.can_reject) {
        return;
    }

    openRejectDialog(step.id);
}

const approvalList = computed(() => selectedRequest.value?.approval_steps ?? []);
const latestRejection = computed(() => selectedRequest.value?.latest_rejection ?? null);

const approveForm = useForm({
    review_notes: '',
});

const rejectForm = useForm({
    reason: '',
    review_notes: '',
});

function openRequestDetails(request: AdvanceRequestItem) {
    selectedRequest.value = request;
    detailsDialogOpen.value = true;
}

function closeRequestDetails() {
    detailsDialogOpen.value = false;
    selectedRequest.value = null;
}

function openApproveDialog(request: AdvanceRequestItem) {
    approvingRequestId.value = request.id;
    approveForm.reset();
    approveForm.clearErrors();
    approveDialogOpen.value = true;
}

function closeApproveDialog() {
    approveDialogOpen.value = false;
    approvingRequestId.value = null;
    approveForm.reset();
    approveForm.clearErrors();
}

function submitApprove() {
    if (approvingRequestId.value === null) {
        return;
    }

    approveForm.post(
        route('companies.advance-requests.approve', [props.company.id, approvingRequestId.value]),
        {
            preserveScroll: true,
            onSuccess: () => {
                closeApproveDialog();
                closeRequestDetails();
            },
        },
    );
}

function openDirectRejectDialog() {
    rejectingStepId.value = null;
    rejectForm.reset();
    rejectForm.clearErrors();
    rejectDialogOpen.value = true;
}

function openRejectDialog(stepId: number) {
    rejectingStepId.value = stepId;
    rejectForm.reset();
    rejectForm.clearErrors();
    rejectDialogOpen.value = true;
}

function closeRejectDialog() {
    rejectDialogOpen.value = false;
    rejectingStepId.value = null;
    rejectForm.reset();
}

function submitReject() {
    if (! selectedRequest.value) {
        return;
    }

    const isWorkflowRejection = props.hasApprovalWorkflow && rejectingStepId.value !== null;

    const url = isWorkflowRejection
        ? route('companies.advance-requests.reject-step', [
            props.company.id,
            selectedRequest.value.id,
            rejectingStepId.value,
        ])
        : route('companies.advance-requests.reject', [props.company.id, selectedRequest.value.id]);

    rejectForm
        .transform((data) => (isWorkflowRejection
            ? { reason: data.reason }
            : { review_notes: data.reason }))
        .post(url, {
            preserveScroll: true,
            onSuccess: (page) => {
                closeRejectDialog();
                closeRequestDetails();
                const flash = page.props.flash as { success?: string; info?: string } | undefined;
                if (flash?.success) {
                    showFlashFeedback(flash.success, 'success');
                } else if (flash?.info) {
                    showFlashFeedback(flash.info, 'info');
                } else {
                    showFlashFeedback(t('advances.request_rejected_success'), 'success');
                }
            },
        });
}

function approveWorkflowStep(step: ApprovalStepState) {
    if (! selectedRequest.value) {
        return;
    }

    approvingStepId.value = step.id;
    router.post(
        route('companies.advance-requests.approve-step', [
            props.company.id,
            selectedRequest.value.id,
            step.id,
        ]),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                approvingStepId.value = null;
            },
            onSuccess: (page) => {
                closeRequestDetails();
                const flash = page.props.flash as { success?: string; info?: string } | undefined;
                if (flash?.success) {
                    showFlashFeedback(flash.success, 'success');
                } else if (flash?.info) {
                    showFlashFeedback(flash.info, 'info');
                } else {
                    showFlashFeedback(t('advances.approval_saved'), 'success');
                }
            },
        },
    );
}

const formatDateTime = (iso: string | null | undefined): string => {
    if (!iso) {
        return '—';
    }

    try {
        return new Date(iso).toLocaleString(locale.value === 'ar' ? 'ar-SA' : 'en-GB', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return iso;
    }
};

const formatApprovalDate = (iso: string | null | undefined): string => {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleDateString(locale.value === 'ar' ? 'ar-SA' : 'en-GB');
    } catch {
        return iso;
    }
};

const formatApprovalTime = (iso: string | null | undefined): string => {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleTimeString(locale.value === 'ar' ? 'ar-SA' : 'en-GB', {
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return iso;
    }
};
</script>

<template>
    <Head :title="t('advances.company_title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 py-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold flex items-center gap-2">
                        <Wallet class="h-5 w-5 text-amber-600" />
                        {{ t('advances.company_title') }}
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ t('advances.company_description') }} — {{ companyName }}
                    </p>
                </div>
                <Button variant="outline" as-child>
                    <Link :href="route('companies.show', company.id)">
                        {{ t('common.back') }}
                    </Link>
                </Button>
            </div>

            <p v-if="isReadOnly" class="text-sm text-muted-foreground rounded-md border px-4 py-3">
                {{ t('advances.view_only_hint') }}
            </p>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('advances.requests_title') }}</CardTitle>
                    <CardDescription>{{ t('advances.requests_description') }}</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="flex flex-wrap gap-2 border-b pb-3">
                        <Button
                            size="sm"
                            :variant="requestsTab === 'pending' ? 'default' : 'outline'"
                            @click="requestsTab = 'pending'"
                        >
                            {{ t('advances.requests_tab_pending') }}
                            <Badge v-if="pendingRequests.length > 0" variant="secondary" class="ms-2">
                                {{ pendingRequests.length }}
                            </Badge>
                        </Button>
                        <Button
                            size="sm"
                            :variant="requestsTab === 'approved' ? 'default' : 'outline'"
                            @click="requestsTab = 'approved'"
                        >
                            {{ t('advances.requests_tab_approved') }}
                            <Badge v-if="approvedRequests.length > 0" variant="secondary" class="ms-2">
                                {{ approvedRequests.length }}
                            </Badge>
                        </Button>
                        <Button
                            size="sm"
                            :variant="requestsTab === 'rejected' ? 'default' : 'outline'"
                            @click="requestsTab = 'rejected'"
                        >
                            {{ t('advances.requests_tab_rejected') }}
                            <Badge v-if="rejectedRequests.length > 0" variant="secondary" class="ms-2">
                                {{ rejectedRequests.length }}
                            </Badge>
                        </Button>
                    </div>

                    <div v-if="activeRequests.length === 0" class="text-sm text-muted-foreground py-6 text-center">
                        {{ emptyRequestsMessage }}
                    </div>

                    <div v-else class="space-y-4">
                        <div
                            v-for="request in activeRequests"
                            :key="request.id"
                            class="rounded-lg border p-4 space-y-3"
                        >
                            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-medium">{{ request.employee.full_name }}</p>
                                        <Badge
                                            v-if="request.status && requestsTab !== 'pending'"
                                            :variant="statusVariant(request.status)"
                                        >
                                            {{ statusLabel(request.status) }}
                                        </Badge>
                                    </div>
                                    <p v-if="request.employee.job_title" class="text-sm text-muted-foreground mt-1">
                                        {{ request.employee.job_title }}
                                    </p>
                                    <div class="mt-2 grid grid-cols-1 gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                                        <p>
                                            <span class="text-muted-foreground">{{ t('advances.amount') }}:</span>
                                            <span class="font-medium"> {{ formatCurrency(request.amount) }}</span>
                                        </p>
                                        <p>
                                            <span class="text-muted-foreground">{{ t('advances.monthly_deduction') }}:</span>
                                            <span class="font-medium"> {{ formatCurrency(request.monthly_deduction) }}</span>
                                        </p>
                                        <p>
                                            <span class="text-muted-foreground">{{ t('advances.months_count') }}:</span>
                                            <span class="font-medium"> {{ request.months_count }}</span>
                                        </p>
                                        <p>
                                            <span class="text-muted-foreground">{{ t('advances.gross_monthly') }}:</span>
                                            <span class="font-medium"> {{ formatCurrency(request.employee.gross_monthly) }}</span>
                                        </p>
                                    </div>
                                    <p v-if="request.reviewed_at" class="text-xs text-muted-foreground mt-1">
                                        {{ t('advances.request_reviewed_at') }}: {{ formatDateTime(request.reviewed_at) }}
                                        <span v-if="request.reviewer_name"> — {{ request.reviewer_name }}</span>
                                    </p>
                                </div>
                                <div class="flex flex-wrap gap-2 shrink-0">
                                    <Button size="sm" variant="outline" @click="openRequestDetails(request)">
                                        {{ t('advances.request_details') }}
                                    </Button>
                                    <template v-if="canReviewOnList(request)">
                                        <Button
                                            v-if="!hasApprovalWorkflow || actionableStep(request)?.can_approve"
                                            size="sm"
                                            :disabled="approvingStepId !== null"
                                            @click="approveFromList(request)"
                                        >
                                            {{ listApproveLabel(request) }}
                                        </Button>
                                        <Button
                                            v-if="!hasApprovalWorkflow || actionableStep(request)?.can_reject"
                                            size="sm"
                                            variant="destructive"
                                            :disabled="approvingStepId !== null"
                                            @click="rejectFromList(request)"
                                        >
                                            {{ t('advances.reject_request') }}
                                        </Button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Dialog :open="detailsDialogOpen" @update:open="(open: boolean) => (open ? undefined : closeRequestDetails())">
                <DialogContent class="max-w-xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader class="space-y-1">
                        <DialogTitle>{{ t('advances.request_details') }}</DialogTitle>
                        <DialogDescription v-if="selectedRequest" class="text-base font-medium text-foreground">
                            {{ selectedRequest.employee.full_name }}
                        </DialogDescription>
                    </DialogHeader>

                    <div v-if="selectedRequest" class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                            <div class="rounded-md border px-3 py-2.5">
                                <p class="text-xs text-muted-foreground mb-0.5">{{ t('advances.amount') }}</p>
                                <p class="font-medium">{{ formatCurrency(selectedRequest.amount) }}</p>
                            </div>
                            <div class="rounded-md border px-3 py-2.5">
                                <p class="text-xs text-muted-foreground mb-0.5">{{ t('advances.monthly_deduction') }}</p>
                                <p class="font-medium">{{ formatCurrency(selectedRequest.monthly_deduction) }}</p>
                            </div>
                            <div class="rounded-md border px-3 py-2.5">
                                <p class="text-xs text-muted-foreground mb-0.5">{{ t('advances.gross_monthly') }}</p>
                                <p class="font-medium">{{ formatCurrency(selectedRequest.employee.gross_monthly) }}</p>
                            </div>
                            <div class="rounded-md border px-3 py-2.5">
                                <p class="text-xs text-muted-foreground mb-0.5">{{ t('advances.hire_date') }}</p>
                                <p class="font-medium">{{ selectedRequest.employee.hire_date || '—' }}</p>
                            </div>
                            <div class="rounded-md border px-3 py-2.5 sm:col-span-2">
                                <p class="text-xs text-muted-foreground mb-0.5">{{ t('advances.reason') }}</p>
                                <p class="font-medium whitespace-pre-wrap">{{ selectedRequest.reason }}</p>
                            </div>
                            <div class="rounded-md border px-3 py-2.5 sm:col-span-2">
                                <p class="text-xs text-muted-foreground mb-0.5">{{ t('advances.request_submitted_at') }}</p>
                                <p class="font-medium">{{ formatDateTime(selectedRequest.created_at) }}</p>
                            </div>
                            <div v-if="selectedRequest.review_notes" class="rounded-md border px-3 py-2.5 sm:col-span-2">
                                <p class="text-xs text-muted-foreground mb-0.5">{{ t('advances.review_notes') }}</p>
                                <p class="font-medium whitespace-pre-wrap">{{ selectedRequest.review_notes }}</p>
                            </div>
                        </div>

                        <div class="space-y-2 border-t pt-4">
                            <p class="font-medium">{{ t('advances.repayment_plan') }}</p>
                            <p class="text-sm text-muted-foreground">
                                {{ t('advances.repayment_plan_summary', {
                                    amount: formatCurrency(selectedRequest.amount),
                                    months: selectedRequest.months_count,
                                    monthly: formatCurrency(selectedRequest.monthly_deduction),
                                }) }}
                            </p>
                            <ul class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                <li
                                    v-for="row in selectedRequest.repayment_schedule"
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

                        <div
                            v-if="hasApprovalWorkflow && selectedRequest.approval_steps?.length"
                            class="space-y-4 border-t pt-4"
                        >
                            <p class="font-medium">{{ t('leaves.approvals_section') }}</p>

                            <div
                                v-if="latestRejection"
                                class="rounded-lg border border-red-200 bg-red-50/50 p-4 space-y-2 dark:border-red-800 dark:bg-red-950/20"
                            >
                                <p class="font-medium text-red-800 dark:text-red-300">
                                    {{ t('salary_runs.approval_rejection_notice_title') }}
                                </p>
                                <p class="text-sm text-red-700 dark:text-red-300">
                                    {{ t('salary_runs.approval_rejection_notice_message', {
                                        name: latestRejection.rejector_name ?? '—',
                                        step: latestRejection.step_title ?? '—',
                                        date: formatApprovalDate(latestRejection.rejected_at),
                                        time: formatApprovalTime(latestRejection.rejected_at),
                                    }) }}
                                </p>
                                <div class="text-sm">
                                    <span class="font-medium text-red-800 dark:text-red-300">{{ t('salary_runs.approval_rejection_reason_label') }}:</span>
                                    <span class="text-red-700 dark:text-red-300">{{ latestRejection.reason }}</span>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div
                                    v-for="approval in approvalList"
                                    :key="approval.id"
                                    class="rounded-lg border p-4 flex flex-col justify-between"
                                    :class="approval.approved_at ? 'border-green-200 bg-green-50/50 dark:bg-green-950/20 dark:border-green-800' : 'border-gray-200 dark:border-gray-700'"
                                >
                                    <div class="font-medium text-sm mb-1">{{ approval.title }}</div>
                                    <div v-if="approval.team_name" class="text-xs text-muted-foreground mb-2">
                                        {{ approval.team_name }}
                                    </div>
                                    <p v-if="approval.is_final_step && !approval.approved_at" class="text-xs text-amber-700 dark:text-amber-400 mb-2">
                                        {{ t('advances.final_step_creates_debt') }}
                                    </p>
                                    <div v-if="approval.approved_at" class="text-sm space-y-1">
                                        <div class="text-muted-foreground">
                                            <span>{{ t('salary_runs.approval_date_label') }}:</span>
                                            {{ formatApprovalDate(approval.approved_at) }}
                                        </div>
                                        <div class="font-medium pt-0.5">
                                            <span class="text-muted-foreground">{{ t('salary_runs.approval_by_label') }}:</span>
                                            {{ approval.approver_name || '—' }}
                                        </div>
                                    </div>
                                    <div v-else class="space-y-2">
                                        <p v-if="approval.waiting_previous" class="text-sm text-amber-600 dark:text-amber-400">
                                            {{ t('salary_runs.approval_waiting_previous') }}
                                        </p>
                                        <p v-else class="text-sm text-amber-600 dark:text-amber-400">
                                            {{ t('salary_runs.approval_pending') }}
                                        </p>
                                        <div v-if="approval.can_approve || approval.can_reject" class="flex gap-2">
                                            <Button
                                                v-if="approval.can_approve"
                                                size="sm"
                                                class="flex-1"
                                                :disabled="approvingStepId === approval.id"
                                                @click="approveWorkflowStep(approval)"
                                            >
                                                {{ approvingStepId === approval.id ? '...' : (approval.is_final_step ? t('advances.approve_request') : t('salary_runs.approval_approve')) }}
                                            </Button>
                                            <Button
                                                v-if="approval.can_reject"
                                                size="sm"
                                                variant="destructive"
                                                class="flex-1"
                                                :disabled="approvingStepId === approval.id"
                                                @click="openRejectDialog(approval.id)"
                                            >
                                                {{ t('salary_runs.approval_reject') }}
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <DialogFooter class="flex-wrap gap-2">
                        <Button variant="outline" @click="closeRequestDetails">
                            {{ t('common.close') }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog :open="approveDialogOpen" @update:open="(open: boolean) => (open ? undefined : closeApproveDialog())">
                <DialogContent class="max-w-md">
                    <DialogHeader>
                        <DialogTitle>{{ t('advances.approve_request') }}</DialogTitle>
                        <DialogDescription>{{ t('advances.approve_request_hint') }}</DialogDescription>
                    </DialogHeader>
                    <form class="space-y-4" @submit.prevent="submitApprove">
                        <p v-if="approvingRequest" class="rounded-md border bg-muted/40 px-3 py-2 text-sm text-muted-foreground">
                            {{ t('advances.repayment_plan_summary', {
                                amount: formatCurrency(approvingRequest.amount),
                                months: approvingRequest.months_count,
                                monthly: formatCurrency(approvingRequest.monthly_deduction),
                            }) }}
                        </p>
                        <div class="space-y-2">
                            <Label for="approve-review-notes">{{ t('advances.review_notes') }}</Label>
                            <textarea
                                id="approve-review-notes"
                                v-model="approveForm.review_notes"
                                rows="3"
                                class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" @click="closeApproveDialog">
                                {{ t('common.cancel') }}
                            </Button>
                            <Button type="submit" :disabled="approveForm.processing">
                                {{ approveForm.processing ? t('common.saving') : t('advances.approve_request') }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog :open="rejectDialogOpen" @update:open="(open: boolean) => (open ? undefined : closeRejectDialog())">
                <DialogContent class="max-w-md">
                    <DialogHeader>
                        <DialogTitle>{{ t('salary_runs.approval_reject_confirm_title') }}</DialogTitle>
                        <DialogDescription>{{ t('salary_runs.approval_reject_confirm_message') }}</DialogDescription>
                    </DialogHeader>
                    <form class="space-y-4" @submit.prevent="submitReject">
                        <div class="space-y-2">
                            <Label for="reject-reason">{{ t('salary_runs.approval_reject_reason_label') }}</Label>
                            <Input
                                id="reject-reason"
                                v-model="rejectForm.reason"
                                :placeholder="t('salary_runs.approval_reject_reason_placeholder')"
                                required
                            />
                            <p v-if="rejectForm.errors.reason" class="text-sm text-red-600">{{ rejectForm.errors.reason }}</p>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" @click="closeRejectDialog">
                                {{ t('common.cancel') }}
                            </Button>
                            <Button type="submit" variant="destructive" :disabled="rejectForm.processing">
                                {{ rejectForm.processing ? '...' : t('salary_runs.approval_reject') }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>

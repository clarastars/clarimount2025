<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Download, Eye, FileBadge } from 'lucide-vue-next';

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
    requires_certificate?: boolean;
}

interface LatestRejectionState {
    id: number;
    reason: string;
    rejected_at: string;
    rejector_name: string | null;
    step_title: string | null;
    cleared_approvals_count: number;
}

interface CertificateRequestItem {
    id: number;
    purpose: string | null;
    addressed_to: string | null;
    language: string;
    notes?: string | null;
    status?: string;
    review_notes?: string | null;
    has_certificate?: boolean;
    can_preview?: boolean;
    created_at?: string | null;
    reviewed_at?: string | null;
    reviewer_name?: string | null;
    employee: {
        id: number;
        full_name: string;
        job_title?: string | null;
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
    pendingRequests?: CertificateRequestItem[];
    completedRequests?: CertificateRequestItem[];
    rejectedRequests?: CertificateRequestItem[];
    canReviewRequests?: boolean;
    hasApprovalWorkflow?: boolean;
    isReadOnly?: boolean;
}>(), {
    pendingRequests: () => [],
    completedRequests: () => [],
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
    { title: t('salary_certificates.company_title'), href: route('companies.salary-certificates.index', props.company.id) },
]);

const detailsDialogOpen = ref(false);
const rejectDialogOpen = ref(false);
const completeDialogOpen = ref(false);
const certificateStepDialogOpen = ref(false);
const selectedRequest = ref<CertificateRequestItem | null>(null);
const rejectingStepId = ref<number | null>(null);
const approvingStepId = ref<number | null>(null);
const completingRequestId = ref<number | null>(null);
const requestsTab = ref<'pending' | 'completed' | 'rejected'>('pending');

const activeRequests = computed(() => {
    if (requestsTab.value === 'completed') {
        return props.completedRequests;
    }

    if (requestsTab.value === 'rejected') {
        return props.rejectedRequests;
    }

    return props.pendingRequests;
});

const emptyRequestsMessage = computed(() => {
    if (requestsTab.value === 'completed') {
        return t('salary_certificates.no_completed_requests');
    }

    if (requestsTab.value === 'rejected') {
        return t('salary_certificates.no_rejected_requests');
    }

    return t('salary_certificates.no_pending_requests');
});

const statusLabel = (status: string) => {
    const key = `salary_certificates.request_status_${status}`;
    const translated = t(key);
    return translated === key ? status : translated;
};

const statusVariant = (status: string): 'default' | 'secondary' | 'destructive' => {
    if (status === 'completed') return 'default';
    if (status === 'rejected') return 'destructive';
    return 'secondary';
};

const languageLabel = (language: string) => {
    const key = `salary_certificates.language_${language}`;
    const translated = t(key);
    return translated === key ? language : translated;
};

const showDirectReviewActions = computed(() =>
    props.canReviewRequests && ! props.hasApprovalWorkflow,
);

const approvalList = computed(() => selectedRequest.value?.approval_steps ?? []);
const latestRejection = computed(() => selectedRequest.value?.latest_rejection ?? null);

const completeForm = useForm({
    review_notes: '',
});

const rejectForm = useForm({
    reason: '',
});

const reviewForm = useForm({
    review_notes: '',
});

const stepApproveForm = useForm({
    review_notes: '',
});

function openRequestDetails(request: CertificateRequestItem) {
    selectedRequest.value = request;
    detailsDialogOpen.value = true;
}

function closeRequestDetails() {
    detailsDialogOpen.value = false;
    selectedRequest.value = null;
}

function openCompleteDialog(requestId: number) {
    completingRequestId.value = requestId;
    completeForm.reset();
    completeForm.clearErrors();
    completeDialogOpen.value = true;
}

function closeCompleteDialog() {
    completeDialogOpen.value = false;
    completingRequestId.value = null;
    completeForm.reset();
    completeForm.clearErrors();
}

function submitComplete() {
    if (completingRequestId.value === null) {
        return;
    }

    completeForm.post(
        route('companies.salary-certificate-requests.complete', [props.company.id, completingRequestId.value]),
        {
            preserveScroll: true,
            onSuccess: () => {
                closeCompleteDialog();
                closeRequestDetails();
            },
        },
    );
}

function rejectRequest(requestId: number) {
    reviewForm.post(route('companies.salary-certificate-requests.reject', [props.company.id, requestId]), {
        preserveScroll: true,
        onSuccess: () => {
            reviewForm.reset();
            closeRequestDetails();
        },
    });
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

function submitRejectStep() {
    if (! selectedRequest.value || rejectingStepId.value === null) {
        return;
    }

    rejectForm.post(
        route('companies.salary-certificate-requests.reject-step', [
            props.company.id,
            selectedRequest.value.id,
            rejectingStepId.value,
        ]),
        {
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
                    showFlashFeedback(t('salary_certificates.request_rejected_success'), 'success');
                }
            },
        },
    );
}

function approveWorkflowStep(step: ApprovalStepState) {
    if (! selectedRequest.value) {
        return;
    }

    if (step.requires_certificate) {
        approvingStepId.value = step.id;
        stepApproveForm.reset();
        stepApproveForm.clearErrors();
        certificateStepDialogOpen.value = true;
        return;
    }

    approvingStepId.value = step.id;
    router.post(
        route('companies.salary-certificate-requests.approve-step', [
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
                    showFlashFeedback(t('salary_certificates.approval_saved'), 'success');
                }
            },
        },
    );
}

function closeCertificateStepDialog() {
    certificateStepDialogOpen.value = false;
    approvingStepId.value = null;
    stepApproveForm.reset();
    stepApproveForm.clearErrors();
}

function submitCertificateStep() {
    if (! selectedRequest.value || approvingStepId.value === null) {
        return;
    }

    stepApproveForm.post(
        route('companies.salary-certificate-requests.approve-step', [
            props.company.id,
            selectedRequest.value.id,
            approvingStepId.value,
        ]),
        {
            preserveScroll: true,
            onSuccess: (page) => {
                closeCertificateStepDialog();
                closeRequestDetails();
                const flash = page.props.flash as { success?: string; info?: string } | undefined;
                if (flash?.success) {
                    showFlashFeedback(flash.success, 'success');
                } else if (flash?.info) {
                    showFlashFeedback(flash.info, 'info');
                } else {
                    showFlashFeedback(t('salary_certificates.approval_saved'), 'success');
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
    <Head :title="t('salary_certificates.company_title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 py-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold flex items-center gap-2">
                        <FileBadge class="h-5 w-5 text-amber-600" />
                        {{ t('salary_certificates.company_title') }}
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ t('salary_certificates.company_description') }} — {{ companyName }}
                    </p>
                </div>
                <Button variant="outline" as-child>
                    <Link :href="route('companies.show', company.id)">
                        {{ t('common.back') }}
                    </Link>
                </Button>
            </div>

            <p v-if="isReadOnly" class="text-sm text-muted-foreground rounded-md border px-4 py-3">
                {{ t('salary_certificates.view_only_hint') }}
            </p>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('salary_certificates.requests_title') }}</CardTitle>
                    <CardDescription>{{ t('salary_certificates.requests_description') }}</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="flex flex-wrap gap-2 border-b pb-3">
                        <Button
                            size="sm"
                            :variant="requestsTab === 'pending' ? 'default' : 'outline'"
                            @click="requestsTab = 'pending'"
                        >
                            {{ t('salary_certificates.requests_tab_pending') }}
                            <Badge v-if="pendingRequests.length > 0" variant="secondary" class="ms-2">
                                {{ pendingRequests.length }}
                            </Badge>
                        </Button>
                        <Button
                            size="sm"
                            :variant="requestsTab === 'completed' ? 'default' : 'outline'"
                            @click="requestsTab = 'completed'"
                        >
                            {{ t('salary_certificates.requests_tab_completed') }}
                            <Badge v-if="completedRequests.length > 0" variant="secondary" class="ms-2">
                                {{ completedRequests.length }}
                            </Badge>
                        </Button>
                        <Button
                            size="sm"
                            :variant="requestsTab === 'rejected' ? 'default' : 'outline'"
                            @click="requestsTab = 'rejected'"
                        >
                            {{ t('salary_certificates.requests_tab_rejected') }}
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
                                    <p class="text-sm text-muted-foreground mt-1">
                                        {{ request.purpose || '—' }}
                                        <span v-if="request.employee.job_title"> — {{ request.employee.job_title }}</span>
                                    </p>
                                    <p v-if="request.reviewed_at" class="text-xs text-muted-foreground mt-1">
                                        {{ t('salary_certificates.request_reviewed_at') }}: {{ formatDateTime(request.reviewed_at) }}
                                        <span v-if="request.reviewer_name"> — {{ request.reviewer_name }}</span>
                                    </p>
                                </div>
                                <div class="flex flex-wrap gap-2 shrink-0">
                                    <Button size="sm" variant="outline" @click="openRequestDetails(request)">
                                        {{ t('salary_certificates.request_details') }}
                                    </Button>
                                    <Button
                                        v-if="request.can_preview"
                                        size="sm"
                                        variant="outline"
                                        as-child
                                    >
                                        <a
                                            :href="route('companies.salary-certificate-requests.preview', [company.id, request.id])"
                                            target="_blank"
                                            rel="noopener"
                                        >
                                            <Eye class="mr-1 h-3.5 w-3.5" />
                                            {{ t('salary_certificates.view_certificate') }}
                                        </a>
                                    </Button>
                                    <Button
                                        v-if="request.can_preview"
                                        size="sm"
                                        variant="outline"
                                        as-child
                                    >
                                        <a :href="route('companies.salary-certificate-requests.download', [company.id, request.id])">
                                            <Download class="mr-1 h-3.5 w-3.5" />
                                            {{ t('salary_certificates.download_certificate') }}
                                        </a>
                                    </Button>
                                    <template v-if="requestsTab === 'pending' && showDirectReviewActions">
                                        <Button size="sm" @click="openCompleteDialog(request.id)">
                                            {{ t('salary_certificates.approve_and_issue') }}
                                        </Button>
                                        <Button size="sm" variant="destructive" :disabled="reviewForm.processing" @click="rejectRequest(request.id)">
                                            {{ t('salary_certificates.reject_request') }}
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
                        <DialogTitle>{{ t('salary_certificates.request_details') }}</DialogTitle>
                        <DialogDescription v-if="selectedRequest" class="text-base font-medium text-foreground">
                            {{ selectedRequest.employee.full_name }}
                        </DialogDescription>
                    </DialogHeader>

                    <div v-if="selectedRequest" class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                            <div class="rounded-md border px-3 py-2.5 sm:col-span-2">
                                <p class="text-xs text-muted-foreground mb-0.5">{{ t('salary_certificates.purpose') }}</p>
                                <p class="font-medium whitespace-pre-wrap">{{ selectedRequest.purpose || '—' }}</p>
                            </div>
                            <div class="rounded-md border px-3 py-2.5">
                                <p class="text-xs text-muted-foreground mb-0.5">{{ t('salary_certificates.addressed_to') }}</p>
                                <p class="font-medium">{{ selectedRequest.addressed_to || '—' }}</p>
                            </div>
                            <div class="rounded-md border px-3 py-2.5">
                                <p class="text-xs text-muted-foreground mb-0.5">{{ t('salary_certificates.language') }}</p>
                                <p class="font-medium">{{ languageLabel(selectedRequest.language) }}</p>
                            </div>
                            <div class="rounded-md border px-3 py-2.5 sm:col-span-2">
                                <p class="text-xs text-muted-foreground mb-0.5">{{ t('salary_certificates.request_submitted_at') }}</p>
                                <p class="font-medium">{{ formatDateTime(selectedRequest.created_at) }}</p>
                            </div>
                            <div class="rounded-md border px-3 py-2.5 sm:col-span-2">
                                <p class="text-xs text-muted-foreground mb-0.5">{{ t('salary_certificates.notes') }}</p>
                                <p class="font-medium whitespace-pre-wrap">{{ selectedRequest.notes || '—' }}</p>
                            </div>
                            <div v-if="selectedRequest.review_notes" class="rounded-md border px-3 py-2.5 sm:col-span-2">
                                <p class="text-xs text-muted-foreground mb-0.5">{{ t('salary_certificates.review_notes') }}</p>
                                <p class="font-medium whitespace-pre-wrap">{{ selectedRequest.review_notes }}</p>
                            </div>
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
                                    <p v-if="approval.requires_certificate && !approval.approved_at" class="text-xs text-amber-700 dark:text-amber-400 mb-2">
                                        {{ t('salary_certificates.final_step_issues_certificate') }}
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
                                                :disabled="approvingStepId === approval.id || rejectingStepId === approval.id"
                                                @click="approveWorkflowStep(approval)"
                                            >
                                                {{ approvingStepId === approval.id ? '...' : (approval.requires_certificate ? t('salary_certificates.approve_and_issue') : t('salary_runs.approval_approve')) }}
                                            </Button>
                                            <Button
                                                v-if="approval.can_reject"
                                                size="sm"
                                                variant="destructive"
                                                class="flex-1"
                                                :disabled="approvingStepId === approval.id || rejectingStepId === approval.id"
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
                        <Button
                            v-if="selectedRequest?.can_preview"
                            variant="outline"
                            as-child
                        >
                            <a
                                :href="route('companies.salary-certificate-requests.preview', [company.id, selectedRequest.id])"
                                target="_blank"
                                rel="noopener"
                            >
                                <Eye class="mr-1 h-3.5 w-3.5" />
                                {{ t('salary_certificates.view_certificate') }}
                            </a>
                        </Button>
                        <Button
                            v-if="selectedRequest?.can_preview"
                            variant="outline"
                            as-child
                        >
                            <a :href="route('companies.salary-certificate-requests.download', [company.id, selectedRequest.id])">
                                <Download class="mr-1 h-3.5 w-3.5" />
                                {{ t('salary_certificates.download_certificate') }}
                            </a>
                        </Button>
                        <Button variant="outline" @click="closeRequestDetails">
                            {{ t('common.close') }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog :open="completeDialogOpen" @update:open="(open: boolean) => (open ? undefined : closeCompleteDialog())">
                <DialogContent class="max-w-md">
                    <DialogHeader>
                        <DialogTitle>{{ t('salary_certificates.approve_and_issue') }}</DialogTitle>
                        <DialogDescription>{{ t('salary_certificates.approve_and_issue_hint') }}</DialogDescription>
                    </DialogHeader>
                    <form class="space-y-4" @submit.prevent="submitComplete">
                        <div class="space-y-2">
                            <Label for="review_notes">{{ t('salary_certificates.review_notes') }}</Label>
                            <textarea
                                id="review_notes"
                                v-model="completeForm.review_notes"
                                rows="3"
                                class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" @click="closeCompleteDialog">
                                {{ t('common.cancel') }}
                            </Button>
                            <Button type="submit" :disabled="completeForm.processing">
                                {{ completeForm.processing ? t('common.saving') : t('salary_certificates.approve_and_issue') }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog :open="certificateStepDialogOpen" @update:open="(open: boolean) => (open ? undefined : closeCertificateStepDialog())">
                <DialogContent class="max-w-md">
                    <DialogHeader>
                        <DialogTitle>{{ t('salary_certificates.approve_and_issue') }}</DialogTitle>
                        <DialogDescription>{{ t('salary_certificates.final_step_issues_certificate') }}</DialogDescription>
                    </DialogHeader>
                    <form class="space-y-4" @submit.prevent="submitCertificateStep">
                        <div class="space-y-2">
                            <Label for="step-review-notes">{{ t('salary_certificates.review_notes') }}</Label>
                            <textarea
                                id="step-review-notes"
                                v-model="stepApproveForm.review_notes"
                                rows="3"
                                class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" @click="closeCertificateStepDialog">
                                {{ t('common.cancel') }}
                            </Button>
                            <Button type="submit" :disabled="stepApproveForm.processing">
                                {{ stepApproveForm.processing ? t('common.saving') : t('salary_certificates.approve_and_issue') }}
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
                    <form class="space-y-4" @submit.prevent="submitRejectStep">
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

<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Calendar, CalendarPlus } from 'lucide-vue-next';

import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import LeaveFormFields from '@/components/leaves/LeaveFormFields.vue';
import type { BreadcrumbItem } from '@/types';

interface CurrentLeave {
    id: number;
    leave_type: string;
    start_date: string;
    end_date: string;
    days: number;
    is_paid: boolean;
    deduct_from_balance: boolean;
    employee: {
        id: number;
        full_name: string;
    };
}

interface EmployeeOption {
    id: number;
    full_name: string;
}

interface LeaveRequestItem {
    id: number;
    leave_type: string;
    start_date: string;
    end_date: string;
    days: number;
    is_paid: boolean;
    deduct_from_balance: boolean;
    notes?: string | null;
    status?: string;
    review_notes?: string | null;
    attachment_url?: string | null;
    created_at?: string | null;
    reviewed_at?: string | null;
    reviewer_name?: string | null;
    employee: {
        id: number;
        full_name: string;
    };
}

interface CompanyItem {
    id: number;
    name_en: string;
    name_ar: string;
}

const props = withDefaults(defineProps<{
    company: CompanyItem;
    currentLeaves: CurrentLeave[];
    pendingRequests?: LeaveRequestItem[];
    approvedRequests?: LeaveRequestItem[];
    rejectedRequests?: LeaveRequestItem[];
    employees: EmployeeOption[];
    canCreateLeaves: boolean;
    canReviewLeaveRequests?: boolean;
    isReadOnly?: boolean;
    leaveTypes: string[];
}>(), {
    pendingRequests: () => [],
    approvedRequests: () => [],
    rejectedRequests: () => [],
    canReviewLeaveRequests: false,
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
    { title: t('leaves.company_leaves_title'), href: route('companies.leaves.index', props.company.id) },
]);

const leaveTypeLabel = (type: string) => {
    const key = `leaves.type_${type}`;
    const translated = t(key);
    return translated === key ? type : translated;
};

const form = useForm({
    employee_id: '' as string | number,
    leave_type: '',
    start_date: '',
    end_date: '',
    deduct_from_balance: false,
    is_paid: true,
    notes: '',
    attachment: null as File | null,
});

const createFormOpen = ref(false);
const detailsDialogOpen = ref(false);
const selectedRequest = ref<LeaveRequestItem | null>(null);
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
        return t('leaves.no_approved_requests');
    }

    if (requestsTab.value === 'rejected') {
        return t('leaves.no_rejected_requests');
    }

    return t('leaves.no_pending_requests');
});

const statusLabel = (status: string) => {
    const key = `leaves.request_status_${status}`;
    const translated = t(key);
    return translated === key ? status : translated;
};

const statusVariant = (status: string): 'default' | 'secondary' | 'destructive' => {
    if (status === 'approved') {
        return 'default';
    }

    if (status === 'rejected') {
        return 'destructive';
    }

    return 'secondary';
};

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
    form.post(route('companies.leaves.store', props.company.id), {
        forceFormData: true,
        onSuccess: () => closeCreateForm(),
    });
};

const reviewForm = useForm({
    review_notes: '',
});

const approveRequest = (requestId: number) => {
    reviewForm.post(route('companies.leave-requests.approve', [props.company.id, requestId]), {
        preserveScroll: true,
        onSuccess: () => reviewForm.reset(),
    });
};

const rejectRequest = (requestId: number) => {
    reviewForm.post(route('companies.leave-requests.reject', [props.company.id, requestId]), {
        preserveScroll: true,
        onSuccess: () => reviewForm.reset(),
    });
};

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

function openRequestDetails(request: LeaveRequestItem) {
    selectedRequest.value = request;
    detailsDialogOpen.value = true;
}

function closeRequestDetails() {
    detailsDialogOpen.value = false;
    selectedRequest.value = null;
}
</script>

<template>
    <Head :title="t('leaves.company_leaves_title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 py-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <Calendar class="h-5 w-5 text-amber-600" />
                        {{ t('leaves.company_leaves_title') }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        {{ companyName }} — {{ t('leaves.company_leaves_description') }}
                    </p>
                    <p v-if="isReadOnly" class="mt-1 text-xs text-amber-700 dark:text-amber-400">
                        {{ t('leaves.view_only_hint') }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button v-if="canCreateLeaves" @click="openCreateForm">
                        <CalendarPlus class="mr-2 h-4 w-4" />
                        {{ t('leaves.create_leave') }}
                    </Button>
                    <Button variant="outline" as-child>
                        <Link :href="route('companies.show', company.id)">
                            {{ t('common.back') }}
                        </Link>
                    </Button>
                </div>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('leaves.current_leaves') }}</CardTitle>
                    <CardDescription>{{ t('leaves.current_leaves_description') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="currentLeaves.length === 0" class="text-sm text-muted-foreground py-6 text-center">
                        {{ t('leaves.no_current_leaves') }}
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-muted-foreground">
                                    <th class="py-3 px-2 text-start font-medium">{{ t('leaves.employee') }}</th>
                                    <th class="py-3 px-2 text-start font-medium">{{ t('leaves.leave_type') }}</th>
                                    <th class="py-3 px-2 text-start font-medium">{{ t('leaves.start_date') }}</th>
                                    <th class="py-3 px-2 text-start font-medium">{{ t('leaves.end_date') }}</th>
                                    <th class="py-3 px-2 text-start font-medium">{{ t('leaves.days') }}</th>
                                    <th class="py-3 px-2 text-start font-medium">{{ t('leaves.paid_leave_label') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="leave in currentLeaves"
                                    :key="leave.id"
                                    class="border-b last:border-0 hover:bg-muted/40"
                                >
                                    <td class="py-3 px-2">
                                        <Link
                                            :href="route('employees.show', leave.employee.id)"
                                            class="text-primary hover:underline font-medium"
                                        >
                                            {{ leave.employee.full_name }}
                                        </Link>
                                    </td>
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

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('leaves.leave_requests') }}</CardTitle>
                    <CardDescription>{{ t('leaves.leave_requests_description') }}</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="flex flex-wrap gap-2 border-b pb-3">
                        <Button
                            size="sm"
                            :variant="requestsTab === 'pending' ? 'default' : 'outline'"
                            @click="requestsTab = 'pending'"
                        >
                            {{ t('leaves.requests_tab_pending') }}
                            <Badge v-if="pendingRequests.length > 0" variant="secondary" class="ms-2">
                                {{ pendingRequests.length }}
                            </Badge>
                        </Button>
                        <Button
                            size="sm"
                            :variant="requestsTab === 'approved' ? 'default' : 'outline'"
                            @click="requestsTab = 'approved'"
                        >
                            {{ t('leaves.requests_tab_approved') }}
                            <Badge v-if="approvedRequests.length > 0" variant="secondary" class="ms-2">
                                {{ approvedRequests.length }}
                            </Badge>
                        </Button>
                        <Button
                            size="sm"
                            :variant="requestsTab === 'rejected' ? 'default' : 'outline'"
                            @click="requestsTab = 'rejected'"
                        >
                            {{ t('leaves.requests_tab_rejected') }}
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
                                        {{ leaveTypeLabel(request.leave_type) }} — {{ request.start_date }} → {{ request.end_date }} ({{ request.days }} {{ t('leaves.days') }})
                                    </p>
                                    <p v-if="request.reviewed_at" class="text-xs text-muted-foreground mt-1">
                                        {{ t('leaves.request_reviewed_at') }}: {{ formatDateTime(request.reviewed_at) }}
                                        <span v-if="request.reviewer_name"> — {{ request.reviewer_name }}</span>
                                    </p>
                                </div>
                                <div class="flex flex-wrap gap-2 shrink-0">
                                    <Button size="sm" variant="outline" @click="openRequestDetails(request)">
                                        {{ t('leaves.request_details') }}
                                    </Button>
                                    <template v-if="requestsTab === 'pending' && canReviewLeaveRequests">
                                        <Button size="sm" :disabled="reviewForm.processing" @click="approveRequest(request.id)">
                                            {{ t('leaves.approve_request') }}
                                        </Button>
                                        <Button size="sm" variant="destructive" :disabled="reviewForm.processing" @click="rejectRequest(request.id)">
                                            {{ t('leaves.reject_request') }}
                                        </Button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Dialog :open="detailsDialogOpen" @update:open="(open: boolean) => (open ? undefined : closeRequestDetails())">
                <DialogContent class="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{{ t('leaves.request_details') }}</DialogTitle>
                        <DialogDescription v-if="selectedRequest">
                            {{ selectedRequest.employee.full_name }}
                        </DialogDescription>
                    </DialogHeader>

                    <div v-if="selectedRequest" class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                        <div>
                            <p class="text-muted-foreground">{{ t('leaves.leave_type') }}</p>
                            <p class="font-medium">{{ leaveTypeLabel(selectedRequest.leave_type) }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">{{ t('leaves.days') }}</p>
                            <p class="font-medium">{{ selectedRequest.days }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">{{ t('leaves.start_date') }}</p>
                            <p class="font-medium">{{ selectedRequest.start_date }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">{{ t('leaves.end_date') }}</p>
                            <p class="font-medium">{{ selectedRequest.end_date }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">{{ t('leaves.deduct_from_balance_label') }}</p>
                            <p class="font-medium">
                                {{ selectedRequest.deduct_from_balance ? t('leaves.deduct_yes') : t('leaves.deduct_no') }}
                            </p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">{{ t('leaves.paid_leave_label') }}</p>
                            <p class="font-medium">
                                {{ selectedRequest.is_paid ? t('leaves.paid_yes') : t('leaves.paid_no') }}
                            </p>
                        </div>
                        <div class="sm:col-span-2">
                            <p class="text-muted-foreground">{{ t('leaves.request_submitted_at') }}</p>
                            <p class="font-medium">{{ formatDateTime(selectedRequest.created_at) }}</p>
                        </div>
                        <div v-if="selectedRequest.reviewed_at" class="sm:col-span-2">
                            <p class="text-muted-foreground">{{ t('leaves.request_reviewed_at') }}</p>
                            <p class="font-medium">
                                {{ formatDateTime(selectedRequest.reviewed_at) }}
                                <span v-if="selectedRequest.reviewer_name"> — {{ selectedRequest.reviewer_name }}</span>
                            </p>
                        </div>
                        <div v-if="selectedRequest.status" class="sm:col-span-2">
                            <p class="text-muted-foreground">{{ t('leaves.request_status') }}</p>
                            <Badge :variant="statusVariant(selectedRequest.status)">
                                {{ statusLabel(selectedRequest.status) }}
                            </Badge>
                        </div>
                        <div class="sm:col-span-2">
                            <p class="text-muted-foreground">{{ t('leaves.notes') }}</p>
                            <p class="font-medium">{{ selectedRequest.notes || '—' }}</p>
                        </div>
                        <div v-if="selectedRequest.review_notes" class="sm:col-span-2">
                            <p class="text-muted-foreground">{{ t('leaves.review_notes') }}</p>
                            <p class="font-medium">{{ selectedRequest.review_notes }}</p>
                        </div>
                        <div v-if="selectedRequest.attachment_url" class="sm:col-span-2">
                            <p class="text-muted-foreground">{{ t('leaves.attachment') }}</p>
                            <a
                                :href="selectedRequest.attachment_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-primary hover:underline font-medium"
                            >
                                {{ t('leaves.view_attachment') }}
                            </a>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" @click="closeRequestDetails">
                            {{ t('common.close') }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                v-if="canCreateLeaves"
                :open="createFormOpen"
                @update:open="(open: boolean) => (open ? openCreateForm() : closeCreateForm())"
            >
                <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2">
                            <CalendarPlus class="h-5 w-5 text-amber-600" />
                            {{ t('leaves.create_leave') }}
                        </DialogTitle>
                        <DialogDescription>{{ t('leaves.create_leave_company_description') }}</DialogDescription>
                    </DialogHeader>

                    <form @submit.prevent="submit" class="space-y-6">
                        <LeaveFormFields
                            :form="form"
                            show-employee-select
                            :employees="employees"
                            @attachment-change="onAttachmentChange"
                        />

                        <DialogFooter>
                            <Button type="button" variant="outline" @click="closeCreateForm">
                                {{ t('common.cancel') }}
                            </Button>
                            <Button type="submit" :disabled="form.processing">
                                <span v-if="form.processing">{{ t('common.saving') }}</span>
                                <span v-else>{{ t('leaves.submit') }}</span>
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { CheckCircle2, Circle, Clock, Download, Eye, FileBadge } from 'lucide-vue-next';

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

interface CertificateRequestRow {
    id: number;
    purpose: string | null;
    addressed_to: string | null;
    language: string;
    attestation_type?: string;
    attestation_fee?: number | null;
    notes?: string | null;
    status: string;
    review_notes?: string | null;
    has_certificate: boolean;
    created_at?: string | null;
    reviewed_at?: string | null;
    approval_progress?: ApprovalProgress | null;
}

interface EmployeeSummary {
    id: number;
    full_name: string;
    company_name?: string | null;
}

const props = defineProps<{
    employee: EmployeeSummary;
    requests: CertificateRequestRow[];
    languages: string[];
    attestationTypes?: string[];
    chamberFee?: number;
}>();

const { t, locale } = useI18n();
const page = usePage();

const breadcrumbs = computed((): BreadcrumbItem[] => [
    { title: t('nav.dashboard'), href: '/dashboard' },
    { title: t('salary_certificates.my_requests_title'), href: route('employee.salary-certificates.index') },
]);

const createFormOpen = ref(false);
const cancellingRequestId = ref<number | null>(null);
const expandedApprovalRequestIds = ref<number[]>([]);

const form = useForm({
    purpose: '',
    addressed_to: '',
    language: 'ar',
    attestation_type: 'none',
    notes: '',
});

const statusLabel = (status: string) => {
    const key = `salary_certificates.request_status_${status}`;
    const translated = t(key);
    return translated === key ? status : translated;
};

const statusVariant = (status: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
    if (status === 'completed') return 'default';
    if (status === 'rejected') return 'destructive';
    if (status === 'cancelled') return 'outline';
    return 'secondary';
};

const languageLabel = (language: string) => {
    const key = `salary_certificates.language_${language}`;
    const translated = t(key);
    return translated === key ? language : translated;
};

const attestationLabel = (type: string | null | undefined) => {
    const key = `salary_certificates.attestation_${type || 'none'}`;
    const translated = t(key);
    return translated === key ? (type || 'none') : translated;
};

const attestationOptions = computed(() => props.attestationTypes?.length ? props.attestationTypes : ['none', 'chamber']);

const chamberFee = computed(() => Number(props.chamberFee ?? 0));

const formatFee = (amount: number) => `${Number(amount).toFixed(2)} SAR`;

const hasAttestationFee = (request: CertificateRequestRow) =>
    request.attestation_type === 'chamber' && Number(request.attestation_fee ?? 0) > 0;

const displayValue = (value: unknown) => (value === null || value === undefined || value === '' ? '—' : value);

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
    form.language = 'ar';
    form.attestation_type = 'none';
}

const submit = () => {
    form.post(route('employee.salary-certificates.store'), {
        onSuccess: () => closeCreateForm(),
    });
};

const cancelRequest = (requestId: number) => {
    if (!window.confirm(t('salary_certificates.cancel_request_confirm'))) {
        return;
    }

    cancellingRequestId.value = requestId;
    router.delete(route('employee.salary-certificates.destroy', requestId), {
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
    <Head :title="t('salary_certificates.my_requests_title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 py-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold flex items-center gap-2">
                        <FileBadge class="h-5 w-5 text-amber-600" />
                        {{ t('salary_certificates.my_requests_title') }}
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ employee.full_name }}
                        <span v-if="employee.company_name"> — {{ employee.company_name }}</span>
                    </p>
                </div>
                <Button @click="openCreateForm">
                    <FileBadge class="mr-2 h-4 w-4" />
                    {{ t('salary_certificates.request_new') }}
                </Button>
            </div>

            <div v-if="(page.props.flash as { success?: string })?.success" class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                {{ (page.props.flash as { success?: string }).success }}
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('salary_certificates.my_requests') }}</CardTitle>
                    <CardDescription>{{ t('salary_certificates.my_requests_description') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="requests.length === 0" class="text-sm text-muted-foreground py-6 text-center">
                        {{ t('salary_certificates.no_requests_yet') }}
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-muted-foreground">
                                    <th class="py-3 px-2 text-start font-medium">{{ t('salary_certificates.purpose') }}</th>
                                    <th class="py-3 px-2 text-start font-medium">{{ t('salary_certificates.addressed_to') }}</th>
                                    <th class="py-3 px-2 text-start font-medium">{{ t('salary_certificates.language') }}</th>
                                    <th class="py-3 px-2 text-start font-medium">{{ t('salary_certificates.attestation_type') }}</th>
                                    <th class="py-3 px-2 text-start font-medium">{{ t('salary_certificates.request_status') }}</th>
                                    <th class="py-3 px-2 text-start font-medium">{{ t('common.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="request in requests" :key="request.id">
                                    <tr class="border-b last:border-0">
                                        <td class="py-3 px-2">{{ displayValue(request.purpose) }}</td>
                                        <td class="py-3 px-2">{{ displayValue(request.addressed_to) }}</td>
                                        <td class="py-3 px-2">{{ languageLabel(request.language) }}</td>
                                        <td class="py-3 px-2">
                                            <div>{{ attestationLabel(request.attestation_type) }}</div>
                                            <p
                                                v-if="hasAttestationFee(request)"
                                                class="text-xs text-muted-foreground mt-1"
                                            >
                                                {{ t('salary_certificates.attestation_fee') }}: {{ formatFee(Number(request.attestation_fee)) }}
                                            </p>
                                        </td>
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
                                                    v-if="request.approval_progress"
                                                    size="sm"
                                                    variant="outline"
                                                    @click="toggleApprovalDetails(request.id)"
                                                >
                                                    {{ isApprovalDetailsOpen(request.id) ? t('common.close') : t('leaves.request_details') }}
                                                </Button>
                                                <Button
                                                    v-if="request.has_certificate"
                                                    size="sm"
                                                    variant="outline"
                                                    as-child
                                                >
                                                    <a
                                                        :href="route('employee.salary-certificates.preview', request.id)"
                                                        target="_blank"
                                                        rel="noopener"
                                                    >
                                                        <Eye class="mr-1 h-3.5 w-3.5" />
                                                        {{ t('salary_certificates.view_certificate') }}
                                                    </a>
                                                </Button>
                                                <Button
                                                    v-if="request.has_certificate"
                                                    size="sm"
                                                    variant="outline"
                                                    as-child
                                                >
                                                    <a :href="route('employee.salary-certificates.download', request.id)">
                                                        <Download class="mr-1 h-3.5 w-3.5" />
                                                        {{ t('salary_certificates.download_certificate') }}
                                                    </a>
                                                </Button>
                                                <Button
                                                    v-if="request.status === 'pending'"
                                                    size="sm"
                                                    variant="outline"
                                                    class="text-destructive hover:text-destructive"
                                                    :disabled="cancellingRequestId === request.id"
                                                    @click="cancelRequest(request.id)"
                                                >
                                                    {{ t('salary_certificates.cancel_request') }}
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr
                                        v-if="request.approval_progress && isApprovalDetailsOpen(request.id)"
                                        class="border-b last:border-0 bg-muted/20"
                                    >
                                        <td colspan="6" class="px-2 pb-4 pt-1">
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

            <Dialog :open="createFormOpen" @update:open="(open: boolean) => (open ? openCreateForm() : closeCreateForm())">
                <DialogContent class="max-w-lg max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>{{ t('salary_certificates.request_new') }}</DialogTitle>
                        <DialogDescription>{{ t('salary_certificates.request_description') }}</DialogDescription>
                    </DialogHeader>

                    <form class="space-y-4" @submit.prevent="submit">
                        <div class="space-y-2">
                            <Label for="purpose">{{ t('salary_certificates.purpose') }}</Label>
                            <Input
                                id="purpose"
                                v-model="form.purpose"
                                required
                            />
                            <p v-if="form.errors.purpose" class="text-sm text-red-600">{{ form.errors.purpose }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="addressed_to">{{ t('salary_certificates.addressed_to') }}</Label>
                            <Input
                                id="addressed_to"
                                v-model="form.addressed_to"
                                :placeholder="t('salary_certificates.addressed_to_placeholder')"
                                required
                            />
                            <p v-if="form.errors.addressed_to" class="text-sm text-red-600">{{ form.errors.addressed_to }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label>{{ t('salary_certificates.attestation_type') }}</Label>
                            <div class="space-y-2">
                                <label
                                    v-for="type in attestationOptions"
                                    :key="type"
                                    class="flex items-start gap-3 rounded-md border p-3 cursor-pointer"
                                    :class="form.attestation_type === type ? 'border-primary bg-muted/40' : ''"
                                >
                                    <input
                                        v-model="form.attestation_type"
                                        type="radio"
                                        :value="type"
                                        class="mt-1"
                                        required
                                    >
                                    <span class="flex flex-col gap-1">
                                        <span class="font-medium">{{ attestationLabel(type) }}</span>
                                        <span
                                            v-if="type === 'chamber' && chamberFee > 0"
                                            class="text-sm text-muted-foreground"
                                        >
                                            {{ t('salary_certificates.chamber_fee_notice', { amount: formatFee(chamberFee) }) }}
                                        </span>
                                    </span>
                                </label>
                            </div>
                            <p v-if="form.errors.attestation_type" class="text-sm text-red-600">{{ form.errors.attestation_type }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="language">{{ t('salary_certificates.language') }}</Label>
                            <select
                                id="language"
                                v-model="form.language"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            >
                                <option v-for="lang in languages" :key="lang" :value="lang">
                                    {{ languageLabel(lang) }}
                                </option>
                            </select>
                            <p v-if="form.errors.language" class="text-sm text-red-600">{{ form.errors.language }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="notes">{{ t('salary_certificates.notes') }}</Label>
                            <textarea
                                id="notes"
                                v-model="form.notes"
                                rows="3"
                                class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            />
                            <p v-if="form.errors.notes" class="text-sm text-red-600">{{ form.errors.notes }}</p>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" @click="closeCreateForm">
                                {{ t('common.cancel') }}
                            </Button>
                            <Button type="submit" :disabled="form.processing">
                                {{ form.processing ? t('common.saving') : t('salary_certificates.submit_request') }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>

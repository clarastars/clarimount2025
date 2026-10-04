<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

interface ApprovalStep {
    id: number;
    title: string;
    sort_order: number;
    team_name: string | null;
    approved_at: string | null;
    approver_name: string | null;
    status: string;
    can_approve: boolean;
    can_reject: boolean;
    waiting_previous: boolean;
}

interface OffboardingItem {
    id: number;
    title: string;
    attachment_mode: string;
    status: string;
    sort_order: number;
    attachment_path: string | null;
    attachment_url: string | null;
    completed_at: string | null;
    approval_steps: ApprovalStep[];
    can_act: boolean;
    requires_attachment_on_approve: boolean;
    allows_attachment: boolean;
}

interface Props {
    employee: { id: number; full_name: string; employee_id: string | null };
    offboarding_case: {
        id: number;
        status: string;
        started_at: string | null;
        cleared_at: string | null;
        termination_date: string | null;
        starter_name: string | null;
        reviewer_name: string | null;
        review_notes: string | null;
    };
    items: OffboardingItem[];
    can_see_all_items: boolean;
    has_clearance_workflow: boolean;
    clearance_steps: ApprovalStep[];
    status?: string | null;
}

const props = defineProps<Props>();
const { t } = useI18n();

const breadcrumbs = computed((): BreadcrumbItem[] => [
    { title: t('nav.dashboard'), href: '/dashboard' },
    { title: t('employees.title'), href: '/employees' },
    { title: props.employee.full_name, href: route('employees.show', props.employee.id) },
    { title: t('offboarding.title'), href: route('employees.offboarding.show', [props.employee.id, props.offboarding_case.id]) },
]);

const processing = ref(false);
const rejectReason = ref('');
const rejectingKey = ref<string | null>(null);
const attachmentFiles = ref<Record<number, File | null>>({});

const caseStatusLabel = computed(() => t(`offboarding.status_${props.offboarding_case.status}`));

const approveItemStep = (item: OffboardingItem, stepId: number) => {
    processing.value = true;

    const body: Record<string, File | string> = {};
    const file = attachmentFiles.value[item.id];
    if (file) {
        body.attachment = file;
    }

    router.post(
        route('employees.offboarding.approve-item-step', [
            props.employee.id,
            props.offboarding_case.id,
            item.id,
            stepId,
        ]),
        body,
        {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
            },
        },
    );
};

const rejectItemStep = (itemId: number, stepId: number) => {
    processing.value = true;
    router.post(
        route('employees.offboarding.reject-item-step', [
            props.employee.id,
            props.offboarding_case.id,
            itemId,
            stepId,
        ]),
        { reason: rejectReason.value },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
                rejectingKey.value = null;
                rejectReason.value = '';
            },
        },
    );
};

const approveClearanceStep = (stepId: number) => {
    processing.value = true;
    router.post(
        route('employees.offboarding.approve-clearance-step', [
            props.employee.id,
            props.offboarding_case.id,
            stepId,
        ]),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
            },
        },
    );
};

const rejectClearanceStep = (stepId: number) => {
    processing.value = true;
    router.post(
        route('employees.offboarding.reject-clearance-step', [
            props.employee.id,
            props.offboarding_case.id,
            stepId,
        ]),
        { reason: rejectReason.value },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
                rejectingKey.value = null;
                rejectReason.value = '';
            },
        },
    );
};

const onFileChange = (itemId: number, event: Event) => {
    const input = event.target as HTMLInputElement;
    attachmentFiles.value[itemId] = input.files?.[0] ?? null;
};

const stepTone = (status: string) => {
    if (status === 'approved') return 'border-emerald-200 bg-emerald-50';
    if (status === 'current') return 'border-amber-200 bg-amber-50';
    return 'border-muted bg-muted/30';
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('offboarding.title')" />

        <div class="max-w-5xl mx-auto px-4 py-8 space-y-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <HeadingSmall
                    :title="t('offboarding.title')"
                    :description="employee.full_name"
                />
                <Button variant="outline" as-child>
                    <Link :href="route('employees.show', employee.id)">{{ t('common.back') }}</Link>
                </Button>
            </div>

            <p v-if="status" class="text-sm text-green-600">{{ status }}</p>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('offboarding.case_details') }}</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-3 sm:grid-cols-2 text-sm">
                    <div><span class="text-muted-foreground">{{ t('offboarding.status') }}:</span> {{ caseStatusLabel }}</div>
                    <div><span class="text-muted-foreground">{{ t('offboarding.termination_date') }}:</span> {{ offboarding_case.termination_date || '—' }}</div>
                    <div><span class="text-muted-foreground">{{ t('offboarding.started_by') }}:</span> {{ offboarding_case.starter_name || '—' }}</div>
                    <div><span class="text-muted-foreground">{{ t('offboarding.started_at') }}:</span> {{ offboarding_case.started_at || '—' }}</div>
                </CardContent>
            </Card>

            <div v-if="!can_see_all_items" class="text-sm text-muted-foreground">
                {{ t('offboarding.partial_visibility_notice') }}
            </div>

            <Card v-for="item in items" :key="item.id">
                <CardHeader>
                    <CardTitle class="flex flex-wrap items-center gap-2">
                        <span>{{ item.title }}</span>
                        <span class="text-xs font-normal text-muted-foreground">
                            ({{ t(`offboarding.item_status_${item.status}`) }} · {{ t(`settings.offboarding_attachment_${item.attachment_mode}`) }})
                        </span>
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div v-if="item.attachment_url" class="text-sm">
                        <a :href="item.attachment_url" target="_blank" class="text-blue-600 underline">
                            {{ t('offboarding.view_attachment') }}
                        </a>
                    </div>

                    <div
                        v-for="step in item.approval_steps"
                        :key="step.id"
                        class="rounded-lg border p-3 space-y-3"
                        :class="stepTone(step.status)"
                    >
                        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="font-medium">{{ step.title }}</div>
                                <div class="text-xs text-muted-foreground">
                                    {{ step.team_name || '—' }}
                                    <span v-if="step.approver_name"> · {{ step.approver_name }}</span>
                                    <span v-if="step.approved_at"> · {{ step.approved_at }}</span>
                                </div>
                            </div>
                            <div class="text-xs uppercase tracking-wide">{{ t(`offboarding.step_${step.status}`) }}</div>
                        </div>

                        <div v-if="step.can_approve" class="space-y-3">
                            <div v-if="item.allows_attachment && item.status === 'pending'" class="space-y-2">
                                <Label>{{ t('offboarding.attachment') }}{{ item.requires_attachment_on_approve ? ' *' : '' }}</Label>
                                <input type="file" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-sm" @change="onFileChange(item.id, $event)" />
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <Button type="button" size="sm" :disabled="processing" @click="approveItemStep(item, step.id)">
                                    {{ t('offboarding.approve') }}
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    :disabled="processing"
                                    @click="rejectingKey = `item-${item.id}-${step.id}`"
                                >
                                    {{ t('offboarding.reject') }}
                                </Button>
                            </div>
                            <div v-if="rejectingKey === `item-${item.id}-${step.id}`" class="space-y-2">
                                <textarea v-model="rejectReason" class="w-full rounded-md border p-2 text-sm" rows="3" :placeholder="t('offboarding.reject_reason')" />
                                <div class="flex gap-2">
                                    <Button type="button" size="sm" variant="destructive" :disabled="!rejectReason || processing" @click="rejectItemStep(item.id, step.id)">
                                        {{ t('offboarding.confirm_reject') }}
                                    </Button>
                                    <Button type="button" size="sm" variant="outline" @click="rejectingKey = null">{{ t('common.cancel') }}</Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card v-if="can_see_all_items && (has_clearance_workflow || clearance_steps.length)">
                <CardHeader>
                    <CardTitle>{{ t('offboarding.clearance_chain') }}</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <p v-if="offboarding_case.status === 'in_progress'" class="text-sm text-muted-foreground">
                        {{ t('offboarding.clearance_waiting_items') }}
                    </p>

                    <div
                        v-for="step in clearance_steps"
                        :key="step.id"
                        class="rounded-lg border p-3 space-y-3"
                        :class="stepTone(step.status)"
                    >
                        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="font-medium">{{ step.title }}</div>
                                <div class="text-xs text-muted-foreground">
                                    {{ step.team_name || '—' }}
                                    <span v-if="step.approver_name"> · {{ step.approver_name }}</span>
                                </div>
                            </div>
                            <div class="text-xs uppercase tracking-wide">{{ t(`offboarding.step_${step.status}`) }}</div>
                        </div>

                        <div v-if="step.can_approve" class="space-y-3">
                            <div class="flex flex-wrap gap-2">
                                <Button type="button" size="sm" :disabled="processing" @click="approveClearanceStep(step.id)">
                                    {{ t('offboarding.approve') }}
                                </Button>
                                <Button type="button" size="sm" variant="outline" :disabled="processing" @click="rejectingKey = `clearance-${step.id}`">
                                    {{ t('offboarding.reject') }}
                                </Button>
                            </div>
                            <div v-if="rejectingKey === `clearance-${step.id}`" class="space-y-2">
                                <textarea v-model="rejectReason" class="w-full rounded-md border p-2 text-sm" rows="3" />
                                <div class="flex gap-2">
                                    <Button type="button" size="sm" variant="destructive" :disabled="!rejectReason || processing" @click="rejectClearanceStep(step.id)">
                                        {{ t('offboarding.confirm_reject') }}
                                    </Button>
                                    <Button type="button" size="sm" variant="outline" @click="rejectingKey = null">{{ t('common.cancel') }}</Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

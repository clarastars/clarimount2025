<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';

interface CompanyItem {
    id: number;
    name_en: string;
    name_ar: string;
}

interface ChecklistItem {
    id: number;
    title: string;
    sort_order: number;
    attachment_mode: 'none' | 'optional' | 'required';
    is_active: boolean;
    active_steps_count: number;
    can_delete: boolean;
}

interface StepItem {
    id: number;
    title: string;
    sort_order: number;
    team_id: number | null;
    team_name: string | null;
    is_active: boolean;
    can_delete: boolean;
}

interface TeamItem {
    id: number;
    name: string;
    description?: string | null;
}

interface Props {
    companies: CompanyItem[];
    defaultChecklist: ChecklistItem[];
    defaultClearanceSteps: StepItem[];
    teams: TeamItem[];
    status?: string | null;
}

const props = defineProps<Props>();
const page = usePage();
const { t, locale } = useI18n();

const breadcrumbs = computed((): BreadcrumbItem[] => [
    {
        title: t('settings.offboarding'),
        href: '/settings/offboarding',
    },
]);

const defaultsError = computed(() => {
    const errors = page.props.errors as Record<string, string> | undefined;

    return errors?.defaults ?? null;
});

const companyLabel = (company: CompanyItem) => {
    if (locale.value === 'ar' && company.name_ar) {
        return company.name_ar;
    }

    return company.name_en || company.name_ar;
};

const attachmentLabel = (mode: string) => t(`settings.offboarding_attachment_${mode}`);

const canApply = computed(
    () => props.defaultChecklist.some((item) => item.is_active) || props.defaultClearanceSteps.some((step) => step.is_active),
);

const checklistCreateForm = useForm({
    title: '',
    attachment_mode: 'optional' as 'none' | 'optional' | 'required',
});

const editingChecklistId = ref<number | null>(null);
const checklistEditForm = useForm({
    title: '',
    attachment_mode: 'optional' as 'none' | 'optional' | 'required',
    is_active: true,
});

const clearanceCreateForm = useForm({
    title: '',
    team_id: '' as string | number,
});

const editingClearanceId = ref<number | null>(null);
const clearanceEditForm = useForm({
    title: '',
    team_id: '' as string | number,
    is_active: true,
});

const startChecklistEdit = (item: ChecklistItem) => {
    editingChecklistId.value = item.id;
    checklistEditForm.title = item.title;
    checklistEditForm.attachment_mode = item.attachment_mode;
    checklistEditForm.is_active = item.is_active;
};

const cancelChecklistEdit = () => {
    editingChecklistId.value = null;
    checklistEditForm.reset();
};

const submitChecklistCreate = () => {
    checklistCreateForm.post(route('settings.offboarding.defaults.checklist.store'), {
        preserveScroll: true,
        onSuccess: () => checklistCreateForm.reset('title'),
    });
};

const submitChecklistEdit = (id: number) => {
    checklistEditForm.put(route('settings.offboarding.defaults.checklist.update', id), {
        preserveScroll: true,
        onSuccess: () => {
            editingChecklistId.value = null;
            checklistEditForm.reset();
        },
    });
};

const deleteChecklistItem = (item: ChecklistItem) => {
    if (!window.confirm(t('settings.offboarding_checklist_delete_confirm'))) {
        return;
    }

    router.delete(route('settings.offboarding.defaults.checklist.destroy', item.id), {
        preserveScroll: true,
    });
};

const moveChecklistItem = (index: number, direction: -1 | 1) => {
    const targetIndex = index + direction;
    if (targetIndex < 0 || targetIndex >= props.defaultChecklist.length) {
        return;
    }

    const orderedIds = props.defaultChecklist.map((item) => item.id);
    const temp = orderedIds[index];
    orderedIds[index] = orderedIds[targetIndex];
    orderedIds[targetIndex] = temp;

    router.post(
        route('settings.offboarding.defaults.checklist.reorder'),
        { ordered_ids: orderedIds },
        { preserveScroll: true },
    );
};

const startClearanceEdit = (step: StepItem) => {
    editingClearanceId.value = step.id;
    clearanceEditForm.title = step.title;
    clearanceEditForm.team_id = step.team_id ?? '';
    clearanceEditForm.is_active = step.is_active;
};

const cancelClearanceEdit = () => {
    editingClearanceId.value = null;
    clearanceEditForm.reset();
};

const submitClearanceCreate = () => {
    clearanceCreateForm.post(route('settings.offboarding.defaults.clearance.store'), {
        preserveScroll: true,
        onSuccess: () => clearanceCreateForm.reset(),
    });
};

const submitClearanceEdit = (id: number) => {
    clearanceEditForm.put(route('settings.offboarding.defaults.clearance.update', id), {
        preserveScroll: true,
        onSuccess: () => {
            editingClearanceId.value = null;
            clearanceEditForm.reset();
        },
    });
};

const deleteClearanceStep = (step: StepItem) => {
    if (!window.confirm(t('settings.offboarding_clearance_approvals_delete_confirm'))) {
        return;
    }

    router.delete(route('settings.offboarding.defaults.clearance.destroy', step.id), {
        preserveScroll: true,
    });
};

const moveClearanceStep = (index: number, direction: -1 | 1) => {
    const targetIndex = index + direction;
    if (targetIndex < 0 || targetIndex >= props.defaultClearanceSteps.length) {
        return;
    }

    const orderedIds = props.defaultClearanceSteps.map((step) => step.id);
    const temp = orderedIds[index];
    orderedIds[index] = orderedIds[targetIndex];
    orderedIds[targetIndex] = temp;

    router.post(
        route('settings.offboarding.defaults.clearance.reorder'),
        { ordered_ids: orderedIds },
        { preserveScroll: true },
    );
};

const applyToAll = () => {
    if (!window.confirm(t('settings.offboarding_defaults_apply_confirm'))) {
        return;
    }

    router.post(route('settings.offboarding.defaults.apply-all'), {}, {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('settings.offboarding')" />

        <SettingsLayout>
            <div class="space-y-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <HeadingSmall
                        :title="t('settings.offboarding')"
                        :description="t('settings.offboarding_company_picker_description')"
                    />
                    <Button type="button" variant="secondary" :disabled="!canApply" @click="applyToAll">
                        {{ t('settings.offboarding_defaults_apply_all') }}
                    </Button>
                </div>

                <p v-if="status" class="text-sm text-green-600 dark:text-green-400">{{ status }}</p>
                <InputError v-if="defaultsError" :message="defaultsError" />

                <Card>
                    <CardHeader>
                        <CardTitle>{{ t('settings.offboarding_defaults_checklist_title') }}</CardTitle>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ t('settings.offboarding_defaults_checklist_description') }}
                        </p>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <form class="space-y-4 rounded-lg border p-4" @submit.prevent="submitChecklistCreate">
                            <div class="font-medium">{{ t('settings.offboarding_checklist_add') }}</div>
                            <div class="space-y-2">
                                <Label for="default-checklist-title">{{ t('settings.offboarding_item_title') }}</Label>
                                <Input id="default-checklist-title" v-model="checklistCreateForm.title" required />
                                <InputError :message="checklistCreateForm.errors.title" />
                            </div>
                            <div class="space-y-2">
                                <Label for="default-checklist-attachment">{{ t('settings.offboarding_attachment_mode') }}</Label>
                                <select
                                    id="default-checklist-attachment"
                                    v-model="checklistCreateForm.attachment_mode"
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    required
                                >
                                    <option value="none">{{ t('settings.offboarding_attachment_none') }}</option>
                                    <option value="optional">{{ t('settings.offboarding_attachment_optional') }}</option>
                                    <option value="required">{{ t('settings.offboarding_attachment_required') }}</option>
                                </select>
                                <InputError :message="checklistCreateForm.errors.attachment_mode" />
                            </div>
                            <Button type="submit" :disabled="checklistCreateForm.processing">
                                {{ t('common.add') }}
                            </Button>
                        </form>

                        <div class="space-y-4">
                            <div class="font-medium">{{ t('settings.offboarding_checklist_list') }}</div>
                            <p v-if="defaultChecklist.length === 0" class="text-sm text-muted-foreground">
                                {{ t('settings.offboarding_defaults_checklist_empty') }}
                            </p>

                            <div
                                v-for="(item, index) in defaultChecklist"
                                :key="item.id"
                                class="rounded-lg border p-4 space-y-3"
                            >
                                <div v-if="editingChecklistId === item.id" class="space-y-3">
                                    <div class="space-y-2">
                                        <Label>{{ t('settings.offboarding_item_title') }}</Label>
                                        <Input v-model="checklistEditForm.title" required />
                                        <InputError :message="checklistEditForm.errors.title" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>{{ t('settings.offboarding_attachment_mode') }}</Label>
                                        <select
                                            v-model="checklistEditForm.attachment_mode"
                                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                            required
                                        >
                                            <option value="none">{{ t('settings.offboarding_attachment_none') }}</option>
                                            <option value="optional">{{ t('settings.offboarding_attachment_optional') }}</option>
                                            <option value="required">{{ t('settings.offboarding_attachment_required') }}</option>
                                        </select>
                                    </div>
                                    <label class="flex items-center gap-2 text-sm">
                                        <input v-model="checklistEditForm.is_active" type="checkbox" class="rounded border-gray-300" />
                                        {{ t('settings.active') }}
                                    </label>
                                    <div class="flex gap-2">
                                        <Button type="button" :disabled="checklistEditForm.processing" @click="submitChecklistEdit(item.id)">
                                            {{ t('common.save') }}
                                        </Button>
                                        <Button type="button" variant="outline" @click="cancelChecklistEdit">
                                            {{ t('common.cancel') }}
                                        </Button>
                                    </div>
                                </div>

                                <div v-else class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <div class="font-medium">
                                            {{ index + 1 }}. {{ item.title }}
                                            <span v-if="!item.is_active" class="text-xs text-muted-foreground">({{ t('settings.inactive') }})</span>
                                        </div>
                                        <div class="text-sm text-muted-foreground">
                                            {{ attachmentLabel(item.attachment_mode) }}
                                            ·
                                            {{ t('settings.offboarding_steps_count', { count: item.active_steps_count }) }}
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <Button type="button" size="sm" variant="outline" :disabled="index === 0" @click="moveChecklistItem(index, -1)">
                                            {{ t('settings.advance_approvals_move_up') }}
                                        </Button>
                                        <Button type="button" size="sm" variant="outline" :disabled="index === defaultChecklist.length - 1" @click="moveChecklistItem(index, 1)">
                                            {{ t('settings.advance_approvals_move_down') }}
                                        </Button>
                                        <Button type="button" size="sm" variant="outline" as-child>
                                            <Link :href="route('settings.offboarding.defaults.item-approvals.index', item.id)">
                                                {{ t('settings.offboarding_manage_item_approvals') }}
                                            </Link>
                                        </Button>
                                        <Button type="button" size="sm" variant="outline" @click="startChecklistEdit(item)">
                                            {{ t('common.edit') }}
                                        </Button>
                                        <Button type="button" size="sm" variant="destructive" @click="deleteChecklistItem(item)">
                                            {{ t('common.delete') }}
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{{ t('settings.offboarding_defaults_clearance_title') }}</CardTitle>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ t('settings.offboarding_defaults_clearance_description') }}
                        </p>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <form class="space-y-4 rounded-lg border p-4" @submit.prevent="submitClearanceCreate">
                            <div class="font-medium">{{ t('settings.offboarding_clearance_approvals_add_step') }}</div>
                            <div class="space-y-2">
                                <Label for="default-clearance-title">{{ t('settings.offboarding_clearance_approval_step_title') }}</Label>
                                <Input id="default-clearance-title" v-model="clearanceCreateForm.title" required />
                                <InputError :message="clearanceCreateForm.errors.title" />
                            </div>
                            <div class="space-y-2">
                                <Label for="default-clearance-team">{{ t('settings.offboarding_clearance_approval_team') }}</Label>
                                <select
                                    id="default-clearance-team"
                                    v-model="clearanceCreateForm.team_id"
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    required
                                >
                                    <option value="" disabled>{{ t('settings.offboarding_clearance_approval_team_placeholder') }}</option>
                                    <option v-for="team in teams" :key="team.id" :value="team.id">
                                        {{ team.name }}
                                    </option>
                                </select>
                                <InputError :message="clearanceCreateForm.errors.team_id" />
                            </div>
                            <Button type="submit" :disabled="clearanceCreateForm.processing">
                                {{ t('common.add') }}
                            </Button>
                        </form>

                        <div class="space-y-4">
                            <div class="font-medium">{{ t('settings.offboarding_clearance_approvals_steps_list') }}</div>
                            <p v-if="defaultClearanceSteps.length === 0" class="text-sm text-muted-foreground">
                                {{ t('settings.offboarding_defaults_clearance_empty') }}
                            </p>

                            <div
                                v-for="(step, index) in defaultClearanceSteps"
                                :key="step.id"
                                class="rounded-lg border p-4 space-y-3"
                            >
                                <div v-if="editingClearanceId === step.id" class="space-y-3">
                                    <div class="space-y-2">
                                        <Label>{{ t('settings.offboarding_clearance_approval_step_title') }}</Label>
                                        <Input v-model="clearanceEditForm.title" required />
                                        <InputError :message="clearanceEditForm.errors.title" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>{{ t('settings.offboarding_clearance_approval_team') }}</Label>
                                        <select
                                            v-model="clearanceEditForm.team_id"
                                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                            required
                                        >
                                            <option value="" disabled>{{ t('settings.offboarding_clearance_approval_team_placeholder') }}</option>
                                            <option v-for="team in teams" :key="team.id" :value="team.id">
                                                {{ team.name }}
                                            </option>
                                        </select>
                                    </div>
                                    <label class="flex items-center gap-2 text-sm">
                                        <input v-model="clearanceEditForm.is_active" type="checkbox" class="rounded border-gray-300" />
                                        {{ t('settings.active') }}
                                    </label>
                                    <div class="flex gap-2">
                                        <Button type="button" :disabled="clearanceEditForm.processing" @click="submitClearanceEdit(step.id)">
                                            {{ t('common.save') }}
                                        </Button>
                                        <Button type="button" variant="outline" @click="cancelClearanceEdit">
                                            {{ t('common.cancel') }}
                                        </Button>
                                    </div>
                                </div>

                                <div v-else class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <div class="font-medium">
                                            {{ index + 1 }}. {{ step.title }}
                                            <span v-if="!step.is_active" class="text-xs text-muted-foreground">({{ t('settings.inactive') }})</span>
                                        </div>
                                        <div class="text-sm text-muted-foreground">
                                            {{ t('settings.offboarding_clearance_approval_team') }}: {{ step.team_name || '-' }}
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <Button type="button" size="sm" variant="outline" :disabled="index === 0" @click="moveClearanceStep(index, -1)">
                                            {{ t('settings.advance_approvals_move_up') }}
                                        </Button>
                                        <Button type="button" size="sm" variant="outline" :disabled="index === defaultClearanceSteps.length - 1" @click="moveClearanceStep(index, 1)">
                                            {{ t('settings.advance_approvals_move_down') }}
                                        </Button>
                                        <Button type="button" size="sm" variant="outline" @click="startClearanceEdit(step)">
                                            {{ t('common.edit') }}
                                        </Button>
                                        <Button type="button" size="sm" variant="destructive" @click="deleteClearanceStep(step)">
                                            {{ t('common.delete') }}
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{{ t('settings.offboarding_select_company') }}</CardTitle>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ t('settings.offboarding_per_company_hint') }}
                        </p>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <p v-if="companies.length === 0" class="text-sm text-muted-foreground">
                            {{ t('settings.offboarding_no_companies') }}
                        </p>

                        <div
                            v-for="company in companies"
                            :key="company.id"
                            class="flex flex-col gap-3 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="font-medium">{{ companyLabel(company) }}</div>
                            <div class="flex flex-wrap gap-2">
                                <Button variant="outline" as-child>
                                    <Link :href="route('companies.offboarding-checklist.index', company.id)">
                                        {{ t('settings.offboarding_manage_checklist') }}
                                    </Link>
                                </Button>
                                <Button variant="outline" as-child>
                                    <Link :href="route('companies.offboarding-clearance-approvals.index', company.id)">
                                        {{ t('settings.offboarding_manage_clearance') }}
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>

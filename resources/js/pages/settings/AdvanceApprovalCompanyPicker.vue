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
    defaultSteps: StepItem[];
    teams: TeamItem[];
    status?: string | null;
}

const props = defineProps<Props>();
const page = usePage();
const { t, locale } = useI18n();

const defaultsError = computed(() => {
    const errors = page.props.errors as Record<string, string> | undefined;

    return errors?.defaults ?? null;
});

const breadcrumbs = computed((): BreadcrumbItem[] => [
    {
        title: t('settings.advance_approvals'),
        href: '/settings/advance-approvals',
    },
]);

const companyLabel = (company: CompanyItem) => {
    if (locale.value === 'ar' && company.name_ar) {
        return company.name_ar;
    }

    return company.name_en || company.name_ar;
};

const createForm = useForm({
    title: '',
    team_id: '' as string | number,
});

const editingStepId = ref<number | null>(null);
const editForm = useForm({
    title: '',
    team_id: '' as string | number,
    is_active: true,
});

const startEdit = (step: StepItem) => {
    editingStepId.value = step.id;
    editForm.title = step.title;
    editForm.team_id = step.team_id ?? '';
    editForm.is_active = step.is_active;
};

const cancelEdit = () => {
    editingStepId.value = null;
    editForm.reset();
};

const submitCreate = () => {
    createForm.post(route('settings.advance-approvals.defaults.store'), {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
        },
    });
};

const submitEdit = (stepId: number) => {
    editForm.put(route('settings.advance-approvals.defaults.update', stepId), {
        preserveScroll: true,
        onSuccess: () => {
            editingStepId.value = null;
            editForm.reset();
        },
    });
};

const deleteStep = (step: StepItem) => {
    if (!window.confirm(t('settings.advance_approvals_delete_confirm'))) {
        return;
    }

    router.delete(route('settings.advance-approvals.defaults.destroy', step.id), {
        preserveScroll: true,
    });
};

const moveStep = (index: number, direction: -1 | 1) => {
    const targetIndex = index + direction;
    if (targetIndex < 0 || targetIndex >= props.defaultSteps.length) {
        return;
    }

    const orderedIds = props.defaultSteps.map((step) => step.id);
    const temp = orderedIds[index];
    orderedIds[index] = orderedIds[targetIndex];
    orderedIds[targetIndex] = temp;

    router.post(
        route('settings.advance-approvals.defaults.reorder'),
        { ordered_ids: orderedIds },
        { preserveScroll: true },
    );
};

const applyToAll = () => {
    if (!window.confirm(t('settings.advance_approvals_defaults_apply_confirm'))) {
        return;
    }

    router.post(route('settings.advance-approvals.defaults.apply-all'), {}, {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('settings.advance_approvals')" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall
                    :title="t('settings.advance_approvals')"
                    :description="t('settings.advance_approvals_company_picker_description')"
                />

                <p v-if="status" class="text-sm text-green-600 dark:text-green-400">{{ status }}</p>
                <InputError v-if="defaultsError" :message="defaultsError" />

                <Card>
                    <CardHeader class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <CardTitle>{{ t('settings.advance_approvals_defaults_title') }}</CardTitle>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ t('settings.advance_approvals_defaults_description') }}
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="secondary"
                            :disabled="defaultSteps.length === 0"
                            @click="applyToAll"
                        >
                            {{ t('settings.advance_approvals_defaults_apply_all') }}
                        </Button>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <form class="space-y-4 rounded-lg border p-4" @submit.prevent="submitCreate">
                            <div class="font-medium">{{ t('settings.advance_approvals_add_step') }}</div>
                            <div class="space-y-2">
                                <Label for="default-new-title">{{ t('settings.advance_approval_step_title') }}</Label>
                                <Input id="default-new-title" v-model="createForm.title" required />
                                <InputError :message="createForm.errors.title" />
                            </div>
                            <div class="space-y-2">
                                <Label for="default-new-team">{{ t('settings.advance_approval_team') }}</Label>
                                <select
                                    id="default-new-team"
                                    v-model="createForm.team_id"
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    required
                                >
                                    <option value="" disabled>{{ t('settings.advance_approval_team_placeholder') }}</option>
                                    <option v-for="team in teams" :key="team.id" :value="team.id">
                                        {{ team.name }}
                                    </option>
                                </select>
                                <InputError :message="createForm.errors.team_id" />
                            </div>
                            <Button type="submit" :disabled="createForm.processing">
                                {{ t('common.add') }}
                            </Button>
                        </form>

                        <div class="space-y-4">
                            <div class="font-medium">{{ t('settings.advance_approvals_steps_list') }}</div>
                            <p v-if="defaultSteps.length === 0" class="text-sm text-muted-foreground">
                                {{ t('settings.advance_approvals_defaults_empty') }}
                            </p>

                            <div
                                v-for="(step, index) in defaultSteps"
                                :key="step.id"
                                class="rounded-lg border p-4 space-y-3"
                            >
                                <div v-if="editingStepId === step.id" class="space-y-3">
                                    <div class="space-y-2">
                                        <Label>{{ t('settings.advance_approval_step_title') }}</Label>
                                        <Input v-model="editForm.title" required />
                                        <InputError :message="editForm.errors.title" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>{{ t('settings.advance_approval_team') }}</Label>
                                        <select
                                            v-model="editForm.team_id"
                                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                            required
                                        >
                                            <option value="" disabled>{{ t('settings.advance_approval_team_placeholder') }}</option>
                                            <option v-for="team in teams" :key="team.id" :value="team.id">
                                                {{ team.name }}
                                            </option>
                                        </select>
                                        <InputError :message="editForm.errors.team_id" />
                                    </div>
                                    <label class="flex items-center gap-2 text-sm">
                                        <input v-model="editForm.is_active" type="checkbox" class="rounded border-gray-300" />
                                        {{ t('settings.active') }}
                                    </label>
                                    <div class="flex gap-2">
                                        <Button type="button" :disabled="editForm.processing" @click="submitEdit(step.id)">
                                            {{ t('common.save') }}
                                        </Button>
                                        <Button type="button" variant="outline" @click="cancelEdit">
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
                                            {{ t('settings.advance_approval_team') }}: {{ step.team_name || '-' }}
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <Button type="button" size="sm" variant="outline" :disabled="index === 0" @click="moveStep(index, -1)">
                                            {{ t('settings.advance_approvals_move_up') }}
                                        </Button>
                                        <Button type="button" size="sm" variant="outline" :disabled="index === defaultSteps.length - 1" @click="moveStep(index, 1)">
                                            {{ t('settings.advance_approvals_move_down') }}
                                        </Button>
                                        <Button type="button" size="sm" variant="outline" @click="startEdit(step)">
                                            {{ t('common.edit') }}
                                        </Button>
                                        <Button type="button" size="sm" variant="destructive" @click="deleteStep(step)">
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
                        <CardTitle>{{ t('settings.advance_approvals_select_company') }}</CardTitle>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ t('settings.advance_approvals_per_company_hint') }}
                        </p>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <p v-if="companies.length === 0" class="text-sm text-muted-foreground">
                            {{ t('settings.advance_approvals_no_companies') }}
                        </p>

                        <div
                            v-for="company in companies"
                            :key="company.id"
                            class="flex flex-col gap-3 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="font-medium">{{ companyLabel(company) }}</div>
                            <Button variant="outline" as-child>
                                <Link :href="route('companies.advance-approvals.index', company.id)">
                                    {{ t('settings.advance_approvals_manage') }}
                                </Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>

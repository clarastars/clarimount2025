<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

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

interface CompanyItem {
    id: number;
    name_en: string;
    name_ar: string;
}

interface Props {
    company: CompanyItem;
    steps: StepItem[];
    teams: TeamItem[];
    status?: string | null;
}

const props = defineProps<Props>();
const { t, locale } = useI18n();

const companyName = computed(() => {
    if (locale.value === 'ar' && props.company.name_ar) {
        return props.company.name_ar;
    }

    return props.company.name_en || props.company.name_ar;
});

const breadcrumbs = computed((): BreadcrumbItem[] => [
    {
        title: t('nav.dashboard'),
        href: '/dashboard',
    },
    {
        title: t('companies.title'),
        href: '/companies',
    },
    {
        title: companyName.value,
        href: `/companies/${props.company.id}`,
    },
    {
        title: t('settings.leave_approvals'),
        href: route('companies.leave-approvals.index', props.company.id),
    },
]);

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
    createForm.post(route('companies.leave-approvals.store', props.company.id), {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
        },
    });
};

const submitEdit = (stepId: number) => {
    editForm.put(route('companies.leave-approvals.update', [props.company.id, stepId]), {
        preserveScroll: true,
        onSuccess: () => {
            editingStepId.value = null;
            editForm.reset();
        },
    });
};

const deleteStep = (step: StepItem) => {
    if (!window.confirm(t('settings.leave_approvals_delete_confirm'))) {
        return;
    }

    router.delete(route('companies.leave-approvals.destroy', [props.company.id, step.id]), {
        preserveScroll: true,
    });
};

const moveStep = (index: number, direction: -1 | 1) => {
    const targetIndex = index + direction;
    if (targetIndex < 0 || targetIndex >= props.steps.length) {
        return;
    }

    const orderedIds = props.steps.map((step) => step.id);
    const temp = orderedIds[index];
    orderedIds[index] = orderedIds[targetIndex];
    orderedIds[targetIndex] = temp;

    router.post(route('companies.leave-approvals.reorder', props.company.id), { ordered_ids: orderedIds }, {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('settings.leave_approvals')" />

        <div class="max-w-4xl mx-auto px-4 py-8 space-y-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <HeadingSmall
                    :title="t('settings.leave_approvals_for_company', { company: companyName })"
                    :description="t('settings.leave_approvals_description')"
                />
                <Button variant="outline" as-child>
                    <Link :href="route('companies.leaves.index', company.id)">
                        {{ t('leaves.company_leaves_title') }}
                    </Link>
                </Button>
            </div>

            <p v-if="status" class="text-sm text-green-600 dark:text-green-400">{{ status }}</p>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('settings.leave_approvals_add_step') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <form class="space-y-4" @submit.prevent="submitCreate">
                        <div class="space-y-2">
                            <Label for="new-title">{{ t('settings.leave_approval_step_title') }}</Label>
                            <Input id="new-title" v-model="createForm.title" required />
                            <InputError :message="createForm.errors.title" />
                        </div>
                        <div class="space-y-2">
                            <Label for="new-team">{{ t('settings.leave_approval_team') }}</Label>
                            <select
                                id="new-team"
                                v-model="createForm.team_id"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                required
                            >
                                <option value="" disabled>{{ t('settings.leave_approval_team_placeholder') }}</option>
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
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('settings.leave_approvals_steps_list') }}</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <p v-if="steps.length === 0" class="text-sm text-muted-foreground">
                        {{ t('settings.leave_approvals_empty') }}
                    </p>

                    <div
                        v-for="(step, index) in steps"
                        :key="step.id"
                        class="rounded-lg border p-4 space-y-3"
                    >
                        <div v-if="editingStepId === step.id" class="space-y-3">
                            <div class="space-y-2">
                                <Label>{{ t('settings.leave_approval_step_title') }}</Label>
                                <Input v-model="editForm.title" required />
                                <InputError :message="editForm.errors.title" />
                            </div>
                            <div class="space-y-2">
                                <Label>{{ t('settings.leave_approval_team') }}</Label>
                                <select
                                    v-model="editForm.team_id"
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    required
                                >
                                    <option value="" disabled>{{ t('settings.leave_approval_team_placeholder') }}</option>
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
                                <Button type="button" @click="submitEdit(step.id)" :disabled="editForm.processing">
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
                                    {{ t('settings.leave_approval_team') }}: {{ step.team_name || '-' }}
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <Button type="button" size="sm" variant="outline" :disabled="index === 0" @click="moveStep(index, -1)">
                                    {{ t('settings.leave_approvals_move_up') }}
                                </Button>
                                <Button type="button" size="sm" variant="outline" :disabled="index === steps.length - 1" @click="moveStep(index, 1)">
                                    {{ t('settings.leave_approvals_move_down') }}
                                </Button>
                                <Button type="button" size="sm" variant="outline" @click="startEdit(step)">
                                    {{ t('common.edit') }}
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="destructive"
                                    :disabled="!step.can_delete"
                                    @click="deleteStep(step)"
                                >
                                    {{ t('common.delete') }}
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

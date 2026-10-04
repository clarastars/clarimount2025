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
}

interface CompanyItem {
    id: number;
    name_en: string;
    name_ar: string;
}

interface TemplateItem {
    id: number;
    title: string;
}

interface Props {
    company: CompanyItem;
    template: TemplateItem;
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
    { title: t('nav.dashboard'), href: '/dashboard' },
    { title: t('settings.offboarding_checklist'), href: route('companies.offboarding-checklist.index', props.company.id) },
    {
        title: props.template.title,
        href: route('companies.offboarding-item-approvals.index', [props.company.id, props.template.id]),
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
    createForm.post(route('companies.offboarding-item-approvals.store', [props.company.id, props.template.id]), {
        preserveScroll: true,
        onSuccess: () => createForm.reset(),
    });
};

const submitEdit = (stepId: number) => {
    editForm.put(route('companies.offboarding-item-approvals.update', [props.company.id, props.template.id, stepId]), {
        preserveScroll: true,
        onSuccess: () => {
            editingStepId.value = null;
            editForm.reset();
        },
    });
};

const deleteStep = (step: StepItem) => {
    if (!window.confirm(t('settings.offboarding_item_approvals_delete_confirm'))) {
        return;
    }

    router.delete(route('companies.offboarding-item-approvals.destroy', [props.company.id, props.template.id, step.id]), {
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

    router.post(
        route('companies.offboarding-item-approvals.reorder', [props.company.id, props.template.id]),
        { ordered_ids: orderedIds },
        { preserveScroll: true },
    );
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('settings.offboarding_item_approvals')" />

        <div class="max-w-4xl mx-auto px-4 py-8 space-y-6">
            <HeadingSmall
                :title="t('settings.offboarding_item_approvals_for_item', { item: template.title, company: companyName })"
                :description="t('settings.offboarding_item_approvals_description')"
            />

            <p v-if="status" class="text-sm text-green-600 dark:text-green-400">{{ status }}</p>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('settings.offboarding_item_approvals_add_step') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <form class="space-y-4" @submit.prevent="submitCreate">
                        <div class="space-y-2">
                            <Label>{{ t('settings.offboarding_item_approval_step_title') }}</Label>
                            <Input v-model="createForm.title" required />
                            <InputError :message="createForm.errors.title" />
                        </div>
                        <div class="space-y-2">
                            <Label>{{ t('settings.offboarding_item_approval_team') }}</Label>
                            <select
                                v-model="createForm.team_id"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                required
                            >
                                <option value="" disabled>{{ t('settings.offboarding_item_approval_team_placeholder') }}</option>
                                <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                            </select>
                            <InputError :message="createForm.errors.team_id" />
                        </div>
                        <Button type="submit" :disabled="createForm.processing">{{ t('common.add') }}</Button>
                    </form>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('settings.offboarding_item_approvals_steps_list') }}</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <p v-if="steps.length === 0" class="text-sm text-muted-foreground">
                        {{ t('settings.offboarding_item_approvals_empty') }}
                    </p>

                    <div v-for="(step, index) in steps" :key="step.id" class="rounded-lg border p-4 space-y-3">
                        <div v-if="editingStepId === step.id" class="space-y-3">
                            <Input v-model="editForm.title" required />
                            <select v-model="editForm.team_id" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm" required>
                                <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                            </select>
                            <label class="flex items-center gap-2 text-sm">
                                <input v-model="editForm.is_active" type="checkbox" class="rounded border-gray-300" />
                                {{ t('settings.active') }}
                            </label>
                            <div class="flex gap-2">
                                <Button type="button" @click="submitEdit(step.id)">{{ t('common.save') }}</Button>
                                <Button type="button" variant="outline" @click="cancelEdit">{{ t('common.cancel') }}</Button>
                            </div>
                        </div>
                        <div v-else class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="font-medium">{{ index + 1 }}. {{ step.title }}</div>
                                <div class="text-sm text-muted-foreground">{{ step.team_name || '-' }}</div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <Button type="button" size="sm" variant="outline" :disabled="index === 0" @click="moveStep(index, -1)">↑</Button>
                                <Button type="button" size="sm" variant="outline" :disabled="index === steps.length - 1" @click="moveStep(index, 1)">↓</Button>
                                <Button type="button" size="sm" variant="outline" @click="startEdit(step)">{{ t('common.edit') }}</Button>
                                <Button type="button" size="sm" variant="destructive" :disabled="!step.can_delete" @click="deleteStep(step)">{{ t('common.delete') }}</Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Button variant="outline" as-child>
                <Link :href="route('companies.offboarding-checklist.index', company.id)">{{ t('common.back') }}</Link>
            </Button>
        </div>
    </AppLayout>
</template>

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
import SettingsLayout from '@/layouts/settings/Layout.vue';
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

interface ItemInfo {
    id: number;
    title: string;
}

interface Props {
    item: ItemInfo;
    steps: StepItem[];
    teams: TeamItem[];
    status?: string | null;
}

const props = defineProps<Props>();
const { t } = useI18n();

const breadcrumbs = computed((): BreadcrumbItem[] => [
    {
        title: t('settings.offboarding'),
        href: '/settings/offboarding',
    },
    {
        title: props.item.title,
        href: route('settings.offboarding.defaults.item-approvals.index', props.item.id),
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
    createForm.post(route('settings.offboarding.defaults.item-approvals.store', props.item.id), {
        preserveScroll: true,
        onSuccess: () => createForm.reset(),
    });
};

const submitEdit = (stepId: number) => {
    editForm.put(route('settings.offboarding.defaults.item-approvals.update', [props.item.id, stepId]), {
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

    router.delete(route('settings.offboarding.defaults.item-approvals.destroy', [props.item.id, step.id]), {
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
        route('settings.offboarding.defaults.item-approvals.reorder', props.item.id),
        { ordered_ids: orderedIds },
        { preserveScroll: true },
    );
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('settings.offboarding_item_approvals')" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall
                    :title="t('settings.offboarding_defaults_item_approvals_title', { item: item.title })"
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
                                <select
                                    v-model="editForm.team_id"
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    required
                                >
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
                                    <div class="font-medium">
                                        {{ index + 1 }}. {{ step.title }}
                                        <span v-if="!step.is_active" class="text-xs text-muted-foreground">({{ t('settings.inactive') }})</span>
                                    </div>
                                    <div class="text-sm text-muted-foreground">{{ step.team_name || '-' }}</div>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <Button type="button" size="sm" variant="outline" :disabled="index === 0" @click="moveStep(index, -1)">
                                        {{ t('settings.advance_approvals_move_up') }}
                                    </Button>
                                    <Button type="button" size="sm" variant="outline" :disabled="index === steps.length - 1" @click="moveStep(index, 1)">
                                        {{ t('settings.advance_approvals_move_down') }}
                                    </Button>
                                    <Button type="button" size="sm" variant="outline" @click="startEdit(step)">{{ t('common.edit') }}</Button>
                                    <Button type="button" size="sm" variant="destructive" @click="deleteStep(step)">{{ t('common.delete') }}</Button>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Button variant="outline" as-child>
                    <Link :href="route('settings.offboarding.index')">{{ t('common.back') }}</Link>
                </Button>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>

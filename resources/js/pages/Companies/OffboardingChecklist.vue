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

interface TemplateItem {
    id: number;
    title: string;
    sort_order: number;
    attachment_mode: 'none' | 'optional' | 'required';
    is_active: boolean;
    active_steps_count: number;
    can_delete: boolean;
}

interface CompanyItem {
    id: number;
    name_en: string;
    name_ar: string;
}

interface Props {
    company: CompanyItem;
    templates: TemplateItem[];
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
    { title: t('settings.offboarding'), href: '/settings/offboarding' },
    {
        title: t('settings.offboarding_checklist_for_company', { company: companyName.value }),
        href: route('companies.offboarding-checklist.index', props.company.id),
    },
]);

const createForm = useForm({
    title: '',
    attachment_mode: 'optional' as 'none' | 'optional' | 'required',
});

const editingId = ref<number | null>(null);
const editForm = useForm({
    title: '',
    attachment_mode: 'optional' as 'none' | 'optional' | 'required',
    is_active: true,
});

const attachmentLabel = (mode: string) => t(`settings.offboarding_attachment_${mode}`);

const startEdit = (item: TemplateItem) => {
    editingId.value = item.id;
    editForm.title = item.title;
    editForm.attachment_mode = item.attachment_mode;
    editForm.is_active = item.is_active;
};

const cancelEdit = () => {
    editingId.value = null;
    editForm.reset();
};

const submitCreate = () => {
    createForm.post(route('companies.offboarding-checklist.store', props.company.id), {
        preserveScroll: true,
        onSuccess: () => createForm.reset('title'),
    });
};

const submitEdit = (id: number) => {
    editForm.put(route('companies.offboarding-checklist.update', [props.company.id, id]), {
        preserveScroll: true,
        onSuccess: () => {
            editingId.value = null;
            editForm.reset();
        },
    });
};

const deleteItem = (item: TemplateItem) => {
    if (!window.confirm(t('settings.offboarding_checklist_delete_confirm'))) {
        return;
    }

    router.delete(route('companies.offboarding-checklist.destroy', [props.company.id, item.id]), {
        preserveScroll: true,
    });
};

const moveItem = (index: number, direction: -1 | 1) => {
    const targetIndex = index + direction;
    if (targetIndex < 0 || targetIndex >= props.templates.length) {
        return;
    }

    const orderedIds = props.templates.map((row) => row.id);
    const temp = orderedIds[index];
    orderedIds[index] = orderedIds[targetIndex];
    orderedIds[targetIndex] = temp;

    router.post(route('companies.offboarding-checklist.reorder', props.company.id), { ordered_ids: orderedIds }, {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('settings.offboarding_checklist')" />

        <div class="max-w-4xl mx-auto px-4 py-8 space-y-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <HeadingSmall
                    :title="t('settings.offboarding_checklist_for_company', { company: companyName })"
                    :description="t('settings.offboarding_checklist_description')"
                />
                <Button variant="outline" as-child>
                    <Link :href="route('companies.offboarding-clearance-approvals.index', company.id)">
                        {{ t('settings.offboarding_manage_clearance') }}
                    </Link>
                </Button>
            </div>

            <p v-if="status" class="text-sm text-green-600 dark:text-green-400">{{ status }}</p>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('settings.offboarding_checklist_add') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <form class="space-y-4" @submit.prevent="submitCreate">
                        <div class="space-y-2">
                            <Label for="new-title">{{ t('settings.offboarding_item_title') }}</Label>
                            <Input id="new-title" v-model="createForm.title" required />
                            <InputError :message="createForm.errors.title" />
                        </div>
                        <div class="space-y-2">
                            <Label for="new-attachment">{{ t('settings.offboarding_attachment_mode') }}</Label>
                            <select
                                id="new-attachment"
                                v-model="createForm.attachment_mode"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            >
                                <option value="none">{{ t('settings.offboarding_attachment_none') }}</option>
                                <option value="optional">{{ t('settings.offboarding_attachment_optional') }}</option>
                                <option value="required">{{ t('settings.offboarding_attachment_required') }}</option>
                            </select>
                            <InputError :message="createForm.errors.attachment_mode" />
                        </div>
                        <Button type="submit" :disabled="createForm.processing">
                            {{ t('common.add') }}
                        </Button>
                    </form>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('settings.offboarding_checklist_list') }}</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <p v-if="templates.length === 0" class="text-sm text-muted-foreground">
                        {{ t('settings.offboarding_checklist_empty') }}
                    </p>

                    <div
                        v-for="(item, index) in templates"
                        :key="item.id"
                        class="rounded-lg border p-4 space-y-3"
                    >
                        <div v-if="editingId === item.id" class="space-y-3">
                            <div class="space-y-2">
                                <Label>{{ t('settings.offboarding_item_title') }}</Label>
                                <Input v-model="editForm.title" required />
                                <InputError :message="editForm.errors.title" />
                            </div>
                            <div class="space-y-2">
                                <Label>{{ t('settings.offboarding_attachment_mode') }}</Label>
                                <select
                                    v-model="editForm.attachment_mode"
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                >
                                    <option value="none">{{ t('settings.offboarding_attachment_none') }}</option>
                                    <option value="optional">{{ t('settings.offboarding_attachment_optional') }}</option>
                                    <option value="required">{{ t('settings.offboarding_attachment_required') }}</option>
                                </select>
                            </div>
                            <label class="flex items-center gap-2 text-sm">
                                <input v-model="editForm.is_active" type="checkbox" class="rounded border-gray-300" />
                                {{ t('settings.active') }}
                            </label>
                            <div class="flex gap-2">
                                <Button type="button" @click="submitEdit(item.id)" :disabled="editForm.processing">
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
                                    {{ index + 1 }}. {{ item.title }}
                                    <span v-if="!item.is_active" class="text-xs text-muted-foreground">({{ t('settings.inactive') }})</span>
                                </div>
                                <div class="text-sm text-muted-foreground">
                                    {{ attachmentLabel(item.attachment_mode) }}
                                    · {{ t('settings.offboarding_steps_count', { count: item.active_steps_count }) }}
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <Button type="button" size="sm" variant="outline" :disabled="index === 0" @click="moveItem(index, -1)">
                                    ↑
                                </Button>
                                <Button type="button" size="sm" variant="outline" :disabled="index === templates.length - 1" @click="moveItem(index, 1)">
                                    ↓
                                </Button>
                                <Button type="button" size="sm" variant="outline" as-child>
                                    <Link :href="route('companies.offboarding-item-approvals.index', [company.id, item.id])">
                                        {{ t('settings.offboarding_manage_item_approvals') }}
                                    </Link>
                                </Button>
                                <Button type="button" size="sm" variant="outline" @click="startEdit(item)">
                                    {{ t('common.edit') }}
                                </Button>
                                <Button type="button" size="sm" variant="destructive" :disabled="!item.can_delete" @click="deleteItem(item)">
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

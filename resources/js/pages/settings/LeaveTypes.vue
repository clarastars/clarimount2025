<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { BreadcrumbItem } from '@/types';

interface LeaveTypeItem {
    id: number;
    key: string;
    name_en: string;
    name_ar: string;
    min_notice_days: number;
    allow_past_dates: boolean;
    sort_order: number;
    is_active: boolean;
}

const props = defineProps<{
    leaveTypes: LeaveTypeItem[];
}>();

const { t } = useI18n();

const breadcrumbs = computed((): BreadcrumbItem[] => [
    {
        title: t('settings.leave_types'),
        href: '/settings/leave-types',
    },
]);

const createForm = useForm({
    name_en: '',
    name_ar: '',
    min_notice_days: 0,
    allow_past_dates: false,
    sort_order: 0,
});

const createDialogOpen = ref(false);
const editingId = ref<number | null>(null);
const editForm = useForm({
    name_en: '',
    name_ar: '',
    min_notice_days: 0,
    allow_past_dates: false,
    sort_order: 0,
});

function createLeaveType() {
    createForm.post(route('settings.leave-types.store'), {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            createForm.allow_past_dates = false;
            createDialogOpen.value = false;
        },
    });
}

function startEdit(item: LeaveTypeItem) {
    editingId.value = item.id;
    editForm.name_en = item.name_en;
    editForm.name_ar = item.name_ar;
    editForm.min_notice_days = item.min_notice_days;
    editForm.allow_past_dates = item.allow_past_dates;
    editForm.sort_order = item.sort_order;
    editForm.clearErrors();
}

function updateLeaveType() {
    if (editingId.value === null) {
        return;
    }

    editForm.put(route('settings.leave-types.update', editingId.value), {
        preserveScroll: true,
        onSuccess: () => {
            editingId.value = null;
        },
    });
}

function deleteLeaveType(item: LeaveTypeItem) {
    if (!window.confirm(t('settings.leave_types_delete_confirm', { name: item.name_ar || item.name_en }))) {
        return;
    }

    router.delete(route('settings.leave-types.destroy', item.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('settings.leave_types')" />

        <SettingsLayout content-width="wide">
            <div class="space-y-8">
                <HeadingSmall
                    :title="t('settings.leave_types')"
                    :description="t('settings.leave_types_description')"
                />

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between gap-4">
                        <CardTitle>{{ t('settings.leave_types') }}</CardTitle>
                        <Button @click="createDialogOpen = true">
                            {{ t('settings.leave_types_add') }}
                        </Button>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[1040px] text-sm">
                                <thead>
                                    <tr class="border-b text-center">
                                        <th class="py-2">{{ t('settings.name_en') }}</th>
                                        <th class="py-2">{{ t('settings.name_ar') }}</th>
                                        <th class="py-2">{{ t('settings.leave_type_min_notice_days') }}</th>
                                        <th class="py-2">{{ t('settings.leave_type_allow_past_dates') }}</th>
                                        <th class="py-2">{{ t('settings.leave_type_sort_order') }}</th>
                                        <th class="py-2">{{ t('common.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="item in props.leaveTypes" :key="item.id" class="border-b">
                                        <td class="py-2 text-center">{{ item.name_en }}</td>
                                        <td class="py-2 text-center">{{ item.name_ar }}</td>
                                        <td class="py-2 text-center">{{ item.min_notice_days }}</td>
                                        <td class="py-2 text-center">
                                            {{ item.allow_past_dates ? t('common.yes') : t('common.no') }}
                                        </td>
                                        <td class="py-2 text-center">{{ item.sort_order }}</td>
                                        <td class="py-2">
                                            <div class="flex justify-center gap-2">
                                                <Button size="sm" variant="outline" @click="startEdit(item)">
                                                    {{ t('common.edit') }}
                                                </Button>
                                                <Button size="sm" variant="destructive" @click="deleteLeaveType(item)">
                                                    {{ t('common.delete') }}
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <form
                            v-if="editingId !== null"
                            class="grid grid-cols-1 md:grid-cols-6 gap-3 items-start border rounded-md p-4 bg-muted/20"
                            @submit.prevent="updateLeaveType"
                        >
                            <div>
                                <Label>{{ t('settings.name_en') }}</Label>
                                <Input v-model="editForm.name_en" />
                                <InputError class="mt-1" :message="editForm.errors.name_en" />
                            </div>
                            <div>
                                <Label>{{ t('settings.name_ar') }}</Label>
                                <Input v-model="editForm.name_ar" />
                                <InputError class="mt-1" :message="editForm.errors.name_ar" />
                            </div>
                            <div>
                                <Label>{{ t('settings.leave_type_min_notice_days') }}</Label>
                                <Input v-model="editForm.min_notice_days" type="number" min="0" />
                                <InputError class="mt-1" :message="editForm.errors.min_notice_days" />
                            </div>
                            <div>
                                <Label>{{ t('settings.leave_type_sort_order') }}</Label>
                                <Input v-model="editForm.sort_order" type="number" min="0" />
                                <InputError class="mt-1" :message="editForm.errors.sort_order" />
                            </div>
                            <div class="md:col-span-2 flex items-start gap-2 pt-6">
                                <input
                                    id="edit-allow-past-dates"
                                    v-model="editForm.allow_past_dates"
                                    type="checkbox"
                                    class="mt-1 h-4 w-4"
                                />
                                <div>
                                    <Label for="edit-allow-past-dates">{{ t('settings.leave_type_allow_past_dates') }}</Label>
                                    <p class="text-xs text-muted-foreground mt-1">
                                        {{ t('settings.leave_type_allow_past_dates_hint') }}
                                    </p>
                                    <InputError class="mt-1" :message="editForm.errors.allow_past_dates" />
                                </div>
                            </div>
                            <div class="flex gap-2 mt-2 md:col-span-6">
                                <Button type="submit" :disabled="editForm.processing">{{ t('common.save') }}</Button>
                                <Button type="button" variant="outline" @click="editingId = null">{{ t('common.cancel') }}</Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Dialog :open="createDialogOpen" @update:open="(open) => (createDialogOpen = open)">
                    <DialogContent class="max-w-2xl">
                        <DialogHeader>
                            <DialogTitle>{{ t('settings.leave_types_add') }}</DialogTitle>
                        </DialogHeader>

                        <form class="grid grid-cols-1 md:grid-cols-2 gap-4" @submit.prevent="createLeaveType">
                            <div>
                                <Label for="leave-type-name-en">{{ t('settings.name_en') }}</Label>
                                <Input id="leave-type-name-en" v-model="createForm.name_en" />
                                <InputError class="mt-1" :message="createForm.errors.name_en" />
                            </div>
                            <div>
                                <Label for="leave-type-name-ar">{{ t('settings.name_ar') }}</Label>
                                <Input id="leave-type-name-ar" v-model="createForm.name_ar" />
                                <InputError class="mt-1" :message="createForm.errors.name_ar" />
                            </div>
                            <div>
                                <Label for="leave-type-min-notice">{{ t('settings.leave_type_min_notice_days') }}</Label>
                                <Input id="leave-type-min-notice" v-model="createForm.min_notice_days" type="number" min="0" />
                                <InputError class="mt-1" :message="createForm.errors.min_notice_days" />
                            </div>
                            <div>
                                <Label for="leave-type-sort-order">{{ t('settings.leave_type_sort_order') }}</Label>
                                <Input id="leave-type-sort-order" v-model="createForm.sort_order" type="number" min="0" />
                                <InputError class="mt-1" :message="createForm.errors.sort_order" />
                            </div>
                            <div class="md:col-span-2 flex items-start gap-2">
                                <input
                                    id="leave-type-allow-past"
                                    v-model="createForm.allow_past_dates"
                                    type="checkbox"
                                    class="mt-1 h-4 w-4"
                                />
                                <div>
                                    <Label for="leave-type-allow-past">{{ t('settings.leave_type_allow_past_dates') }}</Label>
                                    <p class="text-xs text-muted-foreground mt-1">
                                        {{ t('settings.leave_type_allow_past_dates_hint') }}
                                    </p>
                                    <InputError class="mt-1" :message="createForm.errors.allow_past_dates" />
                                </div>
                            </div>

                            <DialogFooter class="md:col-span-2">
                                <Button type="button" variant="outline" @click="createDialogOpen = false">
                                    {{ t('common.cancel') }}
                                </Button>
                                <Button type="submit" :disabled="createForm.processing">
                                    {{ t('common.add') }}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>

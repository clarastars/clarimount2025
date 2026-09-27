<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { BreadcrumbItem } from '@/types';

interface TierItem {
    id: number;
    min_months: number;
    max_months: number | null;
    max_amount: number;
    max_installments: number;
    sort_order: number;
    is_active: boolean;
}

const props = defineProps<{
    tiers: TierItem[];
    status?: string | null;
}>();

const { t, locale } = useI18n();

const breadcrumbs = computed((): BreadcrumbItem[] => [
    {
        title: t('settings.advance_entitlement_tiers'),
        href: '/settings/advance-entitlement-tiers',
    },
]);

const createDialogOpen = ref(false);
const editingId = ref<number | null>(null);

const createForm = useForm({
    min_months: 0,
    max_months: '' as string | number,
    max_amount: 300,
    max_installments: 3,
    sort_order: 0,
});

const editForm = useForm({
    min_months: 0,
    max_months: '' as string | number,
    max_amount: 0,
    max_installments: 1,
    sort_order: 0,
    is_active: true,
});

const formatCurrency = (amount: number) =>
    `${Number(amount).toLocaleString(locale.value === 'ar' ? 'ar-SA' : 'en-GB', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })} SAR`;

const tenureLabel = (tier: TierItem): string => {
    if (tier.max_months === null) {
        return t('settings.advance_entitlement_tenure_open', { min: tier.min_months });
    }

    return t('settings.advance_entitlement_tenure_range', {
        min: tier.min_months,
        max: tier.max_months,
    });
};

function createTier() {
    const maxMonths = createForm.max_months === '' || createForm.max_months === null
        ? null
        : Number(createForm.max_months);

    createForm.transform(() => ({
        min_months: Number(createForm.min_months),
        max_months: maxMonths,
        max_amount: Number(createForm.max_amount),
        max_installments: Number(createForm.max_installments),
        sort_order: Number(createForm.sort_order || 0),
    })).post(route('settings.advance-entitlement-tiers.store'), {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            createForm.max_amount = 300;
            createForm.max_installments = 3;
            createDialogOpen.value = false;
        },
    });
}

function startEdit(tier: TierItem) {
    editingId.value = tier.id;
    editForm.min_months = tier.min_months;
    editForm.max_months = tier.max_months ?? '';
    editForm.max_amount = tier.max_amount;
    editForm.max_installments = tier.max_installments;
    editForm.sort_order = tier.sort_order;
    editForm.is_active = tier.is_active;
    editForm.clearErrors();
}

function updateTier() {
    if (editingId.value === null) {
        return;
    }

    const maxMonths = editForm.max_months === '' || editForm.max_months === null
        ? null
        : Number(editForm.max_months);

    editForm.transform(() => ({
        min_months: Number(editForm.min_months),
        max_months: maxMonths,
        max_amount: Number(editForm.max_amount),
        max_installments: Number(editForm.max_installments),
        sort_order: Number(editForm.sort_order || 0),
        is_active: editForm.is_active,
    })).put(route('settings.advance-entitlement-tiers.update', editingId.value), {
        preserveScroll: true,
        onSuccess: () => {
            editingId.value = null;
        },
    });
}

function deleteTier(tier: TierItem) {
    if (!window.confirm(t('settings.advance_entitlement_tiers_delete_confirm'))) {
        return;
    }

    router.delete(route('settings.advance-entitlement-tiers.destroy', tier.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('settings.advance_entitlement_tiers')" />

        <SettingsLayout content-width="wide">
            <div class="space-y-6">
                <HeadingSmall
                    :title="t('settings.advance_entitlement_tiers')"
                    :description="t('settings.advance_entitlement_tiers_description')"
                />

                <div
                    v-if="status"
                    class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700"
                >
                    {{ status }}
                </div>

                <div class="flex justify-end">
                    <Button type="button" @click="createDialogOpen = true">
                        {{ t('settings.advance_entitlement_tiers_add') }}
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{{ t('settings.advance_entitlement_tiers_list') }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p v-if="tiers.length === 0" class="text-sm text-muted-foreground">
                            {{ t('settings.advance_entitlement_tiers_empty') }}
                        </p>

                        <div v-else class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b text-muted-foreground">
                                        <th class="py-2 px-2 text-start font-medium">{{ t('settings.advance_entitlement_tenure') }}</th>
                                        <th class="py-2 px-2 text-start font-medium">{{ t('settings.advance_entitlement_max_amount') }}</th>
                                        <th class="py-2 px-2 text-start font-medium">{{ t('settings.advance_entitlement_max_installments') }}</th>
                                        <th class="py-2 px-2 text-start font-medium">{{ t('common.status') }}</th>
                                        <th class="py-2 px-2 text-start font-medium">{{ t('common.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="tier in tiers" :key="tier.id" class="border-b last:border-0">
                                        <td class="py-3 px-2">{{ tenureLabel(tier) }}</td>
                                        <td class="py-3 px-2">{{ formatCurrency(tier.max_amount) }}</td>
                                        <td class="py-3 px-2">{{ tier.max_installments }}</td>
                                        <td class="py-3 px-2">
                                            {{ tier.is_active ? t('common.active') : t('common.inactive') }}
                                        </td>
                                        <td class="py-3 px-2">
                                            <div class="flex gap-2">
                                                <Button type="button" variant="outline" size="sm" @click="startEdit(tier)">
                                                    {{ t('common.edit') }}
                                                </Button>
                                                <Button type="button" variant="destructive" size="sm" @click="deleteTier(tier)">
                                                    {{ t('common.delete') }}
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <Dialog :open="createDialogOpen" @update:open="(open) => (createDialogOpen = open)">
                    <DialogContent class="max-w-lg">
                        <DialogHeader>
                            <DialogTitle>{{ t('settings.advance_entitlement_tiers_add') }}</DialogTitle>
                        </DialogHeader>
                        <form class="space-y-4" @submit.prevent="createTier">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="create_min_months">{{ t('settings.advance_entitlement_min_months') }}</Label>
                                    <Input id="create_min_months" v-model="createForm.min_months" type="number" min="0" required />
                                    <p v-if="createForm.errors.min_months" class="text-sm text-red-600">{{ createForm.errors.min_months }}</p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="create_max_months">{{ t('settings.advance_entitlement_max_months') }}</Label>
                                    <Input id="create_max_months" v-model="createForm.max_months" type="number" min="0" :placeholder="t('settings.advance_entitlement_max_months_open')" />
                                    <p v-if="createForm.errors.max_months" class="text-sm text-red-600">{{ createForm.errors.max_months }}</p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="create_max_amount">{{ t('settings.advance_entitlement_max_amount') }}</Label>
                                    <Input id="create_max_amount" v-model="createForm.max_amount" type="number" min="1" step="0.01" required />
                                    <p v-if="createForm.errors.max_amount" class="text-sm text-red-600">{{ createForm.errors.max_amount }}</p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="create_max_installments">{{ t('settings.advance_entitlement_max_installments') }}</Label>
                                    <Input id="create_max_installments" v-model="createForm.max_installments" type="number" min="1" required />
                                    <p v-if="createForm.errors.max_installments" class="text-sm text-red-600">{{ createForm.errors.max_installments }}</p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="create_sort_order">{{ t('settings.advance_entitlement_sort_order') }}</Label>
                                    <Input id="create_sort_order" v-model="createForm.sort_order" type="number" min="0" />
                                </div>
                            </div>
                            <DialogFooter>
                                <Button type="button" variant="outline" @click="createDialogOpen = false">{{ t('common.cancel') }}</Button>
                                <Button type="submit" :disabled="createForm.processing">
                                    {{ createForm.processing ? t('common.saving') : t('common.save') }}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>

                <Dialog :open="editingId !== null" @update:open="(open) => { if (!open) editingId = null; }">
                    <DialogContent class="max-w-lg">
                        <DialogHeader>
                            <DialogTitle>{{ t('settings.advance_entitlement_tiers_edit') }}</DialogTitle>
                        </DialogHeader>
                        <form class="space-y-4" @submit.prevent="updateTier">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="edit_min_months">{{ t('settings.advance_entitlement_min_months') }}</Label>
                                    <Input id="edit_min_months" v-model="editForm.min_months" type="number" min="0" required />
                                    <p v-if="editForm.errors.min_months" class="text-sm text-red-600">{{ editForm.errors.min_months }}</p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="edit_max_months">{{ t('settings.advance_entitlement_max_months') }}</Label>
                                    <Input id="edit_max_months" v-model="editForm.max_months" type="number" min="0" :placeholder="t('settings.advance_entitlement_max_months_open')" />
                                    <p v-if="editForm.errors.max_months" class="text-sm text-red-600">{{ editForm.errors.max_months }}</p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="edit_max_amount">{{ t('settings.advance_entitlement_max_amount') }}</Label>
                                    <Input id="edit_max_amount" v-model="editForm.max_amount" type="number" min="1" step="0.01" required />
                                    <p v-if="editForm.errors.max_amount" class="text-sm text-red-600">{{ editForm.errors.max_amount }}</p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="edit_max_installments">{{ t('settings.advance_entitlement_max_installments') }}</Label>
                                    <Input id="edit_max_installments" v-model="editForm.max_installments" type="number" min="1" required />
                                    <p v-if="editForm.errors.max_installments" class="text-sm text-red-600">{{ editForm.errors.max_installments }}</p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="edit_sort_order">{{ t('settings.advance_entitlement_sort_order') }}</Label>
                                    <Input id="edit_sort_order" v-model="editForm.sort_order" type="number" min="0" />
                                </div>
                                <div class="flex items-center gap-2 pt-6">
                                    <input id="edit_is_active" v-model="editForm.is_active" type="checkbox" class="h-4 w-4 rounded border" />
                                    <Label for="edit_is_active">{{ t('common.active') }}</Label>
                                </div>
                            </div>
                            <DialogFooter>
                                <Button type="button" variant="outline" @click="editingId = null">{{ t('common.cancel') }}</Button>
                                <Button type="submit" :disabled="editForm.processing">
                                    {{ editForm.processing ? t('common.saving') : t('common.save') }}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>

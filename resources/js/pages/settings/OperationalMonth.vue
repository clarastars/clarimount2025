<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';

interface OperationalMonthSettings {
    start_day: number | null;
    end_day: number | null;
    is_custom: boolean;
}

interface Props {
    settings: OperationalMonthSettings;
    currentState: {
        mode: 'custom' | 'calendar';
        start: string;
        end: string;
    };
    status?: string | null;
}

const props = defineProps<Props>();
const { t } = useI18n();
const isEditing = ref(false);

const breadcrumbItems = computed((): BreadcrumbItem[] => [
    {
        title: t('settings.operational_month'),
        href: '/settings/operational-month',
    },
]);

const form = useForm({
    start_day: props.settings.start_day,
    end_day: props.settings.end_day,
});

const submit = () => {
    form.put(route('settings.operational-month.update'), {
        onSuccess: () => {
            isEditing.value = false;
        },
    });
};

const resetToDefault = () => {
    form.start_day = null;
    form.end_day = null;
    submit();
};

const currentModeText = computed(() => (
    props.currentState.mode === 'custom'
        ? t('settings.operational_month_mode_custom')
        : t('settings.operational_month_mode_calendar')
));

watch(
    () => props.status,
    (value) => {
        if (value) {
            isEditing.value = false;
        }
    },
    { immediate: true }
);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('settings.operational_month')" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall :title="t('settings.operational_month')" :description="t('settings.operational_month_description')" />

                <div v-if="status" class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                    {{ status }}
                </div>

                <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    {{ t('settings.operational_month_hint') }}
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div class="text-sm font-semibold text-slate-800">{{ t('settings.operational_month_current_state') }}</div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            :disabled="form.processing"
                            @click="isEditing = !isEditing"
                        >
                            {{ isEditing ? t('common.cancel') : t('common.edit') }}
                        </Button>
                    </div>
                    <div class="mt-3 grid gap-2 text-sm text-slate-700">
                        <div>
                            <span class="font-medium">{{ t('settings.operational_month_current_mode') }}: </span>
                            <span>{{ currentModeText }}</span>
                        </div>
                        <div>
                            <span class="font-medium">{{ t('settings.operational_month_effective_range') }}: </span>
                            <span>{{ props.currentState.start }} → {{ props.currentState.end }}</span>
                        </div>
                    </div>
                </div>

                <form v-if="isEditing" class="space-y-4" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="start_day">{{ t('settings.operational_month_start_day') }}</Label>
                        <Input id="start_day" v-model.number="form.start_day" type="number" min="1" max="31" placeholder="21" />
                        <InputError :message="form.errors.start_day" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="end_day">{{ t('settings.operational_month_end_day') }}</Label>
                        <Input id="end_day" v-model.number="form.end_day" type="number" min="1" max="31" placeholder="20" />
                        <InputError :message="form.errors.end_day" />
                    </div>

                    <InputError :message="form.errors.boundaries" />

                    <div class="flex items-center gap-3 pt-2">
                        <Button type="submit" :disabled="form.processing">
                            {{ form.processing ? t('common.saving') : t('common.save') }}
                        </Button>
                        <Button type="button" variant="outline" :disabled="form.processing" @click="resetToDefault">
                            {{ t('settings.operational_month_reset_default') }}
                        </Button>
                    </div>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>

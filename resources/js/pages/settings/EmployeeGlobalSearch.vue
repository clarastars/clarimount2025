<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { type BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

interface Props {
    settings: {
        enabled: boolean;
    };
    status?: string | null;
}

const props = defineProps<Props>();
const { t } = useI18n();

const breadcrumbItems = computed((): BreadcrumbItem[] => [
    {
        title: t('settings.employee_global_search'),
        href: '/settings/employee-global-search',
    },
]);

const form = useForm({
    enabled: props.settings.enabled,
});

const submit = (): void => {
    form.put(route('settings.employee-global-search.update'));
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('settings.employee_global_search')" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall
                    :title="t('settings.employee_global_search')"
                    :description="t('settings.employee_global_search_description')"
                />

                <div
                    v-if="status"
                    class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700"
                >
                    {{ status }}
                </div>

                <form class="space-y-4 rounded-lg border p-4" @submit.prevent="submit">
                    <label class="inline-flex cursor-pointer items-center gap-2 text-sm">
                        <input
                            v-model="form.enabled"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300"
                        >
                        <span>{{ t('settings.employee_global_search_toggle_label') }}</span>
                    </label>

                    <p class="text-xs text-muted-foreground">
                        {{ t('settings.employee_global_search_toggle_hint') }}
                    </p>

                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? t('common.saving') : t('common.save') }}
                    </Button>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>


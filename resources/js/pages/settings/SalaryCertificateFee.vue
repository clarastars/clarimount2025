<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

interface Props {
    settings: {
        chamber_fee: number;
    };
    status?: string | null;
}

const props = defineProps<Props>();
const { t } = useI18n();

const breadcrumbItems = computed((): BreadcrumbItem[] => [
    {
        title: t('settings.salary_certificate_fee'),
        href: '/settings/salary-certificate-fee',
    },
]);

const form = useForm({
    chamber_fee: props.settings.chamber_fee,
});

const submit = (): void => {
    form.put(route('settings.salary-certificate-fee.update'));
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('settings.salary_certificate_fee')" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall
                    :title="t('settings.salary_certificate_fee')"
                    :description="t('settings.salary_certificate_fee_description')"
                />

                <div
                    v-if="status"
                    class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700"
                >
                    {{ status }}
                </div>

                <form class="space-y-4 rounded-lg border p-4" @submit.prevent="submit">
                    <div class="space-y-2">
                        <Label for="chamber_fee">{{ t('settings.salary_certificate_chamber_fee') }}</Label>
                        <Input
                            id="chamber_fee"
                            v-model="form.chamber_fee"
                            type="number"
                            min="0"
                            step="0.01"
                            required
                        />
                        <p v-if="form.errors.chamber_fee" class="text-sm text-red-600">{{ form.errors.chamber_fee }}</p>
                    </div>

                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? t('common.saving') : t('common.save') }}
                    </Button>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>

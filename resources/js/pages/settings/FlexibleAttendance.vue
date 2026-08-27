<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { type BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

interface CompanyFlexibleRow {
    id: number;
    name_ar: string;
    name_en: string;
    flexible_time_enabled: boolean;
    flexible_time_minutes: number;
}

interface Props {
    companies: CompanyFlexibleRow[];
    status?: string | null;
}

const props = defineProps<Props>();
const { t, locale } = useI18n();

const breadcrumbItems = computed((): BreadcrumbItem[] => [
    {
        title: t('settings.flexible_attendance'),
        href: '/settings/flexible-attendance',
    },
]);

const form = useForm({
    companies: props.companies.map((company) => ({
        id: company.id,
        flexible_time_enabled: Boolean(company.flexible_time_enabled),
        flexible_time_minutes: Number(company.flexible_time_minutes || 30),
    })),
});

const companyName = (company: CompanyFlexibleRow): string =>
    locale.value === 'ar' ? company.name_ar : company.name_en;

const submit = (): void => {
    form
        .transform((data) => ({
            companies: data.companies.map((row) => ({
                ...row,
                flexible_time_enabled: Boolean(row.flexible_time_enabled),
                flexible_time_minutes: row.flexible_time_enabled
                    ? Number(row.flexible_time_minutes)
                    : Number(row.flexible_time_minutes || 30),
            })),
        }))
        .put(route('settings.flexible-attendance.update'), {
            preserveScroll: true,
        });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('settings.flexible_attendance')" />

        <SettingsLayout content-width="wide">
            <div class="space-y-6">
                <HeadingSmall
                    :title="t('settings.flexible_attendance')"
                    :description="t('settings.flexible_attendance_description')"
                />

                <div
                    v-if="status"
                    class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700"
                >
                    {{ status }}
                </div>

                <p class="text-xs text-muted-foreground">
                    {{ t('settings.flexible_attendance_hint') }}
                </p>

                <form v-if="props.companies.length > 0" class="space-y-4" @submit.prevent="submit">
                    <div class="overflow-x-auto rounded-lg border">
                        <table class="min-w-full text-sm">
                            <thead class="bg-muted/50 text-start">
                                <tr>
                                    <th class="px-4 py-3 font-medium">{{ t('settings.flexible_attendance_company') }}</th>
                                    <th class="px-4 py-3 font-medium">{{ t('settings.flexible_attendance_mode') }}</th>
                                    <th class="px-4 py-3 font-medium">{{ t('settings.flexible_attendance_minutes') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(company, index) in props.companies"
                                    :key="company.id"
                                    class="border-t"
                                >
                                    <td class="px-4 py-3 align-top">
                                        <div class="font-medium">{{ companyName(company) }}</div>
                                        <div class="text-xs text-muted-foreground">
                                            {{
                                                form.companies[index].flexible_time_enabled
                                                    ? t('settings.flexible_attendance_mode_flexible')
                                                    : t('settings.flexible_attendance_mode_normal')
                                            }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 align-top">
                                        <label class="inline-flex cursor-pointer items-center gap-2">
                                            <input
                                                v-model="form.companies[index].flexible_time_enabled"
                                                type="checkbox"
                                                class="h-4 w-4 rounded border-gray-300"
                                            >
                                            <span>{{ t('settings.flexible_attendance_enable') }}</span>
                                        </label>
                                    </td>
                                    <td class="px-4 py-3 align-top">
                                        <Input
                                            v-model.number="form.companies[index].flexible_time_minutes"
                                            type="number"
                                            min="1"
                                            max="180"
                                            step="1"
                                            class="max-w-[8rem]"
                                            :disabled="!form.companies[index].flexible_time_enabled"
                                        />
                                        <InputError
                                            class="mt-1"
                                            :message="form.errors[`companies.${index}.flexible_time_minutes`]"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? t('common.saving') : t('common.save') }}
                    </Button>
                </form>

                <div
                    v-else
                    class="rounded-lg border border-dashed px-4 py-8 text-center text-sm text-muted-foreground"
                >
                    {{ t('settings.flexible_attendance_no_companies') }}
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';

interface CompanyCount {
    company_id: number | null;
    company_name_en: string;
    company_name_ar: string;
    employees_count: number;
}

interface Props {
    missingCount: number;
    byCompany: CompanyCount[];
}

const props = defineProps<Props>();
const { t, locale } = useI18n();

const breadcrumbItems = computed((): BreadcrumbItem[] => [
    {
        title: t('settings.missing_hire_date_export'),
        href: '/settings/missing-hire-date-export',
    },
]);

const companyName = (row: CompanyCount): string => {
    return locale.value === 'ar' ? row.company_name_ar : row.company_name_en;
};

const downloadUrl = computed(() => route('settings.missing-hire-date-export.download'));
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('settings.missing_hire_date_export')" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall
                    :title="t('settings.missing_hire_date_export')"
                    :description="t('settings.missing_hire_date_export_description')"
                />

                <Card>
                    <CardHeader>
                        <CardTitle>{{ t('settings.missing_hire_date_export_summary') }}</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <p class="text-sm text-muted-foreground">
                            {{ t('settings.missing_hire_date_export_hint') }}
                        </p>

                        <div class="rounded-md border bg-muted/30 px-4 py-3 text-sm">
                            <span class="font-medium">{{ t('settings.missing_hire_date_export_total') }}:</span>
                            {{ props.missingCount }}
                        </div>

                        <div v-if="props.byCompany.length" class="rounded-md border overflow-hidden">
                            <table class="w-full text-sm">
                                <thead class="bg-muted/50">
                                    <tr>
                                        <th class="px-3 py-2 text-start">{{ t('companies.title') }}</th>
                                        <th class="px-3 py-2 text-start">{{ t('settings.missing_hire_date_export_count') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="row in props.byCompany"
                                        :key="String(row.company_id ?? 'none')"
                                        class="border-t"
                                    >
                                        <td class="px-3 py-2">{{ companyName(row) }}</td>
                                        <td class="px-3 py-2">{{ row.employees_count }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-else class="text-sm text-muted-foreground">
                            {{ t('settings.missing_hire_date_export_empty') }}
                        </div>

                        <div>
                            <a
                                v-if="props.missingCount > 0"
                                :href="downloadUrl"
                                class="inline-flex"
                            >
                                <Button type="button">
                                    {{ t('settings.missing_hire_date_export_button') }}
                                </Button>
                            </a>
                            <Button v-else type="button" disabled>
                                {{ t('settings.missing_hire_date_export_button') }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>

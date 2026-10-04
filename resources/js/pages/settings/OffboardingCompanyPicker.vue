<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import HeadingSmall from '@/components/HeadingSmall.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';

interface CompanyItem {
    id: number;
    name_en: string;
    name_ar: string;
}

interface Props {
    companies: CompanyItem[];
}

const props = defineProps<Props>();
const { t, locale } = useI18n();

const breadcrumbs = computed((): BreadcrumbItem[] => [
    {
        title: t('settings.offboarding'),
        href: '/settings/offboarding',
    },
]);

const companyLabel = (company: CompanyItem) => {
    if (locale.value === 'ar' && company.name_ar) {
        return company.name_ar;
    }

    return company.name_en || company.name_ar;
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('settings.offboarding')" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall
                    :title="t('settings.offboarding')"
                    :description="t('settings.offboarding_company_picker_description')"
                />

                <Card>
                    <CardHeader>
                        <CardTitle>{{ t('settings.offboarding_select_company') }}</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <p v-if="companies.length === 0" class="text-sm text-muted-foreground">
                            {{ t('settings.offboarding_no_companies') }}
                        </p>

                        <div
                            v-for="company in companies"
                            :key="company.id"
                            class="flex flex-col gap-3 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="font-medium">{{ companyLabel(company) }}</div>
                            <div class="flex flex-wrap gap-2">
                                <Button variant="outline" as-child>
                                    <Link :href="route('companies.offboarding-checklist.index', company.id)">
                                        {{ t('settings.offboarding_manage_checklist') }}
                                    </Link>
                                </Button>
                                <Button variant="outline" as-child>
                                    <Link :href="route('companies.offboarding-clearance-approvals.index', company.id)">
                                        {{ t('settings.offboarding_manage_clearance') }}
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>

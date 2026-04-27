<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Plus, Eye, Edit, Trash2, Building } from 'lucide-vue-next';

import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import Heading from '@/components/Heading.vue';
import { type BreadcrumbItem, type Company } from '@/types';

interface Props {
    companies: {
        data: Company[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    isReadOnly?: boolean;
}

const props = defineProps<Props>();
const { t, locale } = useI18n();

const breadcrumbs = computed((): BreadcrumbItem[] => [
    {
        title: t('nav.dashboard'),
        href: '/dashboard',
    },
    {
        title: t('companies.title'),
        href: '/companies',
    },
]);

const deleteCompany = (company: Company) => {
    if (confirm(t('companies.confirm_delete'))) {
        router.delete(`/companies/${company.id}`);
    }
};

const getCompanyName = (company: Company) => {
    return locale.value === 'ar' ? company.name_ar : company.name_en;
};

const getCompanyDescription = (company: Company) => {
    const description = locale.value === 'ar' ? company.description_ar : company.description_en;
    return description || '';
};
</script>

<template>
    <Head :title="t('companies.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                                        <Heading :title="t('companies.my_companies')" />
                <Button v-if="!props.isReadOnly" as-child class="bg-blue-600 hover:bg-blue-700 text-white font-semibold">
                    <Link :href="route('companies.create')">
                        <Plus class="mr-2 h-4 w-4" />
                        {{ t('companies.create_company') }}
                    </Link>
                </Button>
            </div>

            <div v-if="companies.data.length === 0" class="text-center py-12">
                <Building class="mx-auto h-12 w-12 text-gray-400" />
                <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                    {{ t('companies.no_companies') }}
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('companies.create_first_company') }}</p>
                <div class="mt-6">
                    <Button v-if="!props.isReadOnly" as-child>
                        <Link :href="route('companies.create')">
                            <Plus class="mr-2 h-4 w-4" />
                            {{ t('companies.create_company') }}
                        </Link>
                    </Button>
                </div>
            </div>

            <div v-else class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <Card v-for="company in companies.data" :key="company.id" class="hover:shadow-lg transition-shadow">
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <CardTitle class="text-lg">{{ getCompanyName(company) }}</CardTitle>
                            <Badge :variant="company.is_active ? 'default' : 'secondary'">
                                {{ company.is_active ? t('companies.active') : t('companies.inactive') }}
                            </Badge>
                        </div>
                        <CardDescription class="line-clamp-2">
                            {{ getCompanyDescription(company) }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                            <div class="flex items-center justify-between">
                                <span class="font-medium">{{ t('companies.company_email') }}:</span>
                                <span class="truncate ml-2">{{ company.company_email }}</span>
                            </div>
                            <div v-if="company.website" class="flex items-center justify-between">
                                <span class="font-medium">{{ t('companies.website') }}:</span>
                                <a :href="company.website" target="_blank" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 truncate ml-2">
                                    {{ company.website }}
                                </a>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="font-medium">{{ t('companies.created') }}:</span>
                                <span>{{ new Date(company.created_at).toLocaleDateString() }}</span>
                            </div>
                        </div>
                        
                        <div class="flex items-center space-x-2 mt-4">
                            <Button variant="outline" size="sm" as-child>
                                <Link :href="route('companies.show', company.id)">
                                    <Eye class="mr-1 h-3 w-3" />
                                    {{ t('companies.view') }}
                                </Link>
                            </Button>
                            <Button v-if="!props.isReadOnly" variant="outline" size="sm" as-child>
                                <Link :href="route('companies.edit', company.id)">
                                    <Edit class="mr-1 h-3 w-3" />
                                    {{ t('companies.edit') }}
                                </Link>
                            </Button>
                            <Button v-if="!props.isReadOnly" variant="destructive" size="sm" @click="deleteCompany(company)">
                                <Trash2 class="mr-1 h-3 w-3" />
                                {{ t('companies.delete') }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Pagination would go here if needed -->
            <div v-if="companies.last_page > 1" class="flex justify-center">
                <!-- Add pagination component here if needed -->
            </div>
        </div>
    </AppLayout>
</template> 
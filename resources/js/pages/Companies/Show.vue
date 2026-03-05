<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Edit, Globe, Mail, Calendar, User, Users, Package, Settings, CheckCircle, XCircle, Clock, FileText } from 'lucide-vue-next';

import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import Heading from '@/components/Heading.vue';
import { type BreadcrumbItem, type Company } from '@/types';

interface BayzatConfig {
    id: number;
    api_key: string;
    api_url: string;
    is_enabled: boolean;
    sync_frequency: string;
    last_sync_at: string | null;
    settings: any;
}

interface Props {
    company: Company & {
        bayzat_config?: BayzatConfig;
        fingerprint_report_name?: string;
    };
    totalAssetsCount: number;
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
    {
        title: getCompanyName(props.company),
        href: `/companies/${props.company.id}`,
    },
]);

const getCompanyName = (company: Company) => {
    return locale.value === 'ar' ? company.name_ar : company.name_en;
};

const getCompanyDescription = (company: Company) => {
    const description = locale.value === 'ar' ? company.description_ar : company.description_en;
    return description || '';
};

const getBayzatStatusVariant = (isEnabled: boolean) => {
    return isEnabled ? 'default' : 'secondary';
};

const getBayzatStatusIcon = (isEnabled: boolean) => {
    return isEnabled ? CheckCircle : XCircle;
};

const formatLastSync = (lastSync: string | null) => {
    if (!lastSync) return t('bayzat.never_synced');
    return new Date(lastSync).toLocaleString();
};
</script>

<template>
    <Head :title="getCompanyName(company)" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                                            <Heading :title="getCompanyName(company)" />
                    <p v-if="getCompanyDescription(company)" class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        {{ getCompanyDescription(company) }}
                    </p>
                </div>
                <div class="flex items-center space-x-2">
                    <Badge :variant="company.is_active ? 'default' : 'secondary'">
                        {{ company.is_active ? t('companies.active') : t('companies.inactive') }}
                    </Badge>
                    <Button variant="outline" as-child>
                        <Link :href="route('employees.index', { company_id: company.id })">
                            <Users class="mr-2 h-4 w-4" />
                            {{ t('nav.employees') }}
                        </Link>
                    </Button>
                    <Button variant="outline" as-child>
                        <Link :href="route('attendance.index', company.id)">
                            <FileText class="mr-2 h-4 w-4" />
                            {{ t('nav.attendance') }}
                        </Link>
                    </Button>
                    <Button variant="outline" as-child>
                        <Link :href="route('salary-runs.index', company.id)">
                            <FileText class="mr-2 h-4 w-4" />
                            {{ t('salary_runs.title') }}
                        </Link>
                    </Button>
                    <Button variant="outline" as-child>
                        <Link :href="route('companies.edit', company.id)">
                            <Edit class="mr-2 h-4 w-4" />
                            {{ t('companies.edit') }}
                        </Link>
                    </Button>
                </div>
            </div>

            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <!-- Company Details -->
                <Card>
                    <CardHeader>
                        <CardTitle>{{ t('companies.company_details') }}</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="flex items-center space-x-3">
                            <Mail class="h-4 w-4 text-gray-500" />
                            <div>
                                <p class="text-sm font-medium">{{ t('companies.company_email') }}</p>
                                <p class="text-sm text-gray-600 dark:text-gray-400">{{ company.company_email }}</p>
                            </div>
                        </div>

                        <div v-if="company.website" class="flex items-center space-x-3">
                            <Globe class="h-4 w-4 text-gray-500" />
                            <div>
                                <p class="text-sm font-medium">{{ t('companies.website') }}</p>
                                <a :href="company.website" target="_blank" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                    {{ company.website }}
                                </a>
                            </div>
                        </div>

                        <div class="flex items-center space-x-3">
                            <User class="h-4 w-4 text-gray-500" />
                            <div>
                                <p class="text-sm font-medium">{{ t('companies.owner') }}</p>
                                <p class="text-sm text-gray-600 dark:text-gray-400">{{ company.owner?.name }}</p>
                            </div>
                        </div>

                        <div class="flex items-center space-x-3">
                            <Calendar class="h-4 w-4 text-gray-500" />
                            <div>
                                <p class="text-sm font-medium">{{ t('companies.created') }}</p>
                                <p class="text-sm text-gray-600 dark:text-gray-400">{{ new Date(company.created_at).toLocaleDateString() }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Bayzat Configuration -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Settings class="h-5 w-5" />
                            {{ t('bayzat.title') }}
                        </CardTitle>
                        <CardDescription>
                            {{ t('bayzat.attendance_sync_settings') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <template v-if="company.bayzat_config">
                            <!-- Configuration Status -->
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-2">
                                    <component 
                                        :is="getBayzatStatusIcon(company.bayzat_config.is_enabled)" 
                                        class="h-4 w-4"
                                        :class="company.bayzat_config.is_enabled ? 'text-green-500' : 'text-gray-400'"
                                    />
                                    <span class="text-sm font-medium">{{ t('common.status') }}</span>
                                </div>
                                <Badge :variant="getBayzatStatusVariant(company.bayzat_config.is_enabled)">
                                    {{ company.bayzat_config.is_enabled ? t('bayzat.enabled') : t('bayzat.disabled') }}
                                </Badge>
                            </div>

                            <!-- API URL -->
                            <div>
                                <p class="text-sm font-medium">{{ t('bayzat.api_url') }}</p>
                                <p class="text-sm text-gray-600 dark:text-gray-400 truncate">
                                    {{ company.bayzat_config.api_url }}
                                </p>
                            </div>

                            <!-- Sync Frequency -->
                            <div>
                                <p class="text-sm font-medium">{{ t('bayzat.sync_frequency') }}</p>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ t(`bayzat.${company.bayzat_config.sync_frequency}`) }}
                                </p>
                            </div>

                            <!-- Last Sync -->
                            <div class="flex items-center space-x-2">
                                <Clock class="h-4 w-4 text-gray-500" />
                                <div>
                                    <p class="text-sm font-medium">{{ t('bayzat.last_sync') }}</p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ formatLastSync(company.bayzat_config.last_sync_at) }}
                                    </p>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="pt-2 space-y-2">
                                <Button variant="outline" size="sm" as-child class="w-full">
                                    <Link :href="route('bayzat-configs.show', company.id)">
                                        <Settings class="mr-2 h-4 w-4" />
                                        {{ t('bayzat.manage_settings') }}
                                    </Link>
                                </Button>
                            </div>
                        </template>

                        <!-- No Configuration -->
                        <template v-else>
                            <div class="text-center py-6">
                                <Settings class="h-8 w-8 text-gray-400 mx-auto mb-3" />
                                <p class="text-sm font-medium text-gray-900 dark:text-white mb-1">
                                    {{ t('bayzat.no_configuration') }}
                                </p>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                                    {{ t('bayzat.setup_required') }}
                                </p>
                                <Button size="sm" as-child>
                                    <Link :href="route('bayzat-configs.show', company.id)">
                                        <Settings class="mr-2 h-4 w-4" />
                                        {{ t('bayzat.configure_bayzat') }}
                                    </Link>
                                </Button>
                            </div>
                        </template>
                    </CardContent>
                </Card>

                <!-- Multilingual Names -->
                <Card>
                    <CardHeader>
                        <CardTitle>{{ locale === 'ar' ? 'الأسماء المترجمة' : 'Multilingual Names' }}</CardTitle>
                        <CardDescription>
                            {{ locale === 'ar' ? 'أسماء الشركة بلغات مختلفة' : 'Company names in different languages' }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div>
                            <p class="text-sm font-medium">{{ t('companies.name_en') }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ company.name_en }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium">{{ t('companies.name_ar') }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400 text-right" dir="rtl">{{ company.name_ar }}</p>
                        </div>
                        <div v-if="company.fingerprint_report_name">
                            <p class="text-sm font-medium">{{ t('companies.fingerprint_report_name') }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ company.fingerprint_report_name }}</p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Multilingual Descriptions -->
                <Card v-if="company.description_en || company.description_ar" class="md:col-span-2">
                    <CardHeader>
                        <CardTitle>{{ locale === 'ar' ? 'الأوصاف المترجمة' : 'Multilingual Descriptions' }}</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div v-if="company.description_en">
                            <p class="text-sm font-medium">{{ t('companies.description_en') }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ company.description_en }}</p>
                        </div>
                        <div v-if="company.description_ar">
                            <p class="text-sm font-medium">{{ t('companies.description_ar') }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400 text-right" dir="rtl">{{ company.description_ar }}</p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Assets Overview -->
                <Card class="lg:col-span-3">
                    <CardHeader>
                        <CardTitle>{{ t('companies.total_assets') }}</CardTitle>
                        <CardDescription>{{ t('companies.total_assets_description') }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="flex items-center space-x-3">
                            <Package class="h-4 w-4 text-gray-500" />
                            <div>
                                <p class="text-sm font-medium">{{ t('companies.total_assets') }}</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ totalAssetsCount }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template> 
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import Icon from '@/components/Icon.vue';
import Heading from '@/components/Heading.vue';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';
import type { BreadcrumbItem } from '@/types';

const { t } = useI18n();

interface FingerprintEmployee {
    id: string;
    emp_code: string;
    first_name: string;
    dept_name: string;
    position_name: string;
}

interface Props {
    employees: FingerprintEmployee[];
    error: string | null;
}

const props = defineProps<Props>();

const breadcrumbs = computed((): BreadcrumbItem[] => [
    {
        title: t('nav.dashboard'),
        href: '/dashboard',
    },
    {
        title: t('employees.title'),
        href: '/employees',
    },
    {
        title: t('employees.fingerprint_device_employees'),
        href: route('employees.fingerprint-device'),
    },
]);
</script>

<template>
    <Head :title="t('employees.fingerprint_device_employees')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <Heading :title="t('employees.fingerprint_device_employees')" />
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        {{ t('employees.fingerprint_device_employees_description') }}
                    </p>
                </div>
                <Button variant="outline" asChild>
                    <Link :href="route('employees.index')">
                        <Icon name="ArrowLeft" class="mr-2 rtl:mr-0 rtl:ml-2 h-4 w-4" />
                        {{ t('employees.title') }}
                    </Link>
                </Button>
            </div>

            <Card v-if="props.error" class="border-destructive">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-destructive">
                        <Icon name="AlertCircle" class="h-5 w-5" />
                        {{ t('common.error') }}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-sm text-muted-foreground">{{ props.error }}</p>
                </CardContent>
            </Card>

            <Card v-else>
                <CardHeader>
                    <CardTitle class="text-base">
                        {{ t('employees.fingerprint_device_employees') }}
                        <span class="text-muted-foreground font-normal text-sm ms-2">
                            ({{ props.employees.length }})
                        </span>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="!props.employees.length" class="py-12 text-center text-sm text-muted-foreground">
                        {{ t('employees.fingerprint_device_no_employees') }}
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b text-muted-foreground">
                                    <th class="py-3 px-4 text-start font-medium">#</th>
                                    <th class="py-3 px-4 text-start font-medium">
                                        {{ t('employees.fingerprint_id') }}
                                    </th>
                                    <th class="py-3 px-4 text-start font-medium">
                                        {{ t('employees.first_name') }}
                                    </th>
                                    <th class="py-3 px-4 text-start font-medium">
                                        {{ t('employees.fingerprint_dept_name') }}
                                    </th>
                                    <th class="py-3 px-4 text-start font-medium">
                                        {{ t('employees.fingerprint_position_name') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(emp, index) in props.employees"
                                    :key="emp.id || index"
                                    class="border-b last:border-0 hover:bg-muted/40"
                                >
                                    <td class="py-3 px-4">{{ index + 1 }}</td>
                                    <td class="py-3 px-4 font-mono">{{ emp.emp_code || emp.id || '—' }}</td>
                                    <td class="py-3 px-4 font-medium">{{ emp.first_name || '—' }}</td>
                                    <td class="py-3 px-4">{{ emp.dept_name || '—' }}</td>
                                    <td class="py-3 px-4">{{ emp.position_name || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

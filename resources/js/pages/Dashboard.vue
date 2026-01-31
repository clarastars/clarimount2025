<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { CalendarClock, ArrowUpRight, AlertTriangle, ShieldAlert } from 'lucide-vue-next';

const { t } = useI18n();

interface ExpiringEmployeeRow {
    employee_id: number;
    display_name: string;
    full_name: string;
    expiry_field: string;
    expiry_label_key: string;
    expiry_date: string;
    days_remaining: number;
}

interface Props {
    expiringEmployeesPreview: ExpiringEmployeeRow[];
    expiredEmployeesPreview: ExpiringEmployeeRow[];
    expiringEmployeesCount: number;
    expiredEmployeesCount: number;
    expiryDaysThreshold: number;
}

const props = defineProps<Props>();

const breadcrumbs = computed((): BreadcrumbItem[] => []);

const getUrgencyTone = (daysRemaining: number) => {
    if (daysRemaining <= 7) return 'critical';
    if (daysRemaining <= 30) return 'warning';
    return 'info';
};

const getCardBorderClass = (daysRemaining: number) => {
    if (daysRemaining < 0) {
        // Expired documents - red border
        return 'border-red-500 hover:border-red-600';
    }
    // Always use blue border regardless of urgency
    return 'border-blue-500 hover:border-blue-600';
};

const getIconBgClass = (daysRemaining: number) => {
    if (daysRemaining < 0) {
        // Expired documents - red background
        return 'bg-red-100 dark:bg-red-900/30';
    }
    // Always use blue background regardless of urgency
    return 'bg-blue-100 dark:bg-blue-900/30';
};

const getIconColorClass = (daysRemaining: number) => {
    if (daysRemaining < 0) {
        // Expired documents - red color
        return 'text-red-600 dark:text-red-400';
    }
    // Always use blue color regardless of urgency
    return 'text-blue-600 dark:text-blue-400';
};

const getBadgeClasses = (daysRemaining: number) => {
    if (daysRemaining < 0) {
        // Expired documents - red badge
        return 'border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950/40 text-red-700 dark:text-red-300';
    }
    const tone = getUrgencyTone(daysRemaining);
    if (tone === 'critical') {
        return 'border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950/40 text-red-700 dark:text-red-300';
    }
    if (tone === 'warning') {
        return 'border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300';
    }
    return 'border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300';
};

const getToneIcon = () => {
    // Use consistent icon for all cards
    return CalendarClock;
};

const formatRemainingText = (daysRemaining: number) => {
    if (daysRemaining < 0) {
        const days = Math.abs(daysRemaining);
        return t('employees.expiry.expired_days_ago', { days });
    }

    if (daysRemaining === 0) {
        return t('employees.expiry.expires_today');
    }

    return t('employees.expiry.days_remaining', { days: daysRemaining });
};
</script>

<template>
    <Head :title="t('nav.dashboard')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6 pt-0">
            <!-- Header Section -->
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-foreground">
                        {{ t('nav.dashboard') }}
                    </h1>
                    <p class="text-muted-foreground mt-1">
                        {{ t('employees.expiry.upcoming_expirations') }}
                    </p>
                </div>
                <Button
                    v-if="expiringEmployeesCount > 0 || expiredEmployeesCount > 0"
                    asChild
                    variant="outline"
                    size="sm"
                    class="bg-white hover:bg-gray-50 border-gray-200"
                >
                    <Link :href="route('employees.expiring-documents.index')">
                        {{ t('employees.expiry.view_all') }} ({{ expiringEmployeesCount + expiredEmployeesCount }})
                        <ArrowUpRight class="ml-2 h-4 w-4" />
                    </Link>
                </Button>
            </div>

            <!-- Expired Documents Section -->
            <Card v-if="expiredEmployeesPreview && expiredEmployeesPreview.length > 0" class="shadow-sm border border-gray-200 dark:border-gray-800">
                <CardHeader class="border-b border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-900/50">
                    <CardTitle class="flex items-center gap-2 text-gray-900 dark:text-gray-100 text-lg font-semibold">
                        <AlertTriangle class="h-5 w-5 text-red-600 dark:text-red-400" />
                        {{ t('employees.expiry.expired_documents') }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-6">
                    <div class="grid gap-4 md:grid-cols-1 lg:grid-cols-2">
                        <Card
                            v-for="row in expiredEmployeesPreview"
                            :key="'expired-' + row.employee_id + '-' + row.expiry_field"
                            class="group cursor-pointer transition-shadow duration-200 hover:shadow-md border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950"
                            @click="$inertia.visit(route('employees.show', row.employee_id))"
                        >
                            <CardContent class="p-6">
                                <div class="flex items-start justify-between gap-6">
                                    <div class="flex-1 space-y-4">
                                        <div class="flex items-start gap-4">
                                            <div class="rounded-md p-3 bg-red-50 dark:bg-red-950/30 border border-red-100 dark:border-red-900/50 flex-shrink-0">
                                                <component 
                                                    :is="getToneIcon()" 
                                                    class="h-5 w-5 text-red-600 dark:text-red-400"
                                                />
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <h3 class="font-semibold text-base text-gray-900 dark:text-gray-100 mb-1.5">
                                                    {{ row.display_name || row.full_name }}
                                                </h3>
                                                <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">
                                                    {{ t(row.expiry_label_key) }}
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-500 pl-11 border-t border-gray-100 dark:border-gray-800 pt-3">
                                            <CalendarClock class="h-4 w-4 text-gray-400" />
                                            <span class="text-gray-600 dark:text-gray-400">{{ t('employees.expiry.expiry_date') }}:</span>
                                            <span class="font-semibold text-gray-900 dark:text-gray-100">
                                                {{ new Date(row.expiry_date).toLocaleDateString() }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="flex flex-col items-end gap-3 flex-shrink-0">
                                        <Badge 
                                            variant="outline" 
                                            class="font-medium text-xs px-3 py-1.5 border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950/40 text-red-700 dark:text-red-300"
                                        >
                                            {{ formatRemainingText(row.days_remaining) }}
                                        </Badge>
                                        <Button 
                                            variant="outline" 
                                            size="sm" 
                                            asChild 
                                            @click.stop 
                                            class="border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-900 font-medium"
                                        >
                                            <Link :href="route('employees.show', row.employee_id)">
                                                {{ t('employees.view') }}
                                            </Link>
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </CardContent>
            </Card>

            <!-- Upcoming Expirations Section -->
            <Card class="shadow-sm border border-gray-200 dark:border-gray-800">
                <CardHeader class="border-b border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-900/50">
                    <CardTitle class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        {{ t('employees.expiry.upcoming_expirations') }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-6">
                    <div v-if="!expiringEmployeesPreview || expiringEmployeesPreview.length === 0" 
                         class="py-12 text-center">
                        <div class="flex flex-col items-center gap-3">
                            <div class="rounded-full bg-blue-100 dark:bg-blue-900/30 p-4">
                                <ShieldAlert class="h-8 w-8 text-blue-600 dark:text-blue-400" />
                            </div>
                            <div>
                                <p class="text-sm font-medium text-foreground">
                                    {{ t('employees.expiry.no_upcoming', { days: expiryDaysThreshold }) }}
                                </p>
                                <p class="text-xs text-muted-foreground mt-1">
                                    {{ t('employees.expiry.all_documents_valid') }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div v-else class="grid gap-4 md:grid-cols-1 lg:grid-cols-2">
                        <Card
                            v-for="row in expiringEmployeesPreview"
                            :key="row.employee_id + '-' + row.expiry_field"
                            class="group cursor-pointer transition-shadow duration-200 hover:shadow-md border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950"
                            @click="$inertia.visit(route('employees.show', row.employee_id))"
                        >
                            <CardContent class="p-6">
                                <div class="flex items-start justify-between gap-6">
                                    <div class="flex-1 space-y-4">
                                        <div class="flex items-start gap-4">
                                            <div class="rounded-md p-3 bg-blue-50 dark:bg-blue-950/30 border border-blue-100 dark:border-blue-900/50 flex-shrink-0">
                                                <component 
                                                    :is="getToneIcon()" 
                                                    class="h-5 w-5 text-blue-600 dark:text-blue-400"
                                                />
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <h3 class="font-semibold text-base text-gray-900 dark:text-gray-100 mb-1.5">
                                                    {{ row.display_name || row.full_name }}
                                                </h3>
                                                <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">
                                                    {{ t(row.expiry_label_key) }}
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-500 pl-11 border-t border-gray-100 dark:border-gray-800 pt-3">
                                            <CalendarClock class="h-4 w-4 text-gray-400" />
                                            <span class="text-gray-600 dark:text-gray-400">{{ t('employees.expiry.expiry_date') }}:</span>
                                            <span class="font-semibold text-gray-900 dark:text-gray-100">
                                                {{ new Date(row.expiry_date).toLocaleDateString() }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="flex flex-col items-end gap-3 flex-shrink-0">
                                        <Badge 
                                            variant="outline" 
                                            class="font-medium text-xs px-3 py-1.5"
                                            :class="getBadgeClasses(row.days_remaining)"
                                        >
                                            {{ formatRemainingText(row.days_remaining) }}
                                        </Badge>
                                        <Button 
                                            variant="outline" 
                                            size="sm" 
                                            asChild 
                                            @click.stop 
                                            class="border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-900 font-medium"
                                        >
                                            <Link :href="route('employees.show', row.employee_id)">
                                                {{ t('employees.view') }}
                                            </Link>
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

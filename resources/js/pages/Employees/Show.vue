<template>
    <AppLayout>
        <div class="max-w-7xl mx-auto px-4 py-8">
            <div class="space-y-6">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
                <Card class="border-border/60 shadow-sm">
                    <CardContent class="pt-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="space-y-1">
                                <Heading :title="employee.full_name" />
                                <div class="flex items-center gap-2">
                                    <Badge :class="getStatusBadgeClass(employee.employment_status)">
                                        {{ t(`employees.status_${employee.employment_status}`) }}
                                    </Badge>
                                    <span class="text-sm font-mono text-muted-foreground">{{ displayValue(employee.employee_id) }}</span>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <Button variant="outline" asChild><Link :href="route('employees.edit', employee.id)">{{ t('employees.edit') }}</Link></Button>
                                <Button variant="secondary" asChild><Link :href="route('employees.custody.show', employee.id)">{{ t('custody.update_custody') }}</Link></Button>
                                <Button variant="default" asChild><Link :href="route('employees.leaves.create', employee.id)">{{ t('leaves.create_leave') }}</Link></Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card class="border-border/60 shadow-sm">
                    <CardHeader><CardTitle>{{ t('employees.general_information') }}</CardTitle></CardHeader>
                    <CardContent class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.employee_id') }}</Label><p>{{ displayValue(employee.employee_id) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.first_name') }}</Label><p>{{ displayValue(employee.first_name) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.father_name') }}</Label><p>{{ displayValue(employee.father_name) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.last_name') }}</Label><p>{{ displayValue(employee.last_name) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.nationality') }}</Label><p>{{ displayValue(employee.nationality?.name) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.residence_country') }}</Label><p>{{ displayValue(employee.residence_country?.name) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.birth_date') }}</Label><p>{{ displayDate(employee.birth_date) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.work_email') }}</Label><p>{{ displayValue(employee.work_email) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.personal_email') }}</Label><p>{{ displayValue(employee.personal_email) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">رقم الجوال الشخصي</Label><p>{{ displayValue(employee.phone) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">رقم جوال العمل</Label><p>{{ displayValue(employee.mobile) }}</p></div>
                    </CardContent>
                </Card>

                <Card class="border-border/60 shadow-sm">
                    <CardHeader><CardTitle>{{ t('employees.work_details') }}</CardTitle></CardHeader>
                    <CardContent class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.company') }}</Label><p>{{ displayValue(employee.company?.name_en || employee.company?.name_ar) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.employment_date') }}</Label><p>{{ displayDate(employee.employment_date) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.probation_end_date') }}</Label><p>{{ displayDate(employee.probation_end_date) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.fingerprint_device_id') }}</Label><p>{{ displayValue(employee.fingerprint_device_id) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.shift') }}</Label><p>{{ displayValue(employee.shift?.name) }}</p></div>
                        <div class="md:col-span-2 lg:col-span-3"><Label class="text-sm text-muted-foreground">{{ t('employees.work_address') }}</Label><p>{{ displayValue(employee.work_address) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.department') }}</Label><p>{{ displayValue(employee.department) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.job_title') }}</Label><p>{{ displayValue(employee.job_title) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.basic_salary') }}</Label><p>{{ displayCurrency(employee.basic_salary) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.allowances') }}</Label><p>{{ displayCurrency(employee.allowances) }}</p></div>
                    </CardContent>
                </Card>

                <Card class="border-border/60 shadow-sm">
                    <CardHeader><CardTitle>{{ t('leaves.annual_leave_section') }}</CardTitle></CardHeader>
                    <CardContent class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div><Label class="text-sm text-muted-foreground">{{ t('leaves.annual_leave_balance') }}</Label><p>{{ displayValue(employee.annual_leave_balance) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">{{ t('leaves.remaining_balance') }}</Label><p>{{ displayValue(employee.remaining_annual_leave_balance) }}</p></div>
                    </CardContent>
                </Card>

                <Card class="border-border/60 shadow-sm">
                    <CardHeader><CardTitle>{{ t('employees.legal_information') }}</CardTitle></CardHeader>
                    <CardContent class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.id_number') }}</Label><p>{{ displayValue(employee.id_number) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.residence_expiry_date') }}</Label><p>{{ displayDate(employee.residence_expiry_date) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.contract_end_date') }}</Label><p>{{ displayDate(employee.contract_end_date) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.exit_reentry_visa_expiry') }}</Label><p>{{ displayDate(employee.exit_reentry_visa_expiry) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.passport_number') }}</Label><p>{{ displayValue(employee.passport_number) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.passport_expiry_date') }}</Label><p>{{ displayDate(employee.passport_expiry_date) }}</p></div>
                    </CardContent>
                </Card>

                <Card class="border-border/60 shadow-sm">
                    <CardHeader><CardTitle>{{ t('employees.insurance') }}</CardTitle></CardHeader>
                    <CardContent class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.insurance_policy') }}</Label><p>{{ displayValue(employee.insurance_policy) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.insurance_expiry_date') }}</Label><p>{{ displayDate(employee.insurance_expiry_date) }}</p></div>
                    </CardContent>
                </Card>

                <Card class="border-border/60 shadow-sm">
                    <CardHeader><CardTitle>{{ t('employees.employment_status') }}</CardTitle></CardHeader>
                    <CardContent class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.hire_date') }}</Label><p>{{ displayDate(employee.hire_date) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.employment_status') }}</Label><p>{{ t(`employees.status_${employee.employment_status}`) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.termination_date') }}</Label><p>{{ displayDate(employee.termination_date) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.departure_date') }}</Label><p>{{ displayDate(employee.departure_date) }}</p></div>
                        <div class="md:col-span-2 lg:col-span-3"><Label class="text-sm text-muted-foreground">{{ t('employees.departure_reason') }}</Label><p>{{ displayValue(employee.departure_reason) }}</p></div>
                    </CardContent>
                </Card>

                <Card class="border-border/60 shadow-sm">
                    <CardHeader><CardTitle>{{ t('employees.managers_workflow') }}</CardTitle></CardHeader>
                    <CardContent class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.manager') }}</Label><p>{{ displayValue(employee.manager) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.direct_manager') }}</Label><p>{{ displayValue(employee.direct_manager) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.additional_approver_2') }}</Label><p>{{ displayValue(employee.additional_approver_2) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.additional_approver_3') }}</Label><p>{{ displayValue(employee.additional_approver_3) }}</p></div>
                    </CardContent>
                </Card>

                <Card class="border-border/60 shadow-sm">
                    <CardHeader><CardTitle>{{ t('employees.additional_information') }}</CardTitle></CardHeader>
                    <CardContent>
                        <Label class="text-sm text-muted-foreground">{{ t('employees.notes') }}</Label>
                        <p>{{ displayValue(employee.notes) }}</p>
                    </CardContent>
                </Card>

                <Card class="border-border/60 shadow-sm">
                    <CardHeader><CardTitle>{{ t('common.statistics') }}</CardTitle></CardHeader>
                    <CardContent class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <Label class="text-sm text-muted-foreground">{{ t('employees.assets_count') }}</Label>
                            <p>{{ displayValue(employee.assets_count) }}</p>
                        </div>
                        <div>
                            <Label class="text-sm text-muted-foreground">{{ t('employees.tickets_count') }}</Label>
                            <p>{{ displayValue(employee.reported_tickets_count) }}</p>
                        </div>
                    </CardContent>
                </Card>

                <Card class="border-border/60 shadow-sm">
                    <CardHeader><CardTitle>{{ t('common.created_at') }}</CardTitle></CardHeader>
                    <CardContent>
                        <p>{{ displayDate(employee.created_at) }}</p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { 
    Card, 
    CardContent, 
    CardHeader, 
    CardTitle 
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import Heading from '@/components/Heading.vue';
import Icon from '@/components/Icon.vue';
import type { Employee } from '@/types';
import type { BreadcrumbItem } from '@/types';

interface Props {
    employee: Employee;
}

const props = defineProps<Props>();
const { t } = useI18n();

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
        title: props.employee.full_name || `${props.employee.first_name} ${props.employee.last_name}`,
        href: `/employees/${props.employee.id}`,
    },
]);

const getStatusBadgeClass = (status: string): string => {
    switch (status) {
        case 'active':
            return 'bg-green-100 text-green-800';
        case 'inactive':
            return 'bg-yellow-100 text-yellow-800';
        case 'terminated':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

const formatCurrency = (amount: number) => {
    return amount.toFixed(2) + ' SAR';
};

const displayValue = (value: unknown, fallback = '-'): string => {
    if (value === null || value === undefined) return fallback;
    const str = String(value).trim();
    return str.length > 0 ? str : fallback;
};

const displayDate = (value: unknown): string => {
    if (!value) return '-';
    const date = new Date(String(value));
    if (Number.isNaN(date.getTime())) return '-';
    return date.toLocaleDateString();
};

const displayCurrency = (value: unknown): string => {
    const n = Number(value);
    if (Number.isNaN(n)) return '-';
    return formatCurrency(n);
};
</script> 
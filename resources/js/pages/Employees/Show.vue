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
                            <div v-if="canManageEmployees || canCreateLeaves || canUpdateEmployeeCustody || canSyncEmployeeFingerprintMonth" class="flex flex-wrap gap-2">
                                <Button v-if="canManageEmployees" variant="outline" asChild><Link :href="route('employees.edit', employee.id)">{{ t('employees.edit') }}</Link></Button>
                                <Button v-if="canUpdateEmployeeCustody" variant="secondary" asChild><Link :href="route('employees.custody.show', employee.id)">{{ t('custody.update_custody') }}</Link></Button>
                                <Button
                                    v-if="canSyncEmployeeFingerprintMonth"
                                    variant="secondary"
                                    :disabled="isSyncingFingerprintMonth"
                                    @click="syncFingerprintMonth"
                                >
                                    {{ isSyncingFingerprintMonth ? t('attendance.sync_fingerprint_month_processing') : t('attendance.sync_fingerprint_month') }}
                                </Button>
                                <Button v-if="canCreateLeaves" variant="default" asChild><Link :href="route('employees.leaves.create', employee.id)">{{ t('leaves.create_leave') }}</Link></Button>
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
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.personal_phone') }}</Label><p>{{ displayValue(employee.personal_phone) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.work_phone') }}</Label><p>{{ displayValue(employee.work_phone) }}</p></div>
                    </CardContent>
                </Card>

                <Card class="border-border/60 shadow-sm">
                    <CardHeader><CardTitle>{{ t('settings.permissions_teams') }}</CardTitle></CardHeader>
                    <CardContent class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <Label class="text-sm text-muted-foreground">{{ t('employees.employee_account') }}</Label>
                            <p>{{ props.portalAccount?.exists ? displayValue(props.portalAccount?.email) : '-' }}</p>
                        </div>
                        <div class="md:col-span-2">
                            <Label class="text-sm text-muted-foreground">{{ t('settings.assign_employee_teams') }}</Label>
                            <div v-if="(props.assignedTeams || []).length" class="mt-2 flex flex-wrap gap-2">
                                <Badge
                                    v-for="team in props.assignedTeams"
                                    :key="team.id"
                                    variant="secondary"
                                >
                                    {{ team.name }}
                                </Badge>
                            </div>
                            <p v-else class="mt-1">{{ t('settings.no_team') }}</p>
                        </div>
                    </CardContent>
                </Card>

                <Card class="border-border/60 shadow-sm">
                    <CardHeader><CardTitle>{{ t('employees.work_details') }}</CardTitle></CardHeader>
                    <CardContent class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.company') }}</Label><p>{{ displayValue(employee.company?.name_en || employee.company?.name_ar) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.fingerprint_device_id') }}</Label><p>{{ displayValue(employee.fingerprint_device_id) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.shift') }}</Label><p>{{ displayValue(employee.shift?.name) }}</p></div>
                        <div class="md:col-span-2 lg:col-span-3"><Label class="text-sm text-muted-foreground">{{ t('employees.work_address') }}</Label><p>{{ displayValue(employee.work_address) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.department') }}</Label><p>{{ displayValue(employee.department) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.job_title') }}</Label><p>{{ displayValue(employee.job_title) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.basic_salary') }}</Label><p>{{ displayCurrency(employee.basic_salary) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.allowances') }}</Label><p>{{ displayCurrency(employee.allowances) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.allowance_housing') }}</Label><p>{{ displayCurrency(employee.allowance_housing) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.allowance_transportation') }}</Label><p>{{ displayCurrency(employee.allowance_transportation) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.allowance_other') }}</Label><p>{{ displayCurrency(employee.allowance_other) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.allowance_food') }}</Label><p>{{ displayCurrency(employee.allowance_food) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.allowance_personal_car') }}</Label><p>{{ displayCurrency(employee.allowance_personal_car) }}</p></div>
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.social_insurance_deduction_rate') }}</Label><p>{{ employee.social_insurance_deduction_rate !== null && employee.social_insurance_deduction_rate !== undefined && employee.social_insurance_deduction_rate !== '' ? `${employee.social_insurance_deduction_rate}%` : '-' }}</p></div>
                    </CardContent>
                </Card>

                <Card class="border-border/60 shadow-sm">
                    <CardHeader><CardTitle>{{ t('leaves.annual_leave_section') }}</CardTitle></CardHeader>
                    <CardContent class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                        <div>
                            <Label class="text-sm text-muted-foreground">{{ t('leaves.annual_leave_balance') }}</Label>
                            <p>{{ displayValue(employee.annual_leave_balance) }}</p>
                            <p class="text-xs text-muted-foreground mt-1">{{ t('leaves.monthly_leave_accrual') }}: {{ monthlyAccrualLabel }}</p>
                        </div>
                        <div>
                            <Label class="text-sm text-muted-foreground">{{ t('leaves.accrued_leave_balance') }}</Label>
                            <p>{{ displayValue(employee.leave_accrued_balance) }}</p>
                        </div>
                        <div>
                            <Label class="text-sm text-muted-foreground">{{ t('leaves.leave_days_used') }}</Label>
                            <p>{{ displayValue(employee.leave_days_used) }}</p>
                        </div>
                        <div>
                            <Label class="text-sm text-muted-foreground">{{ t('leaves.remaining_balance') }}</Label>
                            <p class="font-medium">{{ displayValue(employee.remaining_annual_leave_balance) }}</p>
                        </div>
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
                        <div><Label class="text-sm text-muted-foreground">{{ t('employees.probation_end_date') }}</Label><p>{{ displayDate(employee.probation_end_date) }}</p></div>
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
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div><Label class="text-sm text-muted-foreground">{{ t('employees.emergency_contact_name') }}</Label><p>{{ displayValue(employee.emergency_contact_name) }}</p></div>
                            <div><Label class="text-sm text-muted-foreground">{{ t('employees.emergency_contact_phone') }}</Label><p>{{ displayValue(employee.emergency_contact_phone) }}</p></div>
                            <div><Label class="text-sm text-muted-foreground">{{ t('employees.emergency_contact_email') }}</Label><p>{{ displayValue(employee.emergency_contact_email) }}</p></div>
                            <div><Label class="text-sm text-muted-foreground">{{ t('employees.emergency_contact_address') }}</Label><p>{{ displayValue(employee.emergency_contact_address) }}</p></div>
                        </div>
                        <Label class="text-sm text-muted-foreground">{{ t('employees.notes') }}</Label>
                        <p>{{ displayValue(employee.notes) }}</p>
                    </CardContent>
                </Card>

                <Card class="border-border/60 shadow-sm">
                    <CardHeader><CardTitle>{{ t('debts.title') }}</CardTitle></CardHeader>
                    <CardContent>
                        <div v-if="employee.debts?.length" class="space-y-2">
                            <div
                                v-for="debt in employee.debts"
                                :key="debt.id"
                                class="flex items-center justify-between rounded-md border p-3"
                            >
                                <span>{{ displayValue(debt.debt_type || t('common.optional')) }}</span>
                                <span class="font-medium">{{ displayCurrency(debt.amount) }}</span>
                            </div>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">{{ t('debts.no_debts') }}</p>
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
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
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
    portalAccount?: {
        exists: boolean;
        email?: string | null;
    };
    assignedTeams?: Array<{
        id: number
        name: string
    }>;
    canManageEmployees?: boolean;
    canCreateLeaves?: boolean;
    canUpdateEmployeeCustody?: boolean;
    canSyncEmployeeFingerprintMonth?: boolean;
}

const props = defineProps<Props>();
const canManageEmployees = computed(() => props.canManageEmployees ?? true);
const canCreateLeaves = computed(() => props.canCreateLeaves ?? false);
const canUpdateEmployeeCustody = computed(() => props.canUpdateEmployeeCustody ?? false);
const canSyncEmployeeFingerprintMonth = computed(() => props.canSyncEmployeeFingerprintMonth ?? false);
const isSyncingFingerprintMonth = ref(false);
const { t } = useI18n();

const monthlyAccrualLabel = computed(() => {
    const entitlement = Number(props.employee.annual_leave_balance ?? 0);

    if (entitlement <= 0) {
        return '—';
    }

    return `${(entitlement / 12).toFixed(2)} ${t('leaves.days')}`;
});

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
        title: props.employee.full_name || [props.employee.first_name, props.employee.father_name, props.employee.last_name].filter(Boolean).join(' '),
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

const syncFingerprintMonth = () => {
    if (isSyncingFingerprintMonth.value) {
        return;
    }

    isSyncingFingerprintMonth.value = true;
    router.post(route('employees.sync-fingerprint-month', props.employee.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            isSyncingFingerprintMonth.value = false;
        },
    });
};
</script> 
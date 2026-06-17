<template>
    <Head :title="employee.full_name || t('employees.employee_details')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-6xl space-y-6 px-4 py-6">
            <!-- Profile hero -->
            <div class="overflow-hidden rounded-2xl border border-border/60 bg-gradient-to-br from-slate-50 via-white to-blue-50/40 shadow-sm">
                <div class="p-6 sm:p-8">
                    <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                        <div class="flex items-start gap-4">
                            <div
                                class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-blue-600 text-xl font-bold text-white shadow-md"
                            >
                                {{ initials }}
                            </div>
                            <div class="min-w-0 space-y-2">
                                <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                                    {{ employee.full_name }}
                                </h1>
                                <div class="flex flex-wrap items-center gap-2">
                                    <Badge :class="getStatusBadgeClass(employee.employment_status)">
                                        {{ t(`employees.status_${employee.employment_status}`) }}
                                    </Badge>
                                    <Badge v-if="employee.employee_id" variant="outline" class="font-mono">
                                        #{{ employee.employee_id }}
                                    </Badge>
                                </div>
                                <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
                                    <span v-if="employee.job_title" class="inline-flex items-center gap-1.5">
                                        <Icon name="Briefcase" class="h-4 w-4" />
                                        {{ displayValue(employee.job_title) }}
                                    </span>
                                    <span v-if="employee.department" class="inline-flex items-center gap-1.5">
                                        <Icon name="Building2" class="h-4 w-4" />
                                        {{ displayValue(employee.department) }}
                                    </span>
                                    <span v-if="employee.company" class="inline-flex items-center gap-1.5">
                                        <Icon name="Building" class="h-4 w-4" />
                                        {{ displayValue(employee.company?.name_en || employee.company?.name_ar) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="canManageEmployees || canCreateLeaves || canUpdateEmployeeCustody || canSyncEmployeeFingerprintMonth"
                            class="flex flex-wrap gap-2"
                        >
                            <Button v-if="canManageEmployees" variant="outline" size="sm" as-child>
                                <Link :href="route('employees.edit', employee.id)">
                                    <Icon name="Pencil" class="h-4 w-4" />
                                    {{ t('employees.edit') }}
                                </Link>
                            </Button>
                            <Button v-if="canUpdateEmployeeCustody" variant="secondary" size="sm" as-child>
                                <Link :href="route('employees.custody.show', employee.id)">
                                    {{ t('custody.update_custody') }}
                                </Link>
                            </Button>
                            <Button
                                v-if="canSyncEmployeeFingerprintMonth"
                                variant="secondary"
                                size="sm"
                                :disabled="isSyncingFingerprintMonth"
                                @click="syncFingerprintMonth"
                            >
                                {{ isSyncingFingerprintMonth ? t('attendance.sync_fingerprint_month_processing') : t('attendance.sync_fingerprint_month') }}
                            </Button>
                            <Button v-if="canCreateLeaves" size="sm" as-child>
                                <Link :href="route('employees.leaves.create', employee.id)">
                                    {{ t('leaves.create_leave') }}
                                </Link>
                            </Button>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div
                            v-if="employee.work_email"
                            class="flex items-center gap-3 rounded-xl border border-white/80 bg-white/70 px-4 py-3 text-sm shadow-sm"
                            dir="ltr"
                        >
                            <Icon name="Mail" class="h-4 w-4 shrink-0 text-blue-600" />
                            <span class="truncate">{{ employee.work_email }}</span>
                        </div>
                        <div
                            v-if="employee.work_phone"
                            class="flex items-center gap-3 rounded-xl border border-white/80 bg-white/70 px-4 py-3 text-sm shadow-sm"
                            dir="ltr"
                        >
                            <Icon name="Phone" class="h-4 w-4 shrink-0 text-blue-600" />
                            <span>{{ employee.work_phone }}</span>
                        </div>
                        <div
                            v-if="employee.hire_date"
                            class="flex items-center gap-3 rounded-xl border border-white/80 bg-white/70 px-4 py-3 text-sm shadow-sm"
                        >
                            <Icon name="Calendar" class="h-4 w-4 shrink-0 text-blue-600" />
                            <span>{{ t('employees.hire_date') }}: {{ displayDate(employee.hire_date) }}</span>
                        </div>
                        <div
                            v-if="employee.shift?.name"
                            class="flex items-center gap-3 rounded-xl border border-white/80 bg-white/70 px-4 py-3 text-sm shadow-sm"
                        >
                            <Icon name="Clock" class="h-4 w-4 shrink-0 text-blue-600" />
                            <span>{{ employee.shift.name }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick stats -->
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <Card class="border-border/60 shadow-sm">
                    <CardContent class="flex items-center gap-4 p-4">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                            <Icon name="CalendarDays" class="h-5 w-5" />
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">{{ t('leaves.remaining_balance') }}</p>
                            <p class="text-xl font-bold">{{ displayValue(employee.remaining_annual_leave_balance) }}</p>
                        </div>
                    </CardContent>
                </Card>
                <Card class="border-border/60 shadow-sm">
                    <CardContent class="flex items-center gap-4 p-4">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-50 text-violet-600">
                            <Icon name="Package" class="h-5 w-5" />
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">{{ t('employees.assets_count') }}</p>
                            <p class="text-xl font-bold">{{ displayValue(employee.assets_count) }}</p>
                        </div>
                    </CardContent>
                </Card>
                <Card class="border-border/60 shadow-sm">
                    <CardContent class="flex items-center gap-4 p-4">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                            <Icon name="FolderOpen" class="h-5 w-5" />
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">{{ t('employees.documents.title') }}</p>
                            <p class="text-xl font-bold">{{ documentsCount }}/5</p>
                        </div>
                    </CardContent>
                </Card>
                <Card class="border-border/60 shadow-sm">
                    <CardContent class="flex items-center gap-4 p-4">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                            <Icon name="Banknote" class="h-5 w-5" />
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">{{ t('employees.basic_salary') }}</p>
                            <p class="text-lg font-bold">{{ displayCurrency(employee.basic_salary) }}</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Sections -->
            <div class="space-y-4">
                <EmployeeShowSection
                    :title="t('employees.general_information')"
                    icon="User"
                    icon-class="text-blue-600"
                    :default-open="true"
                >
                    <div class="grid grid-cols-1 items-stretch gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <EmployeeInfoField :label="t('employees.first_name')" :value="displayValue(employee.first_name)" />
                        <EmployeeInfoField :label="t('employees.father_name')" :value="displayValue(employee.father_name)" />
                        <EmployeeInfoField :label="t('employees.last_name')" :value="displayValue(employee.last_name)" />
                        <EmployeeInfoField :label="t('employees.nationality')" :value="displayValue(employee.nationality?.name)" />
                        <EmployeeInfoField :label="t('employees.residence_country')" :value="displayValue(employee.residence_country?.name)" />
                        <EmployeeInfoField :label="t('employees.birth_date')" :value="displayDate(employee.birth_date)" />
                        <EmployeeInfoField :label="t('employees.work_email')" :value="displayValue(employee.work_email)" dir="ltr" />
                        <EmployeeInfoField :label="t('employees.personal_email')" :value="displayValue(employee.personal_email)" dir="ltr" />
                        <EmployeeInfoField :label="t('employees.work_phone')" :value="displayValue(employee.work_phone)" dir="ltr" />
                        <EmployeeInfoField :label="t('employees.personal_phone')" :value="displayValue(employee.personal_phone)" dir="ltr" />
                    </div>
                </EmployeeShowSection>

                <EmployeeDocumentsSection :documents="documents" mode="show" />

                <EmployeeShowSection
                    v-if="portalAccount?.exists || (assignedTeams || []).length"
                    :title="t('settings.permissions_teams')"
                    icon="Shield"
                    icon-class="text-violet-600"
                >
                    <div class="grid grid-cols-1 items-stretch gap-3 sm:grid-cols-2">
                        <EmployeeInfoField
                            :label="t('employees.employee_account')"
                            :value="portalAccount?.exists ? displayValue(portalAccount?.email) : '-'"
                            dir="ltr"
                        />
                        <div class="flex h-full min-w-0 flex-col rounded-lg p-3 sm:col-span-2">
                            <p class="mb-2 min-h-[2.75rem] text-xs font-medium leading-snug text-muted-foreground text-start">
                                {{ t('settings.assign_employee_teams') }}
                            </p>
                            <div v-if="(assignedTeams || []).length" class="flex flex-wrap gap-2">
                                <Badge v-for="team in assignedTeams" :key="team.id" variant="secondary">
                                    {{ team.name }}
                                </Badge>
                            </div>
                            <p v-else class="text-sm text-muted-foreground">{{ t('settings.no_team') }}</p>
                        </div>
                    </div>
                </EmployeeShowSection>

                <EmployeeShowSection :title="t('employees.work_details')" icon="Briefcase" icon-class="text-indigo-600">
                    <div class="grid grid-cols-1 items-stretch gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <EmployeeInfoField :label="t('employees.company')" :value="displayValue(employee.company?.name_en || employee.company?.name_ar)" />
                        <EmployeeInfoField :label="t('employees.department')" :value="displayValue(employee.department)" />
                        <EmployeeInfoField :label="t('employees.job_title')" :value="displayValue(employee.job_title)" />
                        <EmployeeInfoField :label="t('employees.shift')" :value="displayValue(employee.shift?.name)" />
                        <EmployeeInfoField :label="t('employees.fingerprint_device_id')" :value="displayValue(employee.fingerprint_device_id)" mono />
                        <EmployeeInfoField :label="t('employees.work_address')" :value="displayValue(employee.work_address)" />
                    </div>
                    <div class="mt-4 rounded-xl border border-border/60 bg-muted/20 p-4">
                        <p class="mb-3 text-sm font-semibold text-foreground">{{ t('employees.compensation') }}</p>
                        <div class="grid grid-cols-1 items-stretch gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <EmployeeInfoField :label="t('employees.basic_salary')" :value="displayCurrency(employee.basic_salary)" highlight />
                            <EmployeeInfoField :label="t('employees.allowances')" :value="displayCurrency(employee.allowances)" />
                            <EmployeeInfoField :label="t('employees.allowance_housing')" :value="displayCurrency(employee.allowance_housing)" />
                            <EmployeeInfoField :label="t('employees.allowance_transportation')" :value="displayCurrency(employee.allowance_transportation)" />
                            <EmployeeInfoField :label="t('employees.allowance_food')" :value="displayCurrency(employee.allowance_food)" />
                            <EmployeeInfoField :label="t('employees.allowance_personal_car')" :value="displayCurrency(employee.allowance_personal_car)" />
                            <EmployeeInfoField :label="t('employees.allowance_other')" :value="displayCurrency(employee.allowance_other)" />
                            <EmployeeInfoField
                                :label="t('employees.social_insurance_deduction_rate')"
                                :value="formatPercent(employee.social_insurance_deduction_rate)"
                            />
                        </div>
                    </div>
                </EmployeeShowSection>

                <EmployeeShowSection :title="t('leaves.annual_leave_section')" icon="CalendarDays" icon-class="text-emerald-600">
                    <div class="grid grid-cols-2 items-stretch gap-3 lg:grid-cols-4">
                        <EmployeeInfoField :label="t('leaves.annual_leave_balance')" :value="displayValue(employee.annual_leave_balance)" highlight />
                        <EmployeeInfoField :label="t('leaves.accrued_leave_balance')" :value="displayValue(employee.leave_accrued_balance)" />
                        <EmployeeInfoField :label="t('leaves.leave_days_used')" :value="displayValue(employee.leave_days_used)" />
                        <EmployeeInfoField :label="t('leaves.remaining_balance')" :value="displayValue(employee.remaining_annual_leave_balance)" highlight />
                    </div>
                    <p class="mt-3 text-xs text-muted-foreground">
                        {{ t('leaves.monthly_leave_accrual') }}: {{ monthlyAccrualLabel }}
                    </p>
                </EmployeeShowSection>

                <div class="grid gap-4 lg:grid-cols-2">
                    <EmployeeShowSection :title="t('employees.legal_information')" icon="FileText" icon-class="text-orange-600">
                        <div class="grid grid-cols-1 items-stretch gap-3 sm:grid-cols-2">
                            <EmployeeInfoField :label="t('employees.id_number')" :value="displayValue(employee.id_number)" mono />
                            <EmployeeInfoField :label="t('employees.passport_number')" :value="displayValue(employee.passport_number)" mono />
                            <EmployeeInfoField :label="t('employees.residence_expiry_date')" :value="displayDate(employee.residence_expiry_date)" />
                            <EmployeeInfoField :label="t('employees.passport_expiry_date')" :value="displayDate(employee.passport_expiry_date)" />
                            <EmployeeInfoField :label="t('employees.contract_end_date')" :value="displayDate(employee.contract_end_date)" />
                            <EmployeeInfoField :label="t('employees.exit_reentry_visa_expiry')" :value="displayDate(employee.exit_reentry_visa_expiry)" />
                        </div>
                    </EmployeeShowSection>

                    <EmployeeShowSection :title="t('employees.insurance')" icon="Heart" icon-class="text-rose-600">
                        <div class="grid grid-cols-1 items-stretch gap-3">
                            <EmployeeInfoField :label="t('employees.insurance_policy')" :value="displayValue(employee.insurance_policy)" />
                            <EmployeeInfoField :label="t('employees.insurance_expiry_date')" :value="displayDate(employee.insurance_expiry_date)" />
                        </div>
                    </EmployeeShowSection>
                </div>

                <EmployeeShowSection :title="t('employees.employment_status')" icon="CalendarDays" icon-class="text-sky-600">
                    <div class="grid grid-cols-1 items-stretch gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <EmployeeInfoField :label="t('employees.hire_date')" :value="displayDate(employee.hire_date)" />
                        <EmployeeInfoField :label="t('employees.probation_end_date')" :value="displayDate(employee.probation_end_date)" />
                        <EmployeeInfoField :label="t('employees.employment_status')" :value="t(`employees.status_${employee.employment_status}`)" />
                        <EmployeeInfoField :label="t('employees.termination_date')" :value="displayDate(employee.termination_date)" />
                        <EmployeeInfoField :label="t('employees.departure_date')" :value="displayDate(employee.departure_date)" />
                        <EmployeeInfoField :label="t('employees.departure_reason')" :value="displayValue(employee.departure_reason)" />
                    </div>
                </EmployeeShowSection>

                <EmployeeShowSection :title="t('employees.managers_workflow')" icon="Users" icon-class="text-cyan-600">
                    <div class="grid grid-cols-1 items-stretch gap-3 sm:grid-cols-2">
                        <EmployeeInfoField :label="t('employees.manager')" :value="displayValue(employee.manager)" />
                        <EmployeeInfoField :label="t('employees.direct_manager')" :value="displayValue(employee.direct_manager)" />
                        <EmployeeInfoField :label="t('employees.additional_approver_2')" :value="displayValue(employee.additional_approver_2)" />
                        <EmployeeInfoField :label="t('employees.additional_approver_3')" :value="displayValue(employee.additional_approver_3)" />
                    </div>
                </EmployeeShowSection>

                <EmployeeShowSection :title="t('employees.additional_information')" icon="Info" icon-class="text-slate-600">
                    <div class="grid grid-cols-1 items-stretch gap-3 sm:grid-cols-2">
                        <EmployeeInfoField :label="t('employees.emergency_contact_name')" :value="displayValue(employee.emergency_contact_name)" />
                        <EmployeeInfoField :label="t('employees.emergency_contact_phone')" :value="displayValue(employee.emergency_contact_phone)" dir="ltr" />
                        <EmployeeInfoField :label="t('employees.emergency_contact_email')" :value="displayValue(employee.emergency_contact_email)" dir="ltr" />
                        <EmployeeInfoField :label="t('employees.emergency_contact_address')" :value="displayValue(employee.emergency_contact_address)" />
                    </div>
                    <div class="mt-3 rounded-lg border border-dashed border-border/80 bg-muted/10 p-4">
                        <p class="mb-2 text-xs font-medium leading-snug text-muted-foreground text-start">{{ t('employees.notes') }}</p>
                        <p class="text-sm leading-relaxed text-foreground text-start">{{ displayValue(employee.notes) }}</p>
                    </div>
                </EmployeeShowSection>

                <EmployeeShowSection :title="t('debts.title')" icon="Wallet" icon-class="text-amber-700">
                    <div v-if="employee.debts?.length" class="space-y-2">
                        <div
                            v-for="debt in employee.debts"
                            :key="debt.id"
                            class="flex items-center justify-between rounded-xl border border-border/60 bg-muted/20 px-4 py-3"
                        >
                            <span class="text-sm font-medium">{{ displayValue(debt.debt_type || t('common.optional')) }}</span>
                            <span class="font-semibold text-amber-800">{{ displayCurrency(debt.amount) }}</span>
                        </div>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">{{ t('debts.no_debts') }}</p>
                </EmployeeShowSection>
            </div>

            <p class="text-center text-xs text-muted-foreground">
                {{ t('common.created_at') }}: {{ displayDate(employee.created_at) }}
            </p>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import Icon from '@/components/Icon.vue';
import EmployeeDocumentsSection from '@/components/employees/EmployeeDocumentsSection.vue';
import EmployeeShowSection from '@/components/employees/EmployeeShowSection.vue';
import EmployeeInfoField from '@/components/employees/EmployeeInfoField.vue';
import type { EmployeeDocumentItem } from '@/constants/employeeDocuments';
import type { Employee, BreadcrumbItem } from '@/types';

interface Props {
    employee: Employee;
    documents?: EmployeeDocumentItem[];
    portalAccount?: {
        exists: boolean;
        email?: string | null;
    };
    assignedTeams?: Array<{
        id: number;
        name: string;
    }>;
    canManageEmployees?: boolean;
    canCreateLeaves?: boolean;
    canUpdateEmployeeCustody?: boolean;
    canSyncEmployeeFingerprintMonth?: boolean;
}

const props = defineProps<Props>();
const { t } = useI18n();

const canManageEmployees = computed(() => props.canManageEmployees ?? true);
const canCreateLeaves = computed(() => props.canCreateLeaves ?? false);
const canUpdateEmployeeCustody = computed(() => props.canUpdateEmployeeCustody ?? false);
const canSyncEmployeeFingerprintMonth = computed(() => props.canSyncEmployeeFingerprintMonth ?? false);
const isSyncingFingerprintMonth = ref(false);

const documentsCount = computed(() => props.documents?.length ?? 0);

const initials = computed(() => {
    const parts = [props.employee.first_name, props.employee.last_name]
        .map((part) => (part ? String(part).charAt(0) : ''))
        .join('')
        .toUpperCase();

    return parts || '?';
});

const monthlyAccrualLabel = computed(() => {
    const entitlement = Number(props.employee.annual_leave_balance ?? 0);

    if (entitlement <= 0) {
        return '—';
    }

    return `${(entitlement / 12).toFixed(2)} ${t('leaves.days')}`;
});

const breadcrumbs = computed((): BreadcrumbItem[] => [
    { title: t('nav.dashboard'), href: '/dashboard' },
    { title: t('employees.title'), href: '/employees' },
    {
        title: props.employee.full_name || [props.employee.first_name, props.employee.father_name, props.employee.last_name].filter(Boolean).join(' '),
        href: `/employees/${props.employee.id}`,
    },
]);

const getStatusBadgeClass = (status: string): string => {
    switch (status) {
        case 'active':
            return 'bg-emerald-100 text-emerald-800';
        case 'inactive':
            return 'bg-amber-100 text-amber-800';
        case 'terminated':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-slate-100 text-slate-800';
    }
};

const displayValue = (value: unknown, fallback = '-'): string => {
    if (value === null || value === undefined) {
        return fallback;
    }

    const str = String(value).trim();

    return str.length > 0 ? str : fallback;
};

const displayDate = (value: unknown): string => {
    if (!value) {
        return '-';
    }

    const date = new Date(String(value));

    if (Number.isNaN(date.getTime())) {
        return '-';
    }

    return date.toLocaleDateString();
};

const displayCurrency = (value: unknown): string => {
    const amount = Number(value);

    if (Number.isNaN(amount)) {
        return '-';
    }

    return `${amount.toFixed(2)} SAR`;
};

const formatPercent = (value: unknown): string => {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    return `${value}%`;
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

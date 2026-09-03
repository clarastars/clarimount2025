<template>
    <div class="space-y-6">
        <div v-if="showEmployeeSelect">
            <Label for="employee_id" class="mb-2">{{ t('leaves.select_employee') }} *</Label>
            <select
                id="employee_id"
                v-model="form.employee_id"
                required
                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            >
                <option value="">{{ t('leaves.select_employee_placeholder') }}</option>
                <option v-for="employee in employees" :key="employee.id" :value="employee.id">
                    {{ employee.full_name }}
                </option>
            </select>
            <p v-if="form.errors.employee_id" class="text-red-500 text-sm mt-1">{{ form.errors.employee_id }}</p>
        </div>

        <div>
            <Label for="leave_type" class="mb-2">{{ t('leaves.leave_type') }} *</Label>
            <select
                id="leave_type"
                v-model="form.leave_type"
                required
                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            >
                <option value="">{{ t('leaves.select_leave_type') }}</option>
                <option v-for="leaveType in leaveTypes" :key="leaveType.key" :value="leaveType.key">
                    {{ leaveType.label }}
                </option>
            </select>
            <p
                v-if="selectedLeaveType && selectedLeaveType.min_notice_days > 0"
                class="text-xs text-muted-foreground mt-1"
            >
                {{ t('leaves.min_notice_days_hint_frontend', { days: formattedMinNoticeDays }) }}
            </p>
            <p
                v-if="selectedLeaveType?.allow_past_dates"
                class="text-xs text-muted-foreground mt-1"
            >
                {{ t('leaves.allow_past_dates_hint_frontend') }}
            </p>
            <p v-if="form.errors.leave_type" class="text-red-500 text-sm mt-1">{{ form.errors.leave_type }}</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <Label for="start_date" class="mb-2">{{ t('leaves.start_date') }} *</Label>
                <Input
                    id="start_date"
                    v-model="form.start_date"
                    type="date"
                    :min="startDateMin"
                    required
                />
                <p v-if="form.errors.start_date" class="text-red-500 text-sm mt-1">{{ form.errors.start_date }}</p>
            </div>
            <div>
                <Label for="end_date" class="mb-2">{{ t('leaves.end_date') }} *</Label>
                <Input
                    id="end_date"
                    v-model="form.end_date"
                    type="date"
                    :min="form.start_date || startDateMin || undefined"
                    required
                />
                <p v-if="form.errors.end_date" class="text-red-500 text-sm mt-1">{{ form.errors.end_date }}</p>
            </div>
        </div>

        <div>
            <Label for="attachments" class="mb-2">{{ t('leaves.attachment') }}</Label>
            <Input
                id="attachments"
                type="file"
                multiple
                accept=".pdf,.jpg,.jpeg,.png"
                @change="onAttachmentsChange"
            />
            <p class="text-xs text-muted-foreground mt-1">{{ t('leaves.attachment_hint') }}</p>
            <ul v-if="(form.attachments ?? []).length > 0" class="mt-2 space-y-1 text-sm text-muted-foreground">
                <li v-for="(file, index) in form.attachments" :key="`${file.name}-${index}`">
                    {{ file.name }}
                </li>
            </ul>
            <p v-if="attachmentError" class="text-red-500 text-sm mt-1">{{ attachmentError }}</p>
        </div>

        <div>
            <Label class="mb-2 block">{{ t('leaves.deduct_from_balance_label') }}</Label>
            <div class="flex gap-6">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input
                        v-model="form.deduct_from_balance"
                        type="radio"
                        :value="true"
                    />
                    <span>{{ t('leaves.deduct_yes') }}</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input
                        v-model="form.deduct_from_balance"
                        type="radio"
                        :value="false"
                    />
                    <span>{{ t('leaves.deduct_no') }}</span>
                </label>
            </div>
            <p v-if="form.errors.deduct_from_balance" class="text-red-500 text-sm mt-1">{{ form.errors.deduct_from_balance }}</p>
            <div
                v-if="balancePreview"
                class="mt-3 rounded-md border px-3 py-2.5 text-sm"
                :class="balancePreview.sufficient
                    ? 'border-sky-200 bg-sky-50 text-sky-950 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-100'
                    : 'border-red-200 bg-red-50 text-red-900 dark:border-red-800 dark:bg-red-950/40 dark:text-red-100'"
            >
                <p>
                    {{ t('leaves.projected_balance_hint_frontend', {
                        current: formatDays(balancePreview.current),
                        date: balancePreview.date,
                        projected: formatDays(balancePreview.projected),
                    }) }}
                </p>
                <p class="mt-1 font-medium">
                    {{ balancePreview.sufficient
                        ? t('leaves.projected_balance_enough_frontend', { requested: formatDays(balancePreview.requested) })
                        : t('leaves.projected_balance_short_frontend', {
                            projected: formatDays(balancePreview.projected),
                            requested: formatDays(balancePreview.requested),
                        })
                    }}
                </p>
            </div>
        </div>

        <div>
            <Label class="mb-2 block">{{ t('leaves.paid_leave_label') }}</Label>
            <div class="flex gap-6">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input
                        v-model="form.is_paid"
                        type="radio"
                        :value="true"
                    />
                    <span>{{ t('leaves.paid_yes') }}</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input
                        v-model="form.is_paid"
                        type="radio"
                        :value="false"
                    />
                    <span>{{ t('leaves.paid_no') }}</span>
                </label>
            </div>
            <p v-if="form.errors.is_paid" class="text-red-500 text-sm mt-1">{{ form.errors.is_paid }}</p>
        </div>

        <div>
            <Label for="notes" class="mb-2">{{ t('leaves.notes') }}</Label>
            <textarea
                id="notes"
                v-model="form.notes"
                rows="3"
                class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                :placeholder="t('leaves.notes_placeholder')"
            />
            <p v-if="form.errors.notes" class="text-red-500 text-sm mt-1">{{ form.errors.notes }}</p>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface EmployeeOption {
    id: number;
    full_name: string;
    remaining_annual_leave_balance?: number | string | null;
    monthly_leave_accrual?: number | string | null;
}

interface LeaveTypeOption {
    key: string;
    label: string;
    min_notice_days: number;
    allow_past_dates?: boolean;
}

interface LeaveForm {
    employee_id?: string | number;
    leave_type: string;
    start_date: string;
    end_date: string;
    deduct_from_balance: boolean;
    is_paid: boolean;
    notes: string;
    attachments: File[];
    errors: Record<string, string>;
}

const emit = defineEmits<{
    attachmentsChange: [files: File[]];
}>();

const { t, locale } = useI18n();
const props = defineProps<{
    form: LeaveForm;
    showEmployeeSelect?: boolean;
    employees?: EmployeeOption[];
    leaveTypes: LeaveTypeOption[];
    currentRemaining?: number | string | null;
    monthlyAccrual?: number | string | null;
}>();

const selectedLeaveType = computed(() => props.leaveTypes.find((item) => item.key === props.form.leave_type) ?? null);
const startDateMin = computed(() => {
    if (!selectedLeaveType.value || selectedLeaveType.value.allow_past_dates) {
        return undefined;
    }

    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
});
const formattedMinNoticeDays = computed(() => {
    if (!selectedLeaveType.value) {
        return '';
    }

    return formatDays(selectedLeaveType.value.min_notice_days);
});

const selectedEmployee = computed(() => {
    if (!props.showEmployeeSelect || props.form.employee_id === undefined || props.form.employee_id === '') {
        return null;
    }

    return (props.employees ?? []).find((item) => String(item.id) === String(props.form.employee_id)) ?? null;
});

const currentRemainingValue = computed(() => {
    const fromEmployee = selectedEmployee.value?.remaining_annual_leave_balance;
    if (fromEmployee !== undefined && fromEmployee !== null && fromEmployee !== '') {
        return Number(fromEmployee);
    }

    if (props.currentRemaining === undefined || props.currentRemaining === null || props.currentRemaining === '') {
        return null;
    }

    return Number(props.currentRemaining);
});

const monthlyAccrualValue = computed(() => {
    const fromEmployee = selectedEmployee.value?.monthly_leave_accrual;
    if (fromEmployee !== undefined && fromEmployee !== null && fromEmployee !== '') {
        return Number(fromEmployee);
    }

    if (props.monthlyAccrual === undefined || props.monthlyAccrual === null || props.monthlyAccrual === '') {
        return null;
    }

    return Number(props.monthlyAccrual);
});

const balancePreview = computed(() => {
    if (!props.form.deduct_from_balance || !props.form.start_date || !props.form.end_date) {
        return null;
    }

    if (currentRemainingValue.value === null || monthlyAccrualValue.value === null) {
        return null;
    }

    const startDate = new Date(`${props.form.start_date}T00:00:00`);
    const endDate = new Date(`${props.form.end_date}T00:00:00`);

    if (Number.isNaN(startDate.getTime()) || Number.isNaN(endDate.getTime()) || endDate < startDate) {
        return null;
    }

    const requested = Math.floor((endDate.getTime() - startDate.getTime()) / 86400000) + 1;
    const projected = projectedRemainingOnDate(
        currentRemainingValue.value,
        monthlyAccrualValue.value,
        startDate,
    );

    return {
        current: currentRemainingValue.value,
        projected,
        requested,
        date: props.form.start_date,
        sufficient: projected + 0.0001 >= requested,
    };
});

function formatDays(value: number): string {
    return new Intl.NumberFormat(locale.value === 'ar' ? 'ar-SA' : 'en-US', {
        minimumFractionDigits: Number.isInteger(value) ? 0 : 2,
        maximumFractionDigits: 2,
    }).format(value);
}

function projectedRemainingOnDate(currentRemaining: number, monthlyAccrual: number, startDate: Date): number {
    if (monthlyAccrual <= 0) {
        return roundDays(currentRemaining);
    }

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const currentMonth = new Date(today.getFullYear(), today.getMonth(), 1);
    const startMonth = new Date(startDate.getFullYear(), startDate.getMonth(), 1);

    if (startMonth <= currentMonth) {
        return roundDays(currentRemaining);
    }

    let extraMonths = 0;
    const cursor = new Date(currentMonth);
    cursor.setMonth(cursor.getMonth() + 1);

    while (cursor <= startMonth) {
        extraMonths += 1;
        cursor.setMonth(cursor.getMonth() + 1);
    }

    return roundDays(currentRemaining + extraMonths * monthlyAccrual);
}

function roundDays(value: number): number {
    return Math.round(value * 100) / 100;
}

const attachmentError = computed(() => {
    if (props.form.errors.attachments) {
        return props.form.errors.attachments;
    }

    const nested = Object.entries(props.form.errors).find(([key]) => key.startsWith('attachments.'));

    return nested?.[1] ?? props.form.errors.attachment ?? '';
});

function onAttachmentsChange(event: Event) {
    const target = event.target as HTMLInputElement;
    emit('attachmentsChange', target.files ? Array.from(target.files) : []);
}
</script>

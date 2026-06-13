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
                <option value="annual">{{ t('leaves.type_annual') }}</option>
                <option value="sick">{{ t('leaves.type_sick') }}</option>
                <option value="marriage">{{ t('leaves.type_marriage') }}</option>
                <option value="emergency">{{ t('leaves.type_emergency') }}</option>
                <option value="maternity">{{ t('leaves.type_maternity') }}</option>
            </select>
            <p v-if="form.errors.leave_type" class="text-red-500 text-sm mt-1">{{ form.errors.leave_type }}</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <Label for="start_date" class="mb-2">{{ t('leaves.start_date') }} *</Label>
                <Input
                    id="start_date"
                    v-model="form.start_date"
                    type="date"
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
                    required
                />
                <p v-if="form.errors.end_date" class="text-red-500 text-sm mt-1">{{ form.errors.end_date }}</p>
            </div>
        </div>

        <div>
            <Label for="attachment" class="mb-2">{{ t('leaves.attachment') }}</Label>
            <Input
                id="attachment"
                type="file"
                accept=".pdf,.jpg,.jpeg,.png"
                @change="onAttachmentChange"
            />
            <p class="text-xs text-muted-foreground mt-1">{{ t('leaves.attachment_hint') }}</p>
            <p v-if="form.errors.attachment" class="text-red-500 text-sm mt-1">{{ form.errors.attachment }}</p>
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
import { useI18n } from 'vue-i18n';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface EmployeeOption {
    id: number;
    full_name: string;
}

interface LeaveForm {
    employee_id?: string | number;
    leave_type: string;
    start_date: string;
    end_date: string;
    deduct_from_balance: boolean;
    is_paid: boolean;
    notes: string;
    attachment: File | null;
    errors: Record<string, string>;
}

defineProps<{
    form: LeaveForm;
    showEmployeeSelect?: boolean;
    employees?: EmployeeOption[];
}>();

const emit = defineEmits<{
    attachmentChange: [file: File | null];
}>();

const { t } = useI18n();

function onAttachmentChange(event: Event) {
    const target = event.target as HTMLInputElement;
    emit('attachmentChange', target.files?.[0] ?? null);
}
</script>

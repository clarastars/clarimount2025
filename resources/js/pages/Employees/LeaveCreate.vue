<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="max-w-2xl mx-auto py-8 px-4">
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Icon name="CalendarPlus" class="h-5 w-5 text-amber-600" />
                        {{ t('leaves.create_leave') }}
                    </CardTitle>
                    <CardDescription>
                        {{ employee.full_name }} — {{ t('leaves.create_leave_description') }}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-6">
                        <LeaveFormFields
                            :form="form"
                            :leave-types="leaveTypes"
                            :current-remaining="employee.remaining_annual_leave_balance"
                            :monthly-accrual="employee.monthly_leave_accrual"
                            @attachment-change="onAttachmentChange"
                        />

                        <div class="flex justify-end gap-4 pt-4">
                            <Button type="button" variant="outline" asChild>
                                <Link :href="route('employees.show', employee.id)">{{ t('common.cancel') }}</Link>
                            </Button>
                            <Button type="submit" :disabled="form.processing">
                                <span v-if="form.processing">{{ t('common.saving') }}</span>
                                <span v-else>{{ t('leaves.submit') }}</span>
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import Icon from '@/components/Icon.vue';
import LeaveFormFields from '@/components/leaves/LeaveFormFields.vue';
import type { BreadcrumbItem } from '@/types';

const { t, locale } = useI18n();

const props = defineProps<{
    employee: { id: number; full_name: string; remaining_annual_leave_balance?: number | string | null; monthly_leave_accrual?: number | string | null };
    leaveTypes: Array<{
        key: string;
        label: string;
        min_notice_days: number;
        allow_past_dates?: boolean;
    }>;
}>();

const { employee } = props;

const breadcrumbs = computed((): BreadcrumbItem[] => [
    { title: t('nav.dashboard'), href: '/dashboard' },
    { title: t('employees.title'), href: '/employees' },
    { title: employee.full_name, href: route('employees.show', employee.id) },
    { title: t('leaves.create_leave'), href: route('employees.leaves.create', employee.id) },
]);

const form = useForm({
    leave_type: '',
    start_date: '',
    end_date: '',
    deduct_from_balance: false,
    is_paid: true,
    notes: '',
    attachment: null as File | null,
});

const selectedLeaveType = computed(() =>
    props.leaveTypes.find((item) => item.key === form.leave_type) ?? null,
);

const formatLocalizedNumber = (value: number) =>
    new Intl.NumberFormat(locale.value === 'ar' ? 'ar-SA' : 'en-US').format(value);

function onAttachmentChange(file: File | null) {
    form.attachment = file;
}

const submit = () => {
    const leaveType = selectedLeaveType.value;
    if (leaveType && leaveType.min_notice_days > 0 && form.start_date) {
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        const startDate = new Date(form.start_date);
        startDate.setHours(0, 0, 0, 0);

        const isPastDate = startDate.getTime() < today.getTime();
        if (!(leaveType.allow_past_dates && isPastDate)) {
            const diffInDays = Math.floor((startDate.getTime() - today.getTime()) / 86400000);
            if (diffInDays < leaveType.min_notice_days) {
                form.setError('start_date', t('leaves.min_notice_days_not_met_frontend', {
                    leave_type: leaveType.label,
                    days: formatLocalizedNumber(leaveType.min_notice_days),
                }));
                return;
            }
        }
    }

    form.clearErrors('start_date');
    form.post(route('employees.leaves.store', employee.id), {
        forceFormData: true,
    });
};
</script>

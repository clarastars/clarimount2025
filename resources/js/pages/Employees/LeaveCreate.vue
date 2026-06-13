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

const { t } = useI18n();

const props = defineProps<{
    employee: { id: number; full_name: string };
    leaveTypes: string[];
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

function onAttachmentChange(file: File | null) {
    form.attachment = file;
}

const submit = () => {
    form.post(route('employees.leaves.store', employee.id), {
        forceFormData: true,
    });
};
</script>

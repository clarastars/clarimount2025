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
                        <!-- Leave Type -->
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

                        <!-- Start & End Date -->
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

                        <!-- Attachment -->
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

                        <!-- Deduct from balance -->
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

                        <!-- Paid / Unpaid -->
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

                        <!-- Notes -->
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import Icon from '@/components/Icon.vue';
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

function onAttachmentChange(event: Event) {
    const target = event.target as HTMLInputElement;
    form.attachment = target.files?.[0] ?? null;
}

const submit = () => {
    form.post(route('employees.leaves.store', employee.id), {
        forceFormData: true,
    });
};
</script>

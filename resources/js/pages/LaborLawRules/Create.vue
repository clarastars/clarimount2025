<script setup lang="ts">
import { useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { useI18n } from 'vue-i18n'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import InputError from '@/components/InputError.vue'
import type { BreadcrumbItem } from '@/types'
import { computed } from 'vue'

const { t } = useI18n()

const breadcrumbs = computed((): BreadcrumbItem[] => [
    {
        title: t('nav.dashboard'),
        href: '/dashboard',
    },
    {
        title: t('labor_law_rules.title'),
        href: '/labor-law-rules',
    },
    {
        title: t('labor_law_rules.create_rule'),
        href: '/labor-law-rules/create',
    },
])

const form = useForm({
    violation_type: '',
    min_minutes: null as number | null,
    max_minutes: null as number | null,
    repeat_number: 1,
    action_type: 'warning',
    action_value: null as number | null,
    action_value_gross_days: null as number | null,
    action_value_basic_days: null as number | null,
    reason_text: '',
})

const violationTypes = [
    { value: 'late_0_15', label: t('labor_law_rules.violation_late_0_15') },
    { value: 'late_15_30', label: t('labor_law_rules.violation_late_15_30') },
    { value: 'late_30_60', label: t('labor_law_rules.violation_late_30_60') },
    { value: 'late_over_60', label: t('labor_law_rules.violation_late_over_60') },
    { value: 'absent_without_excuse', label: t('labor_law_rules.violation_absent_without_excuse') },
]

const actionTypes = [
    { value: 'warning', label: t('labor_law_rules.action_warning') },
    { value: 'deduction_percentage', label: t('labor_law_rules.action_deduction_percentage') },
    { value: 'deduction_days', label: t('labor_law_rules.action_deduction_days') },
    { value: 'termination', label: t('labor_law_rules.action_termination') },
]

const showActionValue = computed(() => {
    return form.action_type === 'deduction_percentage' || form.action_type === 'deduction_days'
})

const showDeductionDaysFields = computed(() => {
    return form.action_type === 'deduction_days'
})

const showMinutesRange = computed(() => {
    return form.violation_type !== 'absent_without_excuse' && form.violation_type !== 'absent_without_permission'
})

const submit = () => {
    form.post(route('labor-law-rules.store'))
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <Card>
                    <CardHeader>
                        <CardTitle>{{ $t('labor_law_rules.create_rule') }}</CardTitle>
                        <CardDescription>
                            {{ $t('labor_law_rules.create_rule_description') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submit" class="space-y-6">
                            <!-- Violation Type -->
                            <div>
                                <Label for="violation_type">{{ $t('labor_law_rules.violation_type') }} *</Label>
                                <select
                                    id="violation_type"
                                    v-model="form.violation_type"
                                    required
                                    class="mt-1 h-10 w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-700 rounded-md bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="">{{ $t('labor_law_rules.select_violation_type') }}</option>
                                    <option v-for="type in violationTypes" :key="type.value" :value="type.value">
                                        {{ type.label }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.violation_type" />
                            </div>

                            <!-- Minutes Range -->
                            <div v-if="showMinutesRange" class="grid grid-cols-2 gap-4">
                                <div>
                                    <Label for="min_minutes">{{ $t('labor_law_rules.min_minutes') }}</Label>
                                    <Input
                                        id="min_minutes"
                                        v-model.number="form.min_minutes"
                                        type="number"
                                        min="0"
                                        :placeholder="$t('labor_law_rules.min_minutes_placeholder')"
                                    />
                                    <InputError :message="form.errors.min_minutes" />
                                </div>
                                <div>
                                    <Label for="max_minutes">{{ $t('labor_law_rules.max_minutes') }}</Label>
                                    <Input
                                        id="max_minutes"
                                        v-model.number="form.max_minutes"
                                        type="number"
                                        min="0"
                                        :placeholder="$t('labor_law_rules.max_minutes_placeholder')"
                                    />
                                    <InputError :message="form.errors.max_minutes" />
                                </div>
                            </div>

                            <!-- Repeat Number -->
                            <div>
                                <Label for="repeat_number">{{ $t('labor_law_rules.repeat_number') }} *</Label>
                                <Input
                                    id="repeat_number"
                                    v-model.number="form.repeat_number"
                                    type="number"
                                    min="1"
                                    max="4"
                                    required
                                />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ $t('labor_law_rules.repeat_number_hint') }}
                                </p>
                                <InputError :message="form.errors.repeat_number" />
                            </div>

                            <!-- Action Type -->
                            <div>
                                <Label for="action_type">{{ $t('labor_law_rules.action_type') }} *</Label>
                                <select
                                    id="action_type"
                                    v-model="form.action_type"
                                    required
                                    class="mt-1 h-10 w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-700 rounded-md bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                                >
                                    <option v-for="type in actionTypes" :key="type.value" :value="type.value">
                                        {{ type.label }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.action_type" />
                            </div>

                            <!-- Action Value -->
                            <div v-if="showActionValue && !showDeductionDaysFields">
                                <Label for="action_value">{{ $t('labor_law_rules.action_value') }}</Label>
                                <Input
                                    id="action_value"
                                    v-model.number="form.action_value"
                                    type="number"
                                    min="0"
                                    :placeholder="form.action_type === 'deduction_percentage' ? '5' : '1'"
                                />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    <span v-if="form.action_type === 'deduction_percentage'">
                                        {{ $t('labor_law_rules.action_value_percentage_hint') }}
                                    </span>
                                    <span v-else>
                                        {{ $t('labor_law_rules.action_value_days_hint') }}
                                    </span>
                                </p>
                                <InputError :message="form.errors.action_value" />
                            </div>

                            <!-- Deduction Days Fields (Gross and Basic) -->
                            <div v-if="showDeductionDaysFields" class="grid grid-cols-2 gap-4">
                                <div>
                                    <Label for="action_value_gross_days">{{ $t('labor_law_rules.action_value_gross_days') }}</Label>
                                    <Input
                                        id="action_value_gross_days"
                                        v-model.number="form.action_value_gross_days"
                                        type="number"
                                        min="0"
                                        placeholder="1"
                                    />
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $t('labor_law_rules.action_value_gross_days_hint') }}
                                    </p>
                                    <InputError :message="form.errors.action_value_gross_days" />
                                </div>
                                <div>
                                    <Label for="action_value_basic_days">{{ $t('labor_law_rules.action_value_basic_days') }}</Label>
                                    <Input
                                        id="action_value_basic_days"
                                        v-model.number="form.action_value_basic_days"
                                        type="number"
                                        min="0"
                                        placeholder="1"
                                    />
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $t('labor_law_rules.action_value_basic_days_hint') }}
                                    </p>
                                    <InputError :message="form.errors.action_value_basic_days" />
                                </div>
                            </div>

                            <!-- Reason Text -->
                            <div>
                                <Label for="reason_text">{{ $t('labor_law_rules.reason') }} *</Label>
                                <textarea
                                    id="reason_text"
                                    v-model="form.reason_text"
                                    rows="4"
                                    required
                                    :placeholder="$t('labor_law_rules.reason_placeholder')"
                                    class="mt-1 w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-700 rounded-md bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                                <InputError :message="form.errors.reason_text" />
                            </div>

                            <!-- Actions -->
                            <div class="flex justify-end gap-4 pt-4">
                                <Button
                                    type="button"
                                    variant="outline"
                                    @click="router.visit(route('labor-law-rules.index'))"
                                >
                                    {{ $t('common.cancel') }}
                                </Button>
                                <Button
                                    type="submit"
                                    :disabled="form.processing"
                                >
                                    <span v-if="form.processing">{{ $t('common.creating') }}</span>
                                    <span v-else>{{ $t('common.create') }}</span>
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>

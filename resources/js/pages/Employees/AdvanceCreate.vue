<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Wallet } from 'lucide-vue-next';

import AppLayout from '@/layouts/AppLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { BreadcrumbItem } from '@/types';

interface ScheduleRow {
    month_index: number;
    amount: number;
}

interface EmployeeSummary {
    id: number;
    full_name: string;
    company_name?: string | null;
    gross_monthly: number;
}

interface EntitlementSummary {
    has_hire_date: boolean;
    hire_date: string | null;
    months_of_service: number | null;
    can_request: boolean;
    block_reason: string | null;
    annual_max_amount: number;
    used_amount: number;
    remaining_amount: number;
    max_installments: number;
    period_start: string | null;
    period_end: string | null;
    tier?: {
        id: number;
        min_months: number;
        max_months: number | null;
        max_amount: number;
        max_installments: number;
    } | null;
}

interface EntitlementRule {
    id: number;
    min_months: number;
    max_months: number | null;
    max_amount: number;
    max_installments: number;
    sort_order: number;
    is_active: boolean;
}

const props = withDefaults(defineProps<{
    employee: EmployeeSummary;
    entitlement: EntitlementSummary;
    rules?: EntitlementRule[];
    hasPendingRequest: boolean;
}>(), {
    rules: () => [],
});

const { t, locale } = useI18n();

const breadcrumbs = computed((): BreadcrumbItem[] => [
    { title: t('nav.dashboard'), href: '/dashboard' },
    { title: t('employees.title'), href: '/employees' },
    { title: props.employee.full_name, href: route('employees.show', props.employee.id) },
    { title: t('advances.submit_on_behalf'), href: route('employees.advances.create', props.employee.id) },
]);

const form = useForm({
    amount: '' as string | number,
    installments: '' as string | number,
    reason: '',
});

const selectedAmount = computed(() => Number(form.amount) || 0);
const selectedInstallments = computed(() => Number(form.installments) || 0);

const installmentOptions = computed(() => {
    const max = props.entitlement.max_installments || 0;
    return Array.from({ length: max }, (_, index) => index + 1);
});

const canSubmit = computed(() =>
    props.entitlement.can_request
    && props.entitlement.remaining_amount > 0
    && !props.hasPendingRequest
    && props.employee.gross_monthly > 0,
);

const currentTierId = computed(() => props.entitlement.tier?.id ?? null);

const tenureLabel = (rule: EntitlementRule): string => {
    if (rule.max_months === null) {
        return t('settings.advance_entitlement_tenure_open', { min: rule.min_months });
    }

    return t('settings.advance_entitlement_tenure_range', {
        min: rule.min_months,
        max: rule.max_months,
    });
};

const isCurrentRule = (rule: EntitlementRule): boolean =>
    currentTierId.value !== null && rule.id === currentTierId.value;

const blockMessage = computed(() => {
    if (!props.entitlement.has_hire_date) {
        return t('advances.missing_hire_date');
    }

    if (props.hasPendingRequest) {
        return t('advances.pending_request_exists');
    }

    if (props.entitlement.block_reason === 'no_matching_tier') {
        return t('advances.no_matching_tier');
    }

    if (props.entitlement.block_reason === 'no_remaining_entitlement') {
        return t('advances.no_remaining_entitlement');
    }

    if (props.employee.gross_monthly <= 0) {
        return t('advances.no_amounts_available');
    }

    return null;
});

const formatCurrency = (amount: number) => `${Number(amount).toLocaleString(locale.value === 'ar' ? 'ar-SA' : 'en-GB', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
})} SAR`;

const formatDateOnly = (value: string | null | undefined): string => {
    if (!value) {
        return '—';
    }

    try {
        return new Date(`${value}T00:00:00`).toLocaleDateString(locale.value === 'ar' ? 'ar-SA' : 'en-GB', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        });
    } catch {
        return value;
    }
};

const previewPlan = computed((): { months: number; schedule: ScheduleRow[]; monthly: number } | null => {
    const amount = selectedAmount.value;
    const installments = selectedInstallments.value;

    if (amount <= 0 || installments < 1) {
        return null;
    }

    const baseMonthly = Math.round((amount / installments) * 100) / 100;
    const schedule: ScheduleRow[] = [];
    let allocated = 0;

    for (let index = 1; index <= installments; index += 1) {
        const installment = index === installments
            ? Math.round((amount - allocated) * 100) / 100
            : baseMonthly;

        if (installment <= 0) {
            continue;
        }

        if (index < installments) {
            allocated = Math.round((allocated + installment) * 100) / 100;
        }

        schedule.push({ month_index: schedule.length + 1, amount: installment });
    }

    if (schedule.length === 0) {
        return null;
    }

    return {
        months: schedule.length,
        schedule,
        monthly: schedule[0].amount,
    };
});

const submit = () => {
    form.post(route('employees.advances.store', props.employee.id));
};
</script>

<template>
    <Head :title="t('advances.submit_on_behalf_title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-3xl space-y-6 px-4 py-8">
            <div>
                <h2 class="flex items-center gap-2 text-xl font-semibold">
                    <Wallet class="h-5 w-5 text-amber-600" />
                    {{ t('advances.submit_on_behalf_title') }}
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ employee.full_name }}
                    <span v-if="employee.company_name"> — {{ employee.company_name }}</span>
                </p>
                <p class="mt-2 text-sm text-muted-foreground">
                    {{ t('advances.submit_on_behalf_description') }}
                </p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('advances.entitlement_title') }}</CardTitle>
                    <CardDescription>{{ t('advances.entitlement_description') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="!entitlement.has_hire_date" class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        {{ t('advances.missing_hire_date') }}
                    </div>
                    <div v-else class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <p class="text-muted-foreground">{{ t('advances.hire_date') }}</p>
                            <p class="font-medium">{{ formatDateOnly(entitlement.hire_date) }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">{{ t('advances.months_of_service') }}</p>
                            <p class="font-medium">{{ entitlement.months_of_service ?? 0 }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">{{ t('advances.remaining_amount') }}</p>
                            <p class="font-medium">{{ formatCurrency(entitlement.remaining_amount) }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">{{ t('advances.max_installments') }}</p>
                            <p class="font-medium">{{ entitlement.max_installments || '—' }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">{{ t('advances.used_amount') }}</p>
                            <p class="font-medium">{{ formatCurrency(entitlement.used_amount) }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">{{ t('advances.annual_max_amount') }}</p>
                            <p class="font-medium">{{ formatCurrency(entitlement.annual_max_amount) }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card v-if="rules.length > 0">
                <CardHeader>
                    <CardTitle>{{ t('advances.rules_title') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-muted-foreground">
                                    <th class="px-2 py-2 text-start font-medium">{{ t('settings.advance_entitlement_tenure') }}</th>
                                    <th class="px-2 py-2 text-start font-medium">{{ t('settings.advance_entitlement_max_amount') }}</th>
                                    <th class="px-2 py-2 text-start font-medium">{{ t('settings.advance_entitlement_max_installments') }}</th>
                                    <th class="px-2 py-2 text-start font-medium">{{ t('common.status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="rule in rules"
                                    :key="rule.id"
                                    class="border-b last:border-0"
                                    :class="isCurrentRule(rule) ? 'bg-amber-50/80' : ''"
                                >
                                    <td class="px-2 py-3 font-medium">{{ tenureLabel(rule) }}</td>
                                    <td class="px-2 py-3">{{ formatCurrency(rule.max_amount) }}</td>
                                    <td class="px-2 py-3">{{ rule.max_installments }}</td>
                                    <td class="px-2 py-3">
                                        <Badge v-if="isCurrentRule(rule)" variant="default">
                                            {{ t('advances.rules_current_tier') }}
                                        </Badge>
                                        <span v-else class="text-muted-foreground">—</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <p v-if="blockMessage" class="rounded-md border px-4 py-3 text-sm text-muted-foreground">
                {{ blockMessage }}
            </p>

            <Card v-if="canSubmit">
                <CardHeader>
                    <CardTitle>{{ t('advances.request_new') }}</CardTitle>
                    <CardDescription>
                        {{ t('advances.request_description_entitlement', {
                            max: formatCurrency(entitlement.remaining_amount),
                            installments: entitlement.max_installments,
                        }) }}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form class="space-y-4" @submit.prevent="submit">
                        <div class="space-y-2">
                            <Label for="amount">{{ t('advances.amount') }}</Label>
                            <Input
                                id="amount"
                                v-model="form.amount"
                                type="number"
                                min="1"
                                step="0.01"
                                :max="entitlement.remaining_amount"
                                required
                            />
                            <p class="text-xs text-muted-foreground">
                                {{ t('advances.amount_max_hint', { max: formatCurrency(entitlement.remaining_amount) }) }}
                            </p>
                            <p v-if="form.errors.amount" class="text-sm text-red-600">{{ form.errors.amount }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="installments">{{ t('advances.installments') }}</Label>
                            <select
                                id="installments"
                                v-model="form.installments"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                required
                            >
                                <option value="" disabled>{{ t('advances.select_installments') }}</option>
                                <option v-for="option in installmentOptions" :key="`installment-${option}`" :value="option">
                                    {{ option }}
                                </option>
                            </select>
                            <p v-if="form.errors.installments" class="text-sm text-red-600">{{ form.errors.installments }}</p>
                        </div>

                        <div v-if="previewPlan" class="space-y-2 rounded-md border bg-muted/40 px-3 py-3">
                            <p class="text-sm font-medium">{{ t('advances.repayment_plan') }}</p>
                            <p class="text-sm text-muted-foreground">
                                {{ t('advances.repayment_plan_summary', {
                                    amount: formatCurrency(selectedAmount),
                                    months: previewPlan.months,
                                    monthly: formatCurrency(previewPlan.monthly),
                                }) }}
                            </p>
                            <ul class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                <li
                                    v-for="row in previewPlan.schedule"
                                    :key="`preview-${row.month_index}`"
                                    class="rounded-md border bg-background px-3 py-2 text-xs"
                                >
                                    <span class="text-muted-foreground">
                                        {{ t('advances.repayment_month', { index: row.month_index }) }}
                                    </span>
                                    <span class="block font-medium">{{ formatCurrency(row.amount) }}</span>
                                </li>
                            </ul>
                        </div>

                        <div class="space-y-2">
                            <Label for="reason">{{ t('advances.reason') }}</Label>
                            <textarea
                                id="reason"
                                v-model="form.reason"
                                rows="3"
                                required
                                :placeholder="t('advances.reason_placeholder')"
                                class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            />
                            <p v-if="form.errors.reason" class="text-sm text-red-600">{{ form.errors.reason }}</p>
                        </div>

                        <div class="flex justify-end gap-3 pt-2">
                            <Button type="button" variant="outline" as-child>
                                <Link :href="route('employees.show', employee.id)">{{ t('common.cancel') }}</Link>
                            </Button>
                            <Button type="submit" :disabled="form.processing || !previewPlan">
                                {{ form.processing ? t('common.saving') : t('advances.submit_request') }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

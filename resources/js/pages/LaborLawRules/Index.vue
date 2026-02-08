<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { useI18n } from 'vue-i18n'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import Icon from '@/components/Icon.vue'
import type { BreadcrumbItem } from '@/types'

const { t } = useI18n()

interface LaborLawRule {
    id: number
    violation_type: string
    min_minutes: number | null
    max_minutes: number | null
    repeat_number: number
    action_type: string
    action_value: number | null
    reason_text: string
    created_at: string
    updated_at: string
}

interface Props {
    rules: {
        data: LaborLawRule[]
        links: any[]
        meta: any
    }
    violationTypes: string[]
    filters?: {
        search?: string
        violation_type?: string
        action_type?: string
    }
}

const props = defineProps<Props>()

const breadcrumbs = computed((): BreadcrumbItem[] => [
    {
        title: t('nav.dashboard'),
        href: '/dashboard',
    },
    {
        title: t('labor_law_rules.title'),
        href: '/labor-law-rules',
    },
])

const search = ref(props.filters?.search || '')
const selectedViolationType = ref(props.filters?.violation_type || '')
const selectedActionType = ref(props.filters?.action_type || '')

// Debounced search
let searchTimeout: number
const updateFilters = () => {
    router.get(route('labor-law-rules.index'), {
        search: search.value || undefined,
        violation_type: selectedViolationType.value || undefined,
        action_type: selectedActionType.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    })
}

watch(search, () => {
    clearTimeout(searchTimeout)
    searchTimeout = window.setTimeout(updateFilters, 300)
})

watch([selectedViolationType, selectedActionType], updateFilters)

const clearFilters = () => {
    search.value = ''
    selectedViolationType.value = ''
    selectedActionType.value = ''
}

const getViolationTypeLabel = (type: string) => {
    const labels: Record<string, string> = {
        'late_0_15': t('labor_law_rules.violation_late_0_15'),
        'late_15_30': t('labor_law_rules.violation_late_15_30'),
        'late_30_60': t('labor_law_rules.violation_late_30_60'),
        'late_over_60': t('labor_law_rules.violation_late_over_60'),
        'absent_without_excuse': t('labor_law_rules.violation_absent_without_excuse'),
        'absent_without_permission': t('labor_law_rules.violation_absent_without_permission'),
    }
    return labels[type] || type
}

const getActionTypeLabel = (type: string) => {
    const labels: Record<string, string> = {
        'warning': t('labor_law_rules.action_warning'),
        'deduction_percentage': t('labor_law_rules.action_deduction_percentage'),
        'deduction_days': t('labor_law_rules.action_deduction_days'),
        'termination': t('labor_law_rules.action_termination'),
    }
    return labels[type] || type
}

const getActionTypeVariant = (type: string) => {
    const variants: Record<string, string> = {
        'warning': 'secondary',
        'deduction_percentage': 'warning',
        'deduction_days': 'destructive',
        'termination': 'destructive',
    }
    return variants[type] || 'secondary'
}

const getMinutesRange = (rule: LaborLawRule) => {
    if (rule.min_minutes === null && rule.max_minutes === null) {
        return '-'
    }
    if (rule.min_minutes === null) {
        return `≤ ${rule.max_minutes}`
    }
    if (rule.max_minutes === null) {
        return `≥ ${rule.min_minutes}`
    }
    return `${rule.min_minutes} - ${rule.max_minutes}`
}

const deleteRule = (ruleId: number) => {
    if (confirm(t('labor_law_rules.confirm_delete'))) {
        router.delete(route('labor-law-rules.destroy', ruleId), {
            preserveScroll: true,
        })
    }
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                            {{ $t('labor_law_rules.title') }}
                        </h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            {{ $t('labor_law_rules.description') }}
                        </p>
                    </div>
                    <Button @click="router.visit(route('labor-law-rules.create'))" class="gap-2">
                        <Icon name="Plus" class="w-4 h-4" />
                        {{ $t('labor_law_rules.create_rule') }}
                    </Button>
                </div>

                <!-- Filters -->
                <Card class="mb-6">
                    <CardContent class="p-4">
                        <div class="flex flex-col sm:flex-row gap-4">
                            <!-- Search -->
                            <div class="relative flex-1">
                                <div class="absolute inset-y-0 left-0 rtl:left-auto rtl:right-0 pl-3 rtl:pl-0 rtl:pr-3 flex items-center pointer-events-none">
                                    <Icon name="Search" class="h-4 w-4 text-gray-400" />
                                </div>
                                <Input
                                    v-model="search"
                                    :placeholder="$t('labor_law_rules.search_placeholder')"
                                    class="w-full pl-10 rtl:pl-3 rtl:pr-10"
                                />
                            </div>
                            <!-- Violation Type Filter -->
                            <div class="w-full sm:w-48">
                                <select
                                    v-model="selectedViolationType"
                                    class="h-10 w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-700 rounded-md bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="">{{ $t('labor_law_rules.all_violation_types') }}</option>
                                    <option v-for="type in violationTypes" :key="type" :value="type">
                                        {{ getViolationTypeLabel(type) }}
                                    </option>
                                </select>
                            </div>
                            <!-- Action Type Filter -->
                            <div class="w-full sm:w-48">
                                <select
                                    v-model="selectedActionType"
                                    class="h-10 w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-700 rounded-md bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="">{{ $t('labor_law_rules.all_action_types') }}</option>
                                    <option value="warning">{{ $t('labor_law_rules.action_warning') }}</option>
                                    <option value="deduction_percentage">{{ $t('labor_law_rules.action_deduction_percentage') }}</option>
                                    <option value="deduction_days">{{ $t('labor_law_rules.action_deduction_days') }}</option>
                                    <option value="termination">{{ $t('labor_law_rules.action_termination') }}</option>
                                </select>
                            </div>
                            <!-- Clear Filters -->
                            <Button
                                v-if="search || selectedViolationType || selectedActionType"
                                @click="clearFilters"
                                variant="outline"
                            >
                                {{ $t('common.clear') }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <!-- Rules Table -->
                <Card v-if="rules && rules.data && rules.data.length > 0">
                    <CardContent class="p-0">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider border-r border-gray-200 dark:border-gray-700">
                                            {{ $t('labor_law_rules.violation_type') }}
                                        </th>
                                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider border-r border-gray-200 dark:border-gray-700">
                                            {{ $t('labor_law_rules.minutes_range') }}
                                        </th>
                                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider border-r border-gray-200 dark:border-gray-700">
                                            {{ $t('labor_law_rules.repeat_number') }}
                                        </th>
                                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider border-r border-gray-200 dark:border-gray-700">
                                            {{ $t('labor_law_rules.action_type') }}
                                        </th>
                                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider border-r border-gray-200 dark:border-gray-700">
                                            {{ $t('labor_law_rules.action_value') }}
                                        </th>
                                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider border-r border-gray-200 dark:border-gray-700">
                                            {{ $t('labor_law_rules.reason') }}
                                        </th>
                                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                            {{ $t('common.actions') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-for="rule in (rules.data || [])" :key="rule.id" class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                        <td class="px-6 py-4 text-center border-r border-gray-200 dark:border-gray-700">
                                            <Badge variant="outline">
                                                {{ getViolationTypeLabel(rule.violation_type) }}
                                            </Badge>
                                        </td>
                                        <td class="px-6 py-4 text-center border-r border-gray-200 dark:border-gray-700">
                                            <span class="text-sm text-gray-900 dark:text-white">
                                                {{ getMinutesRange(rule) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-center border-r border-gray-200 dark:border-gray-700">
                                            <Badge variant="info">
                                                {{ rule.repeat_number }}
                                            </Badge>
                                        </td>
                                        <td class="px-6 py-4 text-center border-r border-gray-200 dark:border-gray-700">
                                            <Badge :variant="getActionTypeVariant(rule.action_type)">
                                                {{ getActionTypeLabel(rule.action_type) }}
                                            </Badge>
                                        </td>
                                        <td class="px-6 py-4 text-center border-r border-gray-200 dark:border-gray-700">
                                            <span class="text-sm text-gray-900 dark:text-white">
                                                {{ rule.action_value ?? '-' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 border-r border-gray-200 dark:border-gray-700">
                                            <p class="text-xs text-gray-600 dark:text-gray-400 max-w-xs truncate" :title="rule.reason_text">
                                                {{ rule.reason_text }}
                                            </p>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <div class="flex justify-center gap-2">
                                                <Button
                                                    @click="router.visit(route('labor-law-rules.edit', rule.id))"
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    {{ $t('common.edit') }}
                                                </Button>
                                                <Button
                                                    @click="deleteRule(rule.id)"
                                                    variant="destructive"
                                                    size="sm"
                                                >
                                                    {{ $t('common.delete') }}
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div v-if="rules.links && rules.links.length > 3" class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                            <div class="flex justify-between items-center">
                                <div class="text-sm text-gray-700 dark:text-gray-300">
                                    {{ $t('common.showing') }}
                                    <span class="font-medium">{{ rules.meta?.from || 0 }}</span>
                                    {{ $t('common.to') }}
                                    <span class="font-medium">{{ rules.meta?.to || 0 }}</span>
                                    {{ $t('common.of') }}
                                    <span class="font-medium">{{ rules.meta?.total || 0 }}</span>
                                    {{ $t('common.results') }}
                                </div>
                                <div class="flex gap-1">
                                    <Button
                                        v-for="link in (rules.links || [])"
                                        :key="link.label"
                                        @click="link.url && router.visit(link.url)"
                                        :variant="link.active ? 'default' : 'outline'"
                                        size="sm"
                                        :disabled="!link.url"
                                        v-html="link.label"
                                    />
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Empty State -->
                <Card v-else-if="rules && rules.data && rules.data.length === 0">
                    <CardContent class="p-12 text-center">
                        <Icon name="FileText" class="w-12 h-12 text-gray-400 mx-auto mb-4" />
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
                            {{ $t('labor_law_rules.no_rules_found') }}
                        </h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-6">
                            {{ $t('labor_law_rules.no_rules_description') }}
                        </p>
                        <Button @click="router.visit(route('labor-law-rules.create'))">
                            {{ $t('labor_law_rules.create_rule') }}
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>

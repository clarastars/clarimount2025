<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/layouts/AppLayout.vue'

const { t, locale } = useI18n()

interface EmployeeSummary {
    id: number
    full_name: string
    employee_id?: string | null
    company_id?: number
    job_title?: string | null
    employment_status?: string | null
    email?: string | null
}

interface RoleAssignee {
    user_id?: number | null
    employee_id?: number | null
    full_name: string
    team_name: string
    job_title?: string | null
    employee_code?: string | null
    source: string
}

interface Department {
    id: string
    name: string
    code: string
    description?: string | null
    company_id: number
    created_at: string
    updated_at: string
    company: {
        id: number
        name_en: string
        name_ar?: string | null
        company_email?: string | null
    } | null
    role_assignees?: RoleAssignee[]
    employees: EmployeeSummary[]
    stats: {
        employees_count: number
        active_employees_count: number
        role_assignees_count?: number
        status_counts: Record<string, number>
    }
}

interface Props {
    department: Department
}

const props = defineProps<Props>()

const companyName = computed(() => {
    const company = props.department.company
    if (!company) {
        return '-'
    }

    if (locale.value === 'ar' && company.name_ar) {
        return company.name_ar
    }

    return company.name_en || company.name_ar || '-'
})

const formatDate = (value?: string | null) => {
    if (!value) {
        return '-'
    }

    return new Date(value).toLocaleDateString(locale.value === 'ar' ? 'ar-SA' : 'en-GB')
}

const statusLabel = (status?: string | null) => {
    if (!status) {
        return t('departments.status_unknown')
    }

    const key = `employees.status_${status}`
    const translated = t(key)

    return translated === key ? status : translated
}

const deleteDepartment = () => {
    if (confirm(t('departments.confirm_delete'))) {
        router.delete(`/departments/${props.department.id}`)
    }
}
</script>

<template>
    <AppLayout>
        <Head :title="department.name" />

        <div class="max-w-6xl mx-auto p-6 space-y-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="mb-2 flex items-center gap-2 text-sm text-gray-500">
                        <Link href="/departments" class="hover:text-blue-600">{{ t('departments.title') }}</Link>
                        <span>/</span>
                        <span>{{ department.name }}</span>
                    </div>
                    <h1 class="text-2xl font-bold">{{ department.name }}</h1>
                    <p class="mt-1 text-gray-600">
                        {{ t('departments.department_code') }}: <span class="font-mono">{{ department.code }}</span>
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link
                        :href="`/departments/${department.id}/edit`"
                        class="rounded-md border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-50"
                    >
                        {{ t('departments.edit') }}
                    </Link>
                    <button
                        type="button"
                        class="rounded-md bg-red-600 px-4 py-2 text-white hover:bg-red-700"
                        @click="deleteDepartment"
                    >
                        {{ t('departments.delete') }}
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="rounded-lg border bg-white p-5">
                    <div class="text-sm text-gray-500">{{ t('departments.employees_count') }}</div>
                    <div class="mt-2 text-3xl font-semibold">{{ department.stats.employees_count }}</div>
                </div>
                <div class="rounded-lg border bg-white p-5">
                    <div class="text-sm text-gray-500">{{ t('departments.active_employees') }}</div>
                    <div class="mt-2 text-3xl font-semibold">{{ department.stats.active_employees_count }}</div>
                </div>
                <div class="rounded-lg border bg-white p-5">
                    <div class="text-sm text-gray-500">{{ t('departments.role_assignees') }}</div>
                    <div class="mt-2 text-3xl font-semibold">
                        {{ department.stats.role_assignees_count ?? (department.role_assignees || []).length }}
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div class="rounded-lg border bg-white p-6">
                    <h2 class="mb-4 text-lg font-medium">{{ t('departments.department_information') }}</h2>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <div class="text-sm font-medium text-gray-500">{{ t('departments.department_name') }}</div>
                            <p class="mt-1 text-sm">{{ department.name }}</p>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-500">{{ t('departments.department_code') }}</div>
                            <p class="mt-1 font-mono text-sm">{{ department.code }}</p>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-500">{{ t('departments.company') }}</div>
                            <p class="mt-1 text-sm">{{ companyName }}</p>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-500">{{ t('departments.created') }}</div>
                            <p class="mt-1 text-sm">{{ formatDate(department.created_at) }}</p>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-500">{{ t('departments.updated') }}</div>
                            <p class="mt-1 text-sm">{{ formatDate(department.updated_at) }}</p>
                        </div>
                    </div>
                    <div v-if="department.description" class="mt-6">
                        <div class="text-sm font-medium text-gray-500">{{ t('departments.description') }}</div>
                        <p class="mt-1 whitespace-pre-wrap text-sm">{{ department.description }}</p>
                    </div>
                </div>

                <div class="rounded-lg border bg-white p-6">
                    <h2 class="mb-4 text-lg font-medium">{{ t('departments.role_assignees') }}</h2>
                    <p class="mb-4 text-sm text-gray-500">{{ t('departments.role_assignees_hint') }}</p>

                    <div v-if="(department.role_assignees || []).length" class="space-y-3">
                        <div
                            v-for="(person, idx) in department.role_assignees"
                            :key="`${person.user_id || person.employee_id || idx}-${person.team_name}`"
                            class="rounded-md border p-3"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <div class="text-sm font-medium">
                                        <Link
                                            v-if="person.employee_id"
                                            :href="`/employees/${person.employee_id}`"
                                            class="text-blue-600 hover:text-blue-800"
                                        >
                                            {{ person.full_name }}
                                        </Link>
                                        <span v-else>{{ person.full_name }}</span>
                                    </div>
                                    <div v-if="person.job_title" class="mt-0.5 text-xs text-gray-500">{{ person.job_title }}</div>
                                </div>
                                <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-800">
                                    {{ person.team_name }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-500">{{ t('departments.no_role_assignees') }}</p>
                </div>
            </div>

            <div class="overflow-hidden rounded-lg border bg-white">
                <div class="flex items-center justify-between border-b px-6 py-4">
                    <h2 class="text-lg font-medium">{{ t('departments.assigned_employees') }}</h2>
                    <span class="text-sm text-gray-500">{{ department.stats.employees_count }}</span>
                </div>

                <div v-if="department.employees.length === 0" class="px-6 py-10 text-center text-sm text-gray-500">
                    {{ t('departments.no_assigned_employees') }}
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">
                                    {{ t('departments.employee_name') }}
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">
                                    {{ t('employees.employee_id') }}
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">
                                    {{ t('employees.job_title') }}
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">
                                    {{ t('employees.employment_status') }}
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">
                                    {{ t('departments.actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr
                                v-for="employee in department.employees"
                                :key="employee.id"
                                class="hover:bg-gray-50"
                            >
                                <td class="px-6 py-4 text-center text-sm font-medium text-gray-900">
                                    {{ employee.full_name }}
                                </td>
                                <td class="px-6 py-4 text-center font-mono text-sm text-gray-500">
                                    {{ employee.employee_id || '-' }}
                                </td>
                                <td class="px-6 py-4 text-center text-sm text-gray-700">
                                    {{ employee.job_title || '-' }}
                                </td>
                                <td class="px-6 py-4 text-center text-sm text-gray-700">
                                    {{ statusLabel(employee.employment_status) }}
                                </td>
                                <td class="px-6 py-4 text-center text-sm">
                                    <Link
                                        :href="`/employees/${employee.id}`"
                                        class="text-blue-600 hover:text-blue-800"
                                    >
                                        {{ t('departments.view') }}
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

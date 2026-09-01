<script setup lang="ts">
import EmployeeShowSection from '@/components/employees/EmployeeShowSection.vue'
import { useI18n } from 'vue-i18n'

export interface EmployeeAuditChange {
    field: string
    label: string
    old: string | null
    new: string | null
}

export interface EmployeeAuditLogEntry {
    id: number
    event: string
    event_label: string
    performed_at: string | null
    user: {
        id?: number | null
        name?: string | null
        email?: string | null
    }
    changes: EmployeeAuditChange[]
}

defineProps<{
    auditLogs: EmployeeAuditLogEntry[]
}>()

const { t, locale } = useI18n()

function performerLabel(entry: EmployeeAuditLogEntry): string {
    return entry.user?.name || entry.user?.email || t('employees.audit_by_system')
}

function formatDateTime(value: string | null): string {
    if (!value) {
        return '-'
    }

    const date = new Date(value)

    if (Number.isNaN(date.getTime())) {
        return '-'
    }

    return date.toLocaleString(locale.value === 'ar' ? 'ar-SA' : 'en-GB', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    })
}

function displayValue(value: string | null): string {
    if (value === null || value === undefined || value === '') {
        return '—'
    }

    return value
}
</script>

<template>
    <EmployeeShowSection :title="t('employees.audit_log_title')" icon="History" icon-class="text-violet-600">
        <div v-if="auditLogs.length === 0" class="text-sm text-muted-foreground">
            {{ t('employees.audit_log_empty') }}
        </div>

        <div v-else class="space-y-4">
            <div
                v-for="entry in auditLogs"
                :key="entry.id"
                class="rounded-xl border border-border/60 bg-muted/10 p-4"
            >
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-foreground">
                            {{ entry.event_label }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ t('employees.audit_performed_by') }}: {{ performerLabel(entry) }}
                        </p>
                    </div>
                    <p class="text-xs font-medium text-muted-foreground" dir="ltr">
                        {{ formatDateTime(entry.performed_at) }}
                    </p>
                </div>

                <div v-if="entry.changes.length" class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-border/60 text-center text-xs text-muted-foreground">
                                <th class="px-2 py-2 font-medium">{{ t('employees.audit_field') }}</th>
                                <th class="px-2 py-2 font-medium">{{ t('employees.audit_old_value') }}</th>
                                <th class="px-2 py-2 font-medium">{{ t('employees.audit_new_value') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="change in entry.changes"
                                :key="`${entry.id}-${change.field}`"
                                class="border-b border-border/40 last:border-0 text-center"
                            >
                                <td class="px-2 py-2 align-middle font-medium">{{ change.label }}</td>
                                <td class="px-2 py-2 align-middle text-muted-foreground">{{ displayValue(change.old) }}</td>
                                <td class="px-2 py-2 align-middle text-foreground">{{ displayValue(change.new) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="mt-3 text-xs text-muted-foreground">
                    {{ t('employees.audit_no_field_changes') }}
                </p>
            </div>
        </div>
    </EmployeeShowSection>
</template>

<script setup lang="ts">
import { Label } from '@/components/ui/label'
import { reactive } from 'vue'
import { useI18n } from 'vue-i18n'

export interface TeamRoleAssignment {
    team_id: number
    role_name: string
    company_ids: number[]
    company_departments: Record<number, string[]>
}

const props = defineProps<{
    availableTeams: Array<{ id: number; name: string }>
    assignableTeamRoles?: Array<{ name: string; label: string }>
    roleCompanies?: Array<{ id: number; name: string }>
    roleDepartments?: Array<{ id: string; name: string; company_id: number }>
    errors?: Record<string, string>
}>()

const teamRoleAssignments = defineModel<TeamRoleAssignment[]>('teamRoleAssignments', { required: true })

const { t } = useI18n()
const expandedCompanies = reactive<Record<string, boolean>>({})

function isChecked(event: Event): boolean {
    return (event.target as HTMLInputElement | null)?.checked === true
}

function isTeamSelected(teamId: number): boolean {
    return teamRoleAssignments.value.some((row: TeamRoleAssignment) => row.team_id === teamId)
}

function assignmentFor(teamId: number): TeamRoleAssignment | undefined {
    return teamRoleAssignments.value.find((row: TeamRoleAssignment) => row.team_id === teamId)
}

function toggleTeam(teamId: number, checked: boolean): void {
    if (checked) {
        if (!isTeamSelected(teamId)) {
            teamRoleAssignments.value = [
                ...teamRoleAssignments.value,
                {
                    team_id: teamId,
                    role_name: props.assignableTeamRoles?.[0]?.name ?? 'team-member',
                    company_ids: [],
                    company_departments: {},
                },
            ]
        }
        return
    }

    teamRoleAssignments.value = teamRoleAssignments.value.filter((row: TeamRoleAssignment) => row.team_id !== teamId)
}

function setTeamRole(teamId: number, roleName: string): void {
    teamRoleAssignments.value = teamRoleAssignments.value.map((row: TeamRoleAssignment) => {
        if (row.team_id !== teamId) {
            return row
        }

        return { ...row, role_name: roleName }
    })
}

function isCompanySelected(teamId: number, companyId: number): boolean {
    const assignment = assignmentFor(teamId)
    return Boolean(assignment?.company_ids?.includes(companyId))
}

function toggleCompany(teamId: number, companyId: number, checked: boolean): void {
    teamRoleAssignments.value = teamRoleAssignments.value.map((row: TeamRoleAssignment) => {
        if (row.team_id !== teamId) {
            return row
        }

        const current = row.company_ids ?? []
        const company_departments = { ...(row.company_departments ?? {}) }
        const company_ids = checked
            ? Array.from(new Set([...current, companyId]))
            : current.filter((id: number) => id !== companyId)

        if (!checked) {
            delete company_departments[companyId]
        }

        return { ...row, company_ids, company_departments }
    })
}

function departmentsForCompany(companyId: number): Array<{ id: string; name: string; company_id: number }> {
    return (props.roleDepartments || []).filter((department: { id: string; name: string; company_id: number }) => department.company_id === companyId)
}

function selectedDepartmentIds(teamId: number, companyId: number): string[] {
    const assignment = assignmentFor(teamId)
    return assignment?.company_departments?.[companyId] ?? []
}

function isDepartmentSelected(teamId: number, companyId: number, departmentId: string): boolean {
    return selectedDepartmentIds(teamId, companyId).includes(departmentId)
}

function toggleDepartment(teamId: number, companyId: number, departmentId: string, checked: boolean): void {
    teamRoleAssignments.value = teamRoleAssignments.value.map((row: TeamRoleAssignment) => {
        if (row.team_id !== teamId) {
            return row
        }

        const company_departments = { ...(row.company_departments ?? {}) }
        const current = company_departments[companyId] ?? []
        const next = checked
            ? Array.from(new Set([...current, departmentId]))
            : current.filter((id: string) => id !== departmentId)

        if (next.length === 0) {
            delete company_departments[companyId]
        } else {
            company_departments[companyId] = next
        }

        return { ...row, company_departments }
    })
}

function companyScopeKey(teamId: number, companyId: number): string {
    return `${teamId}-${companyId}`
}

function isDepartmentPickerOpen(teamId: number, companyId: number): boolean {
    return expandedCompanies[companyScopeKey(teamId, companyId)] === true
}

function toggleDepartmentPicker(teamId: number, companyId: number): void {
    const key = companyScopeKey(teamId, companyId)
    expandedCompanies[key] = !expandedCompanies[key]
}

function companyErrorFor(teamId: number): string | undefined {
    const index = teamRoleAssignments.value.findIndex((row: TeamRoleAssignment) => row.team_id === teamId)
    if (index < 0 || !props.errors) {
        return undefined
    }

    return props.errors[`team_role_assignments.${index}.company_ids`]
}
</script>

<template>
    <div class="space-y-3">
        <div>
            <Label class="mb-2">{{ t('settings.assign_employee_teams') }}</Label>
            <p class="text-xs text-muted-foreground mb-2">
                {{ t('settings.assign_employee_teams_hint') }}
            </p>
            <div
                v-if="availableTeams.length === 0"
                class="rounded-md border border-dashed p-3 text-sm text-muted-foreground"
            >
                {{ t('settings.no_teams_available') }}
            </div>
            <div v-else class="space-y-2 rounded-md border p-3 max-h-[28rem] overflow-auto">
                <div
                    v-for="team in availableTeams"
                    :key="team.id"
                    class="rounded-md border border-transparent hover:bg-muted/30"
                >
                    <label class="flex items-center gap-2 text-sm px-2 py-1.5 cursor-pointer">
                        <input
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300"
                            :checked="isTeamSelected(team.id)"
                            @change="toggleTeam(team.id, isChecked($event))"
                        >
                        <span class="font-medium">{{ team.name }}</span>
                    </label>

                    <div
                        v-if="isTeamSelected(team.id)"
                        class="ms-6 mb-2 me-2 rounded-md border bg-muted/20 p-2 space-y-2"
                    >
                        <div v-if="(assignableTeamRoles || []).length">
                            <p class="text-xs font-medium text-foreground">
                                {{ t('settings.team_role_label') }}
                            </p>
                            <div class="mt-1 flex flex-wrap gap-3">
                                <label
                                    v-for="role in (assignableTeamRoles || [])"
                                    :key="`${team.id}-${role.name}`"
                                    class="flex items-center gap-2 text-xs rounded px-1 py-1 hover:bg-muted/50 cursor-pointer"
                                >
                                    <input
                                        type="radio"
                                        class="h-3.5 w-3.5 border-gray-300"
                                        :name="`team-role-${team.id}`"
                                        :value="role.name"
                                        :checked="assignmentFor(team.id)?.role_name === role.name"
                                        @change="setTeamRole(team.id, role.name)"
                                    >
                                    <span>{{ role.label }}</span>
                                </label>
                            </div>
                        </div>

                        <div>
                            <p class="text-xs font-medium text-foreground">
                                {{ t('companies.title') }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ t('settings.role_companies_per_team_hint') }}
                            </p>
                        </div>

                        <div
                            v-if="!(roleCompanies || []).length"
                            class="text-xs text-muted-foreground"
                        >
                            {{ t('settings.no_companies_available') }}
                        </div>
                        <div
                            v-else
                            class="grid grid-cols-1 md:grid-cols-2 gap-1.5 max-h-40 overflow-auto"
                        >
                            <div
                                v-for="company in (roleCompanies || [])"
                                :key="`${team.id}-${company.id}`"
                                class="rounded border px-2 py-2 space-y-2"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <label class="flex items-center gap-2 text-xs rounded hover:bg-muted/50 cursor-pointer min-w-0">
                                        <input
                                            type="checkbox"
                                            class="h-3.5 w-3.5 rounded border-gray-300"
                                            :checked="isCompanySelected(team.id, company.id)"
                                            @change="toggleCompany(team.id, company.id, isChecked($event))"
                                        >
                                        <span class="truncate">{{ company.name }}</span>
                                    </label>

                                    <button
                                        v-if="isCompanySelected(team.id, company.id)"
                                        type="button"
                                        class="text-[11px] text-primary hover:underline shrink-0"
                                        @click="toggleDepartmentPicker(team.id, company.id)"
                                    >
                                        {{ isDepartmentPickerOpen(team.id, company.id) ? t('settings.hide_departments') : t('settings.select_departments') }}
                                    </button>
                                </div>

                                <div
                                    v-if="isCompanySelected(team.id, company.id) && isDepartmentPickerOpen(team.id, company.id)"
                                    class="rounded-md bg-background border p-2 space-y-2"
                                >
                                    <div>
                                        <p class="text-[11px] font-medium text-foreground">
                                            {{ t('employees.department') }}
                                        </p>
                                        <p class="text-[11px] text-muted-foreground">
                                            {{ t('settings.role_departments_per_company_hint') }}
                                        </p>
                                    </div>

                                    <div
                                        v-if="departmentsForCompany(company.id).length"
                                        class="grid grid-cols-1 gap-1 max-h-28 overflow-auto"
                                    >
                                        <label
                                            v-for="department in departmentsForCompany(company.id)"
                                            :key="`${team.id}-${company.id}-${department.id}`"
                                            class="flex items-center gap-2 text-[11px] rounded px-1 py-1 hover:bg-muted/50 cursor-pointer"
                                        >
                                            <input
                                                type="checkbox"
                                                class="h-3.5 w-3.5 rounded border-gray-300"
                                                :checked="isDepartmentSelected(team.id, company.id, department.id)"
                                                @change="toggleDepartment(team.id, company.id, department.id, isChecked($event))"
                                            >
                                            <span>{{ department.name }}</span>
                                        </label>
                                    </div>
                                    <p v-else class="text-[11px] text-muted-foreground">
                                        {{ t('employees.no_departments_found') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div v-if="companyErrorFor(team.id)" class="text-sm text-red-500">
                            {{ companyErrorFor(team.id) }}
                        </div>
                    </div>
                </div>
            </div>
            <div v-if="errors?.team_role_assignments" class="mt-1 text-sm text-red-500">
                {{ errors.team_role_assignments }}
            </div>
        </div>
    </div>
</template>

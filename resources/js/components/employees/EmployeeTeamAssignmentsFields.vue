<script setup lang="ts">
import { Label } from '@/components/ui/label'
import { useI18n } from 'vue-i18n'

export interface TeamRoleAssignment {
    team_id: number
    role_name: string
    company_ids: number[]
}

const props = defineProps<{
    availableTeams: Array<{ id: number; name: string }>
    roleCompanies?: Array<{ id: number; name: string }>
    errors?: Record<string, string>
}>()

const teamRoleAssignments = defineModel<TeamRoleAssignment[]>('teamRoleAssignments', { required: true })

const { t } = useI18n()

function isTeamSelected(teamId: number): boolean {
    return teamRoleAssignments.value.some((row) => row.team_id === teamId)
}

function assignmentFor(teamId: number): TeamRoleAssignment | undefined {
    return teamRoleAssignments.value.find((row) => row.team_id === teamId)
}

function toggleTeam(teamId: number, checked: boolean): void {
    if (checked) {
        if (!isTeamSelected(teamId)) {
            teamRoleAssignments.value = [
                ...teamRoleAssignments.value,
                { team_id: teamId, role_name: 'team-member', company_ids: [] },
            ]
        }
        return
    }

    teamRoleAssignments.value = teamRoleAssignments.value.filter((row) => row.team_id !== teamId)
}

function isCompanySelected(teamId: number, companyId: number): boolean {
    const assignment = assignmentFor(teamId)
    return Boolean(assignment?.company_ids?.includes(companyId))
}

function toggleCompany(teamId: number, companyId: number, checked: boolean): void {
    teamRoleAssignments.value = teamRoleAssignments.value.map((row) => {
        if (row.team_id !== teamId) {
            return row
        }

        const current = row.company_ids ?? []
        const company_ids = checked
            ? Array.from(new Set([...current, companyId]))
            : current.filter((id) => id !== companyId)

        return { ...row, company_ids }
    })
}

function companyErrorFor(teamId: number): string | undefined {
    const index = teamRoleAssignments.value.findIndex((row) => row.team_id === teamId)
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
                            @change="toggleTeam(team.id, ($event.target as HTMLInputElement).checked)"
                        >
                        <span class="font-medium">{{ team.name }}</span>
                    </label>

                    <div
                        v-if="isTeamSelected(team.id)"
                        class="ms-6 mb-2 me-2 rounded-md border bg-muted/20 p-2 space-y-2"
                    >
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
                            <label
                                v-for="company in (roleCompanies || [])"
                                :key="`${team.id}-${company.id}`"
                                class="flex items-center gap-2 text-xs rounded px-1.5 py-1 hover:bg-muted/50"
                            >
                                <input
                                    type="checkbox"
                                    class="h-3.5 w-3.5 rounded border-gray-300"
                                    :checked="isCompanySelected(team.id, company.id)"
                                    @change="toggleCompany(team.id, company.id, ($event.target as HTMLInputElement).checked)"
                                >
                                <span>{{ company.name }}</span>
                            </label>
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

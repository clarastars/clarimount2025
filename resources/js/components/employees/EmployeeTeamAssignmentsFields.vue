<script setup lang="ts">
import { Label } from '@/components/ui/label'
import { useI18n } from 'vue-i18n'

export interface TeamRoleAssignment {
    team_id: number
    role_name: string
}

defineProps<{
    availableTeams: Array<{ id: number; name: string }>
    errors?: Record<string, string>
}>()

const teamRoleAssignments = defineModel<TeamRoleAssignment[]>('teamRoleAssignments', { required: true })

const { t } = useI18n()

function isTeamSelected(teamId: number): boolean {
    return teamRoleAssignments.value.some((row) => row.team_id === teamId)
}

function toggleTeam(teamId: number, checked: boolean): void {
    if (checked) {
        if (!isTeamSelected(teamId)) {
            teamRoleAssignments.value = [
                ...teamRoleAssignments.value,
                { team_id: teamId, role_name: 'team-member' },
            ]
        }
        return
    }

    teamRoleAssignments.value = teamRoleAssignments.value.filter((row) => row.team_id !== teamId)
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
            <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-2 rounded-md border p-3 max-h-56 overflow-auto">
                <label
                    v-for="team in availableTeams"
                    :key="team.id"
                    class="flex items-center gap-2 text-sm rounded-md px-2 py-1.5 hover:bg-muted/40"
                >
                    <input
                        type="checkbox"
                        class="h-4 w-4 rounded border-gray-300"
                        :checked="isTeamSelected(team.id)"
                        @change="toggleTeam(team.id, ($event.target as HTMLInputElement).checked)"
                    >
                    <span>{{ team.name }}</span>
                </label>
            </div>
            <div v-if="errors?.team_role_assignments" class="mt-1 text-sm text-red-500">
                {{ errors.team_role_assignments }}
            </div>
        </div>
    </div>
</template>

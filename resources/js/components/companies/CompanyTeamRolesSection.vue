<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

interface TeamAssignment {
    team_id: number;
    team_name: string;
    role_name: string;
    role_label: string;
    scope_type: 'full_company' | 'departments';
    department_names: string[];
    is_primary_team: boolean;
}

interface GlobalRole {
    name: string;
    label: string;
}

interface TeamRoleOverviewUser {
    user_id: number;
    user_name: string;
    user_email: string;
    employee_id: number | null;
    employee_name: string | null;
    employee_profile_url: string | null;
    global_roles: GlobalRole[];
    team_assignments: TeamAssignment[];
}

interface Props {
    assignments: TeamRoleOverviewUser[];
}

defineProps<Props>();
const { t } = useI18n();
</script>

<template>
    <Card class="lg:col-span-3">
        <CardHeader>
            <CardTitle>{{ t('companies.team_roles_overview') }}</CardTitle>
            <CardDescription>{{ t('companies.team_roles_overview_description') }}</CardDescription>
        </CardHeader>
        <CardContent>
            <p v-if="assignments.length === 0" class="text-sm text-muted-foreground">
                {{ t('companies.team_roles_no_assignments') }}
            </p>

            <div v-else class="space-y-4">
                <div
                    v-for="assignment in assignments"
                    :key="assignment.user_id"
                    class="rounded-lg border border-border/60 p-4"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 space-y-1">
                            <p class="font-medium text-foreground">{{ assignment.user_name }}</p>
                            <p class="text-sm text-muted-foreground" dir="ltr">{{ assignment.user_email }}</p>
                            <p v-if="assignment.employee_name" class="text-sm text-muted-foreground">
                                {{ assignment.employee_name }}
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <Badge
                                v-for="globalRole in assignment.global_roles"
                                :key="`${assignment.user_id}-global-${globalRole.name}`"
                                variant="outline"
                            >
                                {{ globalRole.label }}
                            </Badge>
                            <Link
                                v-if="assignment.employee_profile_url"
                                :href="assignment.employee_profile_url"
                                class="text-sm text-primary hover:underline"
                            >
                                {{ t('companies.team_roles_view_employee') }}
                            </Link>
                        </div>
                    </div>

                    <div v-if="assignment.team_assignments.length" class="mt-4 overflow-x-auto">
                        <table class="w-full min-w-[640px] text-sm">
                            <thead>
                                <tr class="border-b border-border/60 text-muted-foreground">
                                    <th class="px-2 py-2 text-start font-medium">{{ t('companies.team_roles_team') }}</th>
                                    <th class="px-2 py-2 text-start font-medium">{{ t('companies.team_roles_role') }}</th>
                                    <th class="px-2 py-2 text-start font-medium">{{ t('companies.team_roles_scope') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="teamAssignment in assignment.team_assignments"
                                    :key="`${assignment.user_id}-${teamAssignment.team_id}-${teamAssignment.role_name}`"
                                    class="border-b border-border/40 last:border-0"
                                >
                                    <td class="px-2 py-3 align-top">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span>{{ teamAssignment.team_name }}</span>
                                            <Badge v-if="teamAssignment.is_primary_team" variant="secondary" class="text-[11px]">
                                                {{ t('companies.team_roles_primary_team') }}
                                            </Badge>
                                        </div>
                                    </td>
                                    <td class="px-2 py-3 align-top">
                                        <Badge variant="outline">{{ teamAssignment.role_label }}</Badge>
                                    </td>
                                    <td class="px-2 py-3 align-top">
                                        <p
                                            v-if="teamAssignment.scope_type === 'full_company' && teamAssignment.department_names.length === 0"
                                            class="text-muted-foreground"
                                        >
                                            {{ t('settings.role_scope_full_company') }}
                                        </p>
                                        <div v-else class="flex flex-wrap gap-1.5">
                                            <Badge
                                                v-for="(departmentName, index) in teamAssignment.department_names"
                                                :key="`${assignment.user_id}-${teamAssignment.team_id}-dept-${index}`"
                                                variant="secondary"
                                                class="text-[11px] font-normal"
                                            >
                                                {{ departmentName }}
                                            </Badge>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>

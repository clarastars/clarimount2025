<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type BreadcrumbItem } from '@/types';

interface TeamPermissionItem {
    name: string;
    label: string;
    enabled: boolean;
}

interface TeamItem {
    id: number;
    name: string;
    description?: string | null;
    permissions: TeamPermissionItem[];
}

interface Props {
    teams: TeamItem[];
}

const props = defineProps<Props>();
const { t } = useI18n();

const breadcrumbs = computed((): BreadcrumbItem[] => [
    {
        title: t('settings.permissions_teams'),
        href: '/settings/permissions-teams',
    },
]);

const createTeamForm = useForm({
    name: '',
    description: '',
});

const permissionForm = useForm({
    permissions: [] as string[],
});

const createTeam = () => {
    createTeamForm.post(route('settings.permissions-teams.store-team'), {
        preserveScroll: true,
        onSuccess: () => createTeamForm.reset(),
    });
};

const saveTeamPermissions = (team: TeamItem) => {
    permissionForm.permissions = team.permissions.filter((permission) => permission.enabled).map((permission) => permission.name);

    permissionForm.post(route('settings.permissions-teams.sync-permissions', team.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('settings.permissions_teams')" />

        <SettingsLayout>
            <div class="space-y-8">
                <HeadingSmall
                    :title="t('settings.permissions_teams')"
                    :description="t('settings.permissions_teams_description')"
                />

                <Card>
                    <CardHeader>
                        <CardTitle>{{ t('settings.create_team') }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form class="grid grid-cols-1 md:grid-cols-3 gap-4" @submit.prevent="createTeam">
                            <div>
                                <Label for="team-name">{{ t('teams.team_name') }}</Label>
                                <Input id="team-name" v-model="createTeamForm.name" />
                                <InputError class="mt-1" :message="createTeamForm.errors.name" />
                            </div>
                            <div>
                                <Label for="team-description">{{ t('teams.team_description') }}</Label>
                                <Input id="team-description" v-model="createTeamForm.description" />
                                <InputError class="mt-1" :message="createTeamForm.errors.description" />
                            </div>
                            <div class="flex items-end">
                                <Button type="submit" :disabled="createTeamForm.processing">
                                    {{ t('common.create') }}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card v-for="team in props.teams" :key="team.id">
                    <CardHeader>
                        <CardTitle>{{ team.name }}</CardTitle>
                        <p v-if="team.description" class="text-sm text-muted-foreground">{{ team.description }}</p>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <label
                                v-for="permission in team.permissions"
                                :key="permission.name"
                                class="flex items-center gap-2 rounded-md border p-3 cursor-pointer"
                            >
                                <input
                                    v-model="permission.enabled"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-gray-300"
                                >
                                <span>{{ permission.label }}</span>
                            </label>
                        </div>

                        <Button
                            :disabled="permissionForm.processing"
                            @click="saveTeamPermissions(team)"
                        >
                            {{ t('common.save_changes') }}
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>


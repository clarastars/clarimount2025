<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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

const editingTeamId = ref<number | null>(null);
const editTeamForm = useForm({
    name: '',
    description: '',
});

const startEditTeam = (team: TeamItem) => {
    editingTeamId.value = team.id;
    editTeamForm.name = team.name;
    editTeamForm.description = team.description ?? '';
};

const cancelEditTeam = () => {
    editingTeamId.value = null;
    editTeamForm.reset();
};

const updateTeam = (teamId: number) => {
    editTeamForm.put(route('settings.permissions-teams.update-team', teamId), {
        preserveScroll: true,
        onSuccess: () => {
            editingTeamId.value = null;
            editTeamForm.reset();
            router.reload({ preserveScroll: true });
        },
    });
};

const deleteTeam = (teamId: number) => {
    // Confirm deletion to prevent accidental team removal.
    // (Keep message simple because translations may vary.)
    if (!confirm('هل أنت متأكد من حذف هذا الفريق؟')) {
        return;
    }

    router.delete(route('settings.permissions-teams.delete-team', teamId), {
        preserveScroll: true,
        onSuccess: () => {
            router.reload({ preserveScroll: true });
        },
    });
};

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
                    <CardHeader class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <CardTitle>
                                <template v-if="editingTeamId === team.id">
                                    <Input v-model="editTeamForm.name" />
                                </template>
                                <template v-else>
                                    {{ team.name }}
                                </template>
                            </CardTitle>
                            <p v-if="editingTeamId === team.id" class="mt-2">
                                <Input v-model="editTeamForm.description" />
                            </p>
                            <p v-else-if="team.description" class="text-sm text-muted-foreground">
                                {{ team.description }}
                            </p>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <Button
                                v-if="editingTeamId !== team.id"
                                variant="outline"
                                size="sm"
                                @click="startEditTeam(team)"
                            >
                                {{ t('common.edit') }}
                            </Button>

                            <template v-else>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :disabled="editTeamForm.processing"
                                    @click="updateTeam(team.id)"
                                >
                                    {{ t('common.save') }}
                                </Button>
                                <Button variant="ghost" size="sm" @click="cancelEditTeam">
                                    {{ t('common.cancel') }}
                                </Button>
                            </template>

                            <Button
                                variant="destructive"
                                size="sm"
                                @click="deleteTeam(team.id)"
                            >
                                {{ t('common.delete') }}
                            </Button>
                        </div>
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


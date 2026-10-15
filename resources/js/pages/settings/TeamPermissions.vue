<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ChevronDown, Plus, Shield, Users } from 'lucide-vue-next';

import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type BreadcrumbItem } from '@/types';

interface TeamPermissionItem {
    name: string;
    label: string;
    group: string;
    enabled: boolean;
    id?: number | null;
}

interface TeamItem {
    id: number;
    name: string;
    description?: string | null;
    users_count: number;
    enabled_permissions_count: number;
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

const panelMode = ref<'view' | 'create'>('view');
const selectedTeamId = ref<number | null>(props.teams[0]?.id ?? null);
const teamSearch = ref('');
const collapsedGroups = ref<Record<string, boolean>>({});
const draftPermissions = ref<TeamPermissionItem[]>([]);

const createTeamForm = useForm({
    name: '',
    description: '',
});

const permissionForm = useForm({
    permissions: [] as string[],
});

const editTeamForm = useForm({
    name: '',
    description: '',
});

const selectedTeam = computed(() =>
    props.teams.find((team) => team.id === selectedTeamId.value) ?? null,
);

const filteredTeams = computed(() => {
    const query = teamSearch.value.trim().toLowerCase();
    if (!query) {
        return props.teams;
    }

    return props.teams.filter((team) =>
        team.name.toLowerCase().includes(query)
        || (team.description ?? '').toLowerCase().includes(query),
    );
});

const permissionGroupOrder = [
    'employees',
    'leaves',
    'advances',
    'attendance',
    'salary_runs',
    'company',
    'departments',
    'assets',
    'settings',
    'other',
] as const;

const clonePermissions = (permissions: TeamPermissionItem[]): TeamPermissionItem[] =>
    permissions.map((permission) => ({
        ...permission,
        enabled: Boolean(permission.enabled),
    }));

const syncDraftFromTeam = (team: TeamItem | null) => {
    draftPermissions.value = team ? clonePermissions(team.permissions) : [];
};

const permissionGroups = computed(() => {
    const grouped = new Map<string, TeamPermissionItem[]>();

    for (const permission of draftPermissions.value) {
        const group = permission.group || 'other';
        if (!grouped.has(group)) {
            grouped.set(group, []);
        }
        grouped.get(group)!.push(permission);
    }

    return permissionGroupOrder
        .filter((group) => grouped.has(group))
        .map((group) => ({
            key: group,
            label: t(`settings.permission_group_${group}`),
            permissions: grouped.get(group)!,
        }));
});

const enabledCount = computed(() =>
    draftPermissions.value.filter((permission) => permission.enabled).length,
);

const allPermissionsSelected = computed(() =>
    draftPermissions.value.length > 0
    && draftPermissions.value.every((permission) => permission.enabled),
);

const syncEditFormFromTeam = (team: TeamItem | null) => {
    if (!team) {
        editTeamForm.reset();
        return;
    }

    editTeamForm.name = team.name;
    editTeamForm.description = team.description ?? '';
};

watch(
    selectedTeam,
    (team) => {
        if (panelMode.value === 'view') {
            syncEditFormFromTeam(team);
            syncDraftFromTeam(team);
        }
    },
    { immediate: true },
);

watch(
    () => props.teams,
    (teams) => {
        if (panelMode.value === 'create') {
            return;
        }

        if (teams.length === 0) {
            selectedTeamId.value = null;
            return;
        }

        if (!teams.some((team) => team.id === selectedTeamId.value)) {
            selectedTeamId.value = teams[0].id;
        }
    },
);

const selectTeam = (teamId: number) => {
    panelMode.value = 'view';
    selectedTeamId.value = teamId;
};

const openCreatePanel = () => {
    panelMode.value = 'create';
    selectedTeamId.value = null;
    createTeamForm.reset();
    createTeamForm.clearErrors();
};

const cancelCreate = () => {
    panelMode.value = 'view';
    createTeamForm.reset();
    selectedTeamId.value = props.teams[0]?.id ?? null;
};

const teamInitial = (name: string): string => {
    const trimmed = name.trim();
    return trimmed ? trimmed.charAt(0) : '?';
};

const createTeam = () => {
    createTeamForm.post(route('settings.permissions-teams.store-team'), {
        preserveScroll: true,
        onSuccess: (page) => {
            createTeamForm.reset();
            panelMode.value = 'view';

            const teams = (page.props.teams as TeamItem[] | undefined) ?? props.teams;
            const newest = [...teams].sort((a, b) => b.id - a.id)[0];
            selectedTeamId.value = newest?.id ?? null;
        },
    });
};

const updateTeam = () => {
    if (!selectedTeam.value) {
        return;
    }

    editTeamForm.put(route('settings.permissions-teams.update-team', selectedTeam.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            router.reload({ preserveScroll: true });
        },
    });
};

const deleteTeam = () => {
    if (!selectedTeam.value) {
        return;
    }

    if (!confirm(t('settings.delete_team_confirm'))) {
        return;
    }

    const deletedId = selectedTeam.value.id;

    router.delete(route('settings.permissions-teams.delete-team', deletedId), {
        preserveScroll: true,
        onSuccess: () => {
            const remaining = props.teams.filter((team) => team.id !== deletedId);
            selectedTeamId.value = remaining[0]?.id ?? null;
            router.reload({ preserveScroll: true });
        },
    });
};

const setGroupPermissions = (groupKey: string, enabled: boolean) => {
    draftPermissions.value = draftPermissions.value.map((permission) =>
        permission.group === groupKey
            ? { ...permission, enabled }
            : permission,
    );
};

const setAllPermissions = (enabled: boolean) => {
    draftPermissions.value = draftPermissions.value.map((permission) => ({
        ...permission,
        enabled,
    }));
};

const isGroupFullySelected = (groupKey: string): boolean => {
    const groupPermissions = draftPermissions.value.filter((permission) => permission.group === groupKey);
    return groupPermissions.length > 0 && groupPermissions.every((permission) => permission.enabled);
};

const isGroupCollapsed = (groupKey: string): boolean => collapsedGroups.value[groupKey] === true;

const toggleGroupCollapsed = (groupKey: string) => {
    collapsedGroups.value = {
        ...collapsedGroups.value,
        [groupKey]: !isGroupCollapsed(groupKey),
    };
};

const saveTeamPermissions = () => {
    if (!selectedTeam.value) {
        return;
    }

    permissionForm.permissions = draftPermissions.value
        .filter((permission) => permission.enabled)
        .map((permission) => permission.name);

    permissionForm.post(route('settings.permissions-teams.sync-permissions', selectedTeam.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            router.reload({ preserveScroll: true });
        },
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('settings.permissions_teams')" />

        <SettingsLayout content-width="full">
            <div class="space-y-6">
                <HeadingSmall
                    :title="t('settings.permissions_teams')"
                    :description="t('settings.permissions_teams_description')"
                />

                <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
                    <div class="grid min-h-[36rem] lg:grid-cols-[18rem_minmax(0,1fr)]">
                        <!-- Internal teams sidebar -->
                        <aside class="border-b bg-muted/30 lg:border-b-0 lg:border-e">
                            <div class="flex items-center justify-between gap-3 border-b px-4 py-3">
                                <div>
                                    <h2 class="text-sm font-semibold">
                                        {{ t('settings.teams_sidebar_title') }}
                                    </h2>
                                    <p class="text-xs text-muted-foreground">
                                        {{ t('settings.teams_count', { count: props.teams.length }) }}
                                    </p>
                                </div>
                                <Button size="sm" class="shrink-0 gap-1" @click="openCreatePanel">
                                    <Plus class="size-4" />
                                    {{ t('settings.new_team') }}
                                </Button>
                            </div>

                            <div class="border-b px-3 py-3">
                                <Input
                                    v-model="teamSearch"
                                    :placeholder="t('settings.search_teams')"
                                    class="h-9"
                                />
                            </div>

                            <div class="max-h-[28rem] space-y-2 overflow-y-auto p-3 lg:max-h-[40rem]">
                                <button
                                    v-for="team in filteredTeams"
                                    :key="team.id"
                                    type="button"
                                    class="flex w-full items-center gap-3 rounded-lg border px-3 py-3 text-start transition-colors"
                                    :class="panelMode === 'view' && selectedTeamId === team.id
                                        ? 'border-primary bg-primary text-primary-foreground shadow-sm'
                                        : 'border-transparent bg-background hover:bg-muted/70'"
                                    @click="selectTeam(team.id)"
                                >
                                    <span
                                        class="flex size-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold"
                                        :class="panelMode === 'view' && selectedTeamId === team.id
                                            ? 'bg-primary-foreground/20 text-primary-foreground'
                                            : 'bg-muted text-foreground'"
                                    >
                                        {{ teamInitial(team.name) }}
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-medium">
                                            {{ team.name }}
                                        </span>
                                        <span
                                            class="mt-0.5 block truncate text-xs"
                                            :class="panelMode === 'view' && selectedTeamId === team.id
                                                ? 'text-primary-foreground/80'
                                                : 'text-muted-foreground'"
                                        >
                                            {{ t('settings.team_meta', {
                                                permissions: team.enabled_permissions_count,
                                                users: team.users_count,
                                            }) }}
                                        </span>
                                    </span>
                                </button>

                                <p
                                    v-if="filteredTeams.length === 0"
                                    class="px-2 py-8 text-center text-sm text-muted-foreground"
                                >
                                    {{ t('settings.no_teams_found') }}
                                </p>
                            </div>
                        </aside>

                        <!-- Main detail panel -->
                        <section class="flex min-w-0 flex-col">
                            <template v-if="panelMode === 'create'">
                                <div class="border-b px-5 py-4">
                                    <h3 class="text-lg font-semibold">
                                        {{ t('settings.create_team') }}
                                    </h3>
                                    <p class="mt-1 text-sm text-muted-foreground">
                                        {{ t('settings.create_team_sidebar_hint') }}
                                    </p>
                                </div>

                                <form class="space-y-5 p-5" @submit.prevent="createTeam">
                                    <div class="space-y-2">
                                        <Label for="create-team-name">{{ t('teams.team_name') }}</Label>
                                        <Input id="create-team-name" v-model="createTeamForm.name" />
                                        <InputError :message="createTeamForm.errors.name" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="create-team-description">{{ t('teams.team_description') }}</Label>
                                        <Input id="create-team-description" v-model="createTeamForm.description" />
                                        <InputError :message="createTeamForm.errors.description" />
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <Button type="submit" :disabled="createTeamForm.processing">
                                            {{ t('common.create') }}
                                        </Button>
                                        <Button type="button" variant="ghost" @click="cancelCreate">
                                            {{ t('common.cancel') }}
                                        </Button>
                                    </div>
                                </form>
                            </template>

                            <template v-else-if="selectedTeam">
                                <div class="flex flex-wrap items-start justify-between gap-4 border-b px-5 py-4">
                                    <div class="min-w-0 space-y-2">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="truncate text-xl font-semibold">
                                                {{ selectedTeam.name }}
                                            </h3>
                                            <Badge variant="secondary" class="gap-1">
                                                <Users class="size-3.5" />
                                                {{ t('settings.team_users_count', { count: selectedTeam.users_count }) }}
                                            </Badge>
                                            <Badge variant="outline" class="gap-1">
                                                <Shield class="size-3.5" />
                                                {{ t('settings.team_permissions_count', { count: enabledCount }) }}
                                            </Badge>
                                        </div>
                                        <p v-if="selectedTeam.description" class="text-sm text-muted-foreground">
                                            {{ selectedTeam.description }}
                                        </p>
                                    </div>

                                    <Button variant="destructive" size="sm" @click="deleteTeam">
                                        {{ t('common.delete') }}
                                    </Button>
                                </div>

                                <div class="flex-1 space-y-6 overflow-y-auto p-5">
                                    <div class="grid gap-4 md:grid-cols-2">
                                        <div class="space-y-2">
                                            <Label for="edit-team-name">{{ t('teams.team_name') }}</Label>
                                            <Input id="edit-team-name" v-model="editTeamForm.name" />
                                            <InputError :message="editTeamForm.errors.name" />
                                        </div>
                                        <div class="space-y-2">
                                            <Label for="edit-team-description">{{ t('teams.team_description') }}</Label>
                                            <Input id="edit-team-description" v-model="editTeamForm.description" />
                                            <InputError :message="editTeamForm.errors.description" />
                                        </div>
                                    </div>

                                    <div class="flex justify-end">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            :disabled="editTeamForm.processing"
                                            @click="updateTeam"
                                        >
                                            {{ t('settings.save_team_details') }}
                                        </Button>
                                    </div>

                                    <div class="space-y-4">
                                        <div class="flex flex-wrap items-center justify-between gap-3">
                                            <div>
                                                <h4 class="text-base font-semibold">
                                                    {{ t('settings.permissions_section_title') }}
                                                </h4>
                                                <p class="text-sm text-muted-foreground">
                                                    {{ t('settings.permissions_section_description') }}
                                                </p>
                                            </div>
                                            <button
                                                type="button"
                                                class="text-sm font-medium text-primary hover:underline"
                                                @click="setAllPermissions(!allPermissionsSelected)"
                                            >
                                                {{ allPermissionsSelected
                                                    ? t('settings.deselect_all_permissions')
                                                    : t('settings.select_all_permissions') }}
                                            </button>
                                        </div>

                                        <div
                                            v-for="group in permissionGroups"
                                            :key="group.key"
                                            class="overflow-hidden rounded-lg border"
                                        >
                                            <div
                                                class="flex items-center justify-between gap-3 bg-muted/40 px-4 py-3"
                                                :class="!isGroupCollapsed(group.key) ? 'border-b' : ''"
                                            >
                                                <button
                                                    type="button"
                                                    class="flex min-w-0 flex-1 items-center gap-3 text-start"
                                                    :aria-expanded="!isGroupCollapsed(group.key)"
                                                    @click="toggleGroupCollapsed(group.key)"
                                                >
                                                    <ChevronDown
                                                        class="size-4 shrink-0 text-muted-foreground transition-transform"
                                                        :class="isGroupCollapsed(group.key) ? '-rotate-90' : ''"
                                                    />
                                                    <span class="min-w-0">
                                                        <span class="block text-sm font-semibold">
                                                            {{ group.label }}
                                                        </span>
                                                        <span class="block text-xs text-muted-foreground">
                                                            {{ t('settings.group_permissions_count', {
                                                                count: group.permissions.length,
                                                            }) }}
                                                        </span>
                                                    </span>
                                                </button>
                                                <button
                                                    type="button"
                                                    class="shrink-0 text-xs font-medium text-primary hover:underline"
                                                    @click.stop="setGroupPermissions(group.key, !isGroupFullySelected(group.key))"
                                                >
                                                    {{ isGroupFullySelected(group.key)
                                                        ? t('settings.deselect_all_permissions')
                                                        : t('settings.select_all_permissions') }}
                                                </button>
                                            </div>

                                            <div v-show="!isGroupCollapsed(group.key)" class="divide-y">
                                                <label
                                                    v-for="permission in group.permissions"
                                                    :key="permission.name"
                                                    class="flex cursor-pointer items-start gap-3 px-4 py-3 hover:bg-muted/30"
                                                >
                                                    <input
                                                        v-model="permission.enabled"
                                                        type="checkbox"
                                                        class="mt-1 size-4 shrink-0 cursor-pointer rounded border-input text-primary accent-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                    >
                                                    <span class="min-w-0">
                                                        <span class="block text-sm font-medium leading-5">
                                                            {{ permission.label }}
                                                        </span>
                                                        <span class="mt-0.5 block text-xs text-muted-foreground">
                                                            {{ permission.name }}
                                                        </span>
                                                    </span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="sticky bottom-0 flex justify-end border-t bg-card/95 px-5 py-4 backdrop-blur">
                                    <Button
                                        :disabled="permissionForm.processing"
                                        @click="saveTeamPermissions"
                                    >
                                        {{ t('common.save_changes') }}
                                    </Button>
                                </div>
                            </template>

                            <div
                                v-else
                                class="flex flex-1 flex-col items-center justify-center gap-3 px-6 py-16 text-center"
                            >
                                <Shield class="size-10 text-muted-foreground/60" />
                                <p class="text-sm text-muted-foreground">
                                    {{ t('settings.no_team_selected') }}
                                </p>
                                <Button size="sm" @click="openCreatePanel">
                                    <Plus class="size-4" />
                                    {{ t('settings.new_team') }}
                                </Button>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>

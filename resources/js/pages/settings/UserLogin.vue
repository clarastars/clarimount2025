<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';

interface UserRow {
    id: number;
    name: string;
    email: string;
    uses_password_login: boolean;
}

interface PaginatedUsers {
    data: UserRow[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    current_page: number;
    last_page: number;
}

interface Props {
    users: PaginatedUsers;
    filters: {
        search: string;
    };
    status?: string | null;
}

const props = defineProps<Props>();
const { t } = useI18n();

const search = ref(props.filters.search ?? '');
const editingUserId = ref<number | null>(null);

const breadcrumbItems = computed((): BreadcrumbItem[] => [
    {
        title: t('settings.user_login'),
        href: '/settings/user-login',
    },
]);

const editForm = useForm({
    uses_password_login: false,
    password: '',
    password_confirmation: '',
});

const startEdit = (user: UserRow) => {
    editingUserId.value = user.id;
    editForm.uses_password_login = user.uses_password_login;
    editForm.password = '';
    editForm.password_confirmation = '';
    editForm.clearErrors();
};

const cancelEdit = () => {
    editingUserId.value = null;
    editForm.reset();
    editForm.clearErrors();
};

const onPasswordLoginToggle = (value: boolean) => {
    editForm.uses_password_login = value;
    if (!value) {
        editForm.password = '';
        editForm.password_confirmation = '';
    }
};

const submitEdit = (userId: number) => {
    editForm
        .transform((data) => ({
            ...data,
            uses_password_login: Boolean(data.uses_password_login),
        }))
        .put(route('settings.user-login.update', userId), {
            preserveScroll: true,
            onSuccess: () => {
                editingUserId.value = null;
                editForm.reset();
            },
        });
};

const applySearch = () => {
    router.get(
        route('settings.user-login.index'),
        { search: search.value || undefined },
        { preserveState: true, replace: true },
    );
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('settings.user_login')" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall
                    :title="t('settings.user_login')"
                    :description="t('settings.user_login_description')"
                />

                <div v-if="status" class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                    {{ status }}
                </div>

                <form class="flex gap-2" @submit.prevent="applySearch">
                    <Input
                        v-model="search"
                        type="search"
                        :placeholder="t('settings.user_login_search_placeholder')"
                        class="max-w-md"
                    />
                    <Button type="submit" variant="outline">
                        {{ t('settings.user_login_search') }}
                    </Button>
                </form>

                <div class="space-y-4">
                    <div
                        v-for="user in users.data"
                        :key="user.id"
                        class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"
                    >
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="font-medium text-slate-900">{{ user.name }}</p>
                                <p class="text-sm text-slate-600" dir="ltr">{{ user.email }}</p>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{
                                        user.uses_password_login
                                            ? t('settings.user_login_method_password')
                                            : t('settings.user_login_method_otp')
                                    }}
                                </p>
                            </div>

                            <Button
                                v-if="editingUserId !== user.id"
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="startEdit(user)"
                            >
                                {{ t('settings.user_login_edit') }}
                            </Button>
                        </div>

                        <form
                            v-if="editingUserId === user.id"
                            class="mt-4 space-y-4 border-t border-slate-100 pt-4"
                            @submit.prevent="submitEdit(user.id)"
                        >
                            <div class="flex items-center justify-between gap-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <Label :for="`uses_password_login_${user.id}`" class="cursor-pointer">
                                    {{ t('settings.user_login_use_password') }}
                                </Label>
                                <Switch
                                    :id="`uses_password_login_${user.id}`"
                                    :checked="editForm.uses_password_login"
                                    :disabled="editForm.processing"
                                    @update:checked="onPasswordLoginToggle"
                                />
                            </div>

                            <div v-if="editForm.uses_password_login" class="space-y-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <p class="text-sm text-slate-600">
                                    {{ t('settings.user_login_password_hint') }}
                                </p>

                                <div class="grid gap-2">
                                    <Label :for="`password_${user.id}`">{{ t('settings.user_login_password') }}</Label>
                                    <Input
                                        :id="`password_${user.id}`"
                                        v-model="editForm.password"
                                        type="password"
                                        autocomplete="new-password"
                                        :placeholder="t('settings.user_login_password_placeholder')"
                                    />
                                    <InputError :message="editForm.errors.password" />
                                </div>

                                <div class="grid gap-2">
                                    <Label :for="`password_confirmation_${user.id}`">
                                        {{ t('settings.user_login_password_confirmation') }}
                                    </Label>
                                    <Input
                                        :id="`password_confirmation_${user.id}`"
                                        v-model="editForm.password_confirmation"
                                        type="password"
                                        autocomplete="new-password"
                                        :placeholder="t('settings.user_login_password_confirmation_placeholder')"
                                    />
                                </div>
                            </div>

                            <div class="flex gap-2">
                                <Button type="submit" :disabled="editForm.processing">
                                    {{ t('settings.user_login_save') }}
                                </Button>
                                <Button type="button" variant="outline" @click="cancelEdit">
                                    {{ t('settings.user_login_cancel') }}
                                </Button>
                            </div>
                        </form>
                    </div>

                    <p v-if="users.data.length === 0" class="text-sm text-slate-500">
                        {{ t('settings.user_login_empty') }}
                    </p>
                </div>

                <div v-if="users.last_page > 1" class="flex flex-wrap gap-2">
                    <Button
                        v-for="link in users.links"
                        :key="link.label"
                        :variant="link.active ? 'default' : 'outline'"
                        size="sm"
                        :disabled="!link.url"
                        as-child
                    >
                        <a v-if="link.url" :href="link.url" v-html="link.label" />
                        <span v-else v-html="link.label" />
                    </Button>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>

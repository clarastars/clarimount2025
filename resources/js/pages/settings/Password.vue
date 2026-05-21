<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type BreadcrumbItem } from '@/types';

interface Props {
    status?: string | null;
}

const props = defineProps<Props>();
const { t } = useI18n();

const breadcrumbItems = computed((): BreadcrumbItem[] => [
    {
        title: t('password.title'),
        href: '/settings/password',
    },
]);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
        },
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('password.title')" />

        <SettingsLayout>
            <div class="max-w-md space-y-4">
                <div
                    v-if="status"
                    class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700 dark:border-green-800 dark:bg-green-950 dark:text-green-300"
                    role="status"
                >
                    {{ status }}
                </div>

                <Card>
                    <CardHeader class="pb-4">
                        <CardTitle class="text-base">{{ t('password.updateTitle') }}</CardTitle>
                        <p class="text-sm text-muted-foreground">{{ t('password.hint') }}</p>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="updatePassword" class="space-y-4">
                            <div class="space-y-2">
                                <Label for="current_password">{{ t('password.currentPassword') }}</Label>
                                <Input
                                    id="current_password"
                                    v-model="form.current_password"
                                    type="password"
                                    autocomplete="current-password"
                                />
                                <InputError :message="form.errors.current_password" />
                            </div>

                            <div class="space-y-2">
                                <Label for="password">{{ t('password.newPassword') }}</Label>
                                <Input
                                    id="password"
                                    v-model="form.password"
                                    type="password"
                                    autocomplete="new-password"
                                />
                                <InputError :message="form.errors.password" />
                            </div>

                            <div class="space-y-2">
                                <Label for="password_confirmation">{{ t('password.confirmPassword') }}</Label>
                                <Input
                                    id="password_confirmation"
                                    v-model="form.password_confirmation"
                                    type="password"
                                    autocomplete="new-password"
                                />
                                <InputError :message="form.errors.password_confirmation" />
                            </div>

                            <Button type="submit" class="w-full sm:w-auto" :disabled="form.processing">
                                {{ t('password.savePassword') }}
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>

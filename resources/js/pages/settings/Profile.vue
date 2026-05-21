<script setup lang="ts">
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

import DeleteUser from '@/components/DeleteUser.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem, type User } from '@/types';

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
}

defineProps<Props>();

const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: t('profile.title'),
        href: '/settings/profile',
    },
];

const page = usePage();
const user = page.props.auth.user as User;
const canDeleteAccount = computed(
    () => !((page.props.auth as { is_employee?: boolean })?.is_employee ?? false),
);

const form = useForm({
    name: user.name,
    email: user.email,
    language: user.language || 'en',
});

// Track the original language to detect changes
const originalLanguage = ref(user.language || 'en');

const submit = () => {
    const languageChanged = form.language !== originalLanguage.value;
    
    form.patch(route('profile.update'), {
        preserveScroll: true,
        onSuccess: () => {
            // If language was changed, do a hard refresh to load new translations
            if (languageChanged) {
                window.location.reload();
            }
        },
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('profile.title')" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall :title="t('profile.information')" :description="t('profile.description')" />

                <form @submit.prevent="submit" class="space-y-6">
                    <div class="grid gap-2">
                        <Label for="name">{{ t('profile.name') }}</Label>
                        <Input 
                            id="name" 
                            class="mt-1 block w-full" 
                            v-model="form.name" 
                            required 
                            autocomplete="name" 
                            :placeholder="t('profile.namePlaceholder')" 
                        />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">{{ t('profile.email') }}</Label>
                        <Input
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            v-model="form.email"
                            required
                            autocomplete="username"
                            :placeholder="t('profile.emailPlaceholder')"
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="language">{{ t('profile.language') }}</Label>
                        <Select
                            id="language"
                            v-model="form.language"
                            class="mt-1 block w-full"
                        >
                            <option value="en">{{ t('languages.en') }}</option>
                            <option value="ar">{{ t('languages.ar') }}</option>
                        </Select>
                        <p class="text-sm text-muted-foreground">{{ t('profile.languageDescription') }}</p>
                        <InputError class="mt-2" :message="form.errors.language" />
                    </div>

                    <div v-if="mustVerifyEmail && !user.email_verified_at">
                        <p class="-mt-4 text-sm text-muted-foreground">
                            {{ t('profile.emailUnverified') }}
                            <Link
                                :href="route('verification.send')"
                                method="post"
                                as="button"
                                class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                            >
                                {{ t('profile.resendVerification') }}
                            </Link>
                        </p>

                        <div v-if="status === 'verification-link-sent'" class="mt-2 text-sm font-medium text-green-600">
                            {{ t('profile.verificationSent') }}
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <Button :disabled="form.processing">{{ t('profile.save') }}</Button>

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p v-show="form.recentlySuccessful" class="text-sm text-neutral-600">{{ t('profile.saved') }}</p>
                        </Transition>
                    </div>
                </form>
            </div>

            <DeleteUser v-if="canDeleteAccount" />
        </SettingsLayout>
    </AppLayout>
</template>

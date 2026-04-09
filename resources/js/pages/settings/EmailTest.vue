<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';

interface Props {
    defaultToEmail: string;
    status?: string | null;
}

const props = defineProps<Props>();
const { t } = useI18n();

const breadcrumbItems = computed((): BreadcrumbItem[] => [
    {
        title: t('settings.email_test'),
        href: '/settings/email-test',
    },
]);

const form = useForm({
    to: props.defaultToEmail ?? '',
    subject: t('settings.email_test_default_subject'),
    message: t('settings.email_test_default_message'),
});

const submit = () => {
    form.post(route('settings.email-test.send'));
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('settings.email_test')" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall :title="t('settings.email_test')" :description="t('settings.email_test_description')" />

                <div v-if="status" class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                    {{ status }}
                </div>

                <form class="space-y-4" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="to">{{ t('settings.email_test_to') }}</Label>
                        <Input
                            id="to"
                            v-model="form.to"
                            type="email"
                            autocomplete="email"
                            :placeholder="t('settings.email_test_to_placeholder')"
                            required
                        />
                        <InputError :message="form.errors.to" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="subject">{{ t('settings.email_test_subject') }}</Label>
                        <Input
                            id="subject"
                            v-model="form.subject"
                            type="text"
                            :placeholder="t('settings.email_test_subject_placeholder')"
                            required
                        />
                        <InputError :message="form.errors.subject" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="message">{{ t('settings.email_test_message') }}</Label>
                        <textarea
                            id="message"
                            v-model="form.message"
                            rows="6"
                            class="w-full min-h-[120px] rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus:border-transparent focus:outline-none focus:ring-2 focus:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                            :placeholder="t('settings.email_test_message_placeholder')"
                            required
                        />
                        <InputError :message="form.errors.message" />
                    </div>

                    <InputError :message="form.errors.send" />

                    <div class="flex items-center gap-3 pt-2">
                        <Button type="submit" :disabled="form.processing">
                            {{ form.processing ? t('settings.email_test_sending') : t('settings.email_test_send_button') }}
                        </Button>
                    </div>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>

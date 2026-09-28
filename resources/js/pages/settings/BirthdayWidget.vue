<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

interface Props {
    settings: {
        enabled: boolean;
        scope: string;
        days_ahead: number;
    };
    scopes: string[];
    status?: string | null;
}

const props = defineProps<Props>();
const { t } = useI18n();

const breadcrumbItems = computed((): BreadcrumbItem[] => [
    {
        title: t('settings.birthday_widget'),
        href: '/settings/birthday-widget',
    },
]);

const form = useForm({
    enabled: props.settings.enabled,
    scope: props.settings.scope,
    days_ahead: props.settings.days_ahead,
});

const scopeLabel = (scope: string): string => {
    const key = `settings.birthday_widget_scope_${scope}`;
    const translated = t(key);
    return translated === key ? scope : translated;
};

const submit = (): void => {
    form
        .transform((data) => ({
            ...data,
            enabled: Boolean(data.enabled),
            days_ahead: Number(data.days_ahead),
        }))
        .put(route('settings.birthday-widget.update'));
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('settings.birthday_widget')" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall
                    :title="t('settings.birthday_widget')"
                    :description="t('settings.birthday_widget_description')"
                />

                <div
                    v-if="status"
                    class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700"
                >
                    {{ status }}
                </div>

                <form class="space-y-5 rounded-lg border p-4" @submit.prevent="submit">
                    <label class="inline-flex cursor-pointer items-center gap-2 text-sm">
                        <input
                            v-model="form.enabled"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300"
                        >
                        <span>{{ t('settings.birthday_widget_enabled') }}</span>
                    </label>
                    <p class="text-xs text-muted-foreground -mt-3">
                        {{ t('settings.birthday_widget_enabled_hint') }}
                    </p>

                    <div class="space-y-2">
                        <Label for="scope">{{ t('settings.birthday_widget_scope') }}</Label>
                        <select
                            id="scope"
                            v-model="form.scope"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            :disabled="!form.enabled"
                        >
                            <option v-for="scope in scopes" :key="scope" :value="scope">
                                {{ scopeLabel(scope) }}
                            </option>
                        </select>
                        <p class="text-xs text-muted-foreground">
                            {{ t('settings.birthday_widget_scope_hint') }}
                        </p>
                        <p v-if="form.errors.scope" class="text-sm text-red-600">{{ form.errors.scope }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="days_ahead">{{ t('settings.birthday_widget_days_ahead') }}</Label>
                        <Input
                            id="days_ahead"
                            v-model="form.days_ahead"
                            type="number"
                            min="0"
                            max="365"
                            required
                            :disabled="!form.enabled"
                        />
                        <p class="text-xs text-muted-foreground">
                            {{ t('settings.birthday_widget_days_ahead_hint') }}
                        </p>
                        <p v-if="form.errors.days_ahead" class="text-sm text-red-600">{{ form.errors.days_ahead }}</p>
                    </div>

                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? t('common.saving') : t('common.save') }}
                    </Button>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>

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

interface CompanyOption {
    id: number;
    name: string;
    name_ar?: string | null;
    name_en?: string | null;
}

interface Props {
    settings: {
        enabled: boolean;
        scope: string;
        days_ahead: number;
        company_filter_mode: string;
        company_ids: number[];
    };
    scopes: string[];
    companyFilterModes: string[];
    companies: CompanyOption[];
    status?: string | null;
}

const props = defineProps<Props>();
const { t, locale } = useI18n();

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
    company_filter_mode: props.settings.company_filter_mode || 'all',
    company_ids: [...(props.settings.company_ids || [])],
});

const needsCompanySelection = computed(
    () => form.company_filter_mode === 'include' || form.company_filter_mode === 'exclude',
);

const scopeLabel = (scope: string): string => {
    const key = `settings.birthday_widget_scope_${scope}`;
    const translated = t(key);
    return translated === key ? scope : translated;
};

const filterModeLabel = (mode: string): string => {
    const key = `settings.birthday_widget_company_filter_${mode}`;
    const translated = t(key);
    return translated === key ? mode : translated;
};

const companyLabel = (company: CompanyOption): string => {
    if (locale.value === 'ar') {
        return company.name_ar || company.name_en || company.name;
    }

    return company.name_en || company.name_ar || company.name;
};

const toggleCompany = (companyId: number): void => {
    const index = form.company_ids.indexOf(companyId);
    if (index === -1) {
        form.company_ids.push(companyId);
    } else {
        form.company_ids.splice(index, 1);
    }
};

const selectAllCompanies = (): void => {
    form.company_ids = props.companies.map((company) => company.id);
};

const clearCompanies = (): void => {
    form.company_ids = [];
};

const submit = (): void => {
    form
        .transform((data) => ({
            ...data,
            enabled: Boolean(data.enabled),
            days_ahead: Number(data.days_ahead),
            company_ids: data.company_filter_mode === 'all' ? [] : data.company_ids.map(Number),
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
                        <Label for="company_filter_mode">{{ t('settings.birthday_widget_company_filter') }}</Label>
                        <select
                            id="company_filter_mode"
                            v-model="form.company_filter_mode"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            :disabled="!form.enabled"
                            @change="form.company_filter_mode === 'all' ? clearCompanies() : undefined"
                        >
                            <option v-for="mode in companyFilterModes" :key="mode" :value="mode">
                                {{ filterModeLabel(mode) }}
                            </option>
                        </select>
                        <p class="text-xs text-muted-foreground">
                            {{ t('settings.birthday_widget_company_filter_hint') }}
                        </p>
                        <p v-if="form.errors.company_filter_mode" class="text-sm text-red-600">
                            {{ form.errors.company_filter_mode }}
                        </p>
                    </div>

                    <div v-if="needsCompanySelection" class="space-y-3 rounded-md border p-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <Label>{{ t('settings.birthday_widget_companies') }}</Label>
                            <div class="flex gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    :disabled="!form.enabled"
                                    @click="selectAllCompanies"
                                >
                                    {{ t('settings.birthday_widget_companies_select_all') }}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    :disabled="!form.enabled"
                                    @click="clearCompanies"
                                >
                                    {{ t('settings.birthday_widget_companies_clear') }}
                                </Button>
                            </div>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{
                                form.company_filter_mode === 'include'
                                    ? t('settings.birthday_widget_companies_include_hint')
                                    : t('settings.birthday_widget_companies_exclude_hint')
                            }}
                        </p>

                        <div v-if="companies.length === 0" class="text-sm text-muted-foreground">
                            {{ t('settings.birthday_widget_companies_empty') }}
                        </div>
                        <div v-else class="max-h-64 space-y-2 overflow-y-auto rounded-md border p-2">
                            <label
                                v-for="company in companies"
                                :key="company.id"
                                class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-muted/50"
                            >
                                <input
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-gray-300"
                                    :checked="form.company_ids.includes(company.id)"
                                    :disabled="!form.enabled"
                                    @change="toggleCompany(company.id)"
                                >
                                <span>{{ companyLabel(company) }}</span>
                            </label>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{ t('settings.birthday_widget_companies_selected', { count: form.company_ids.length }) }}
                        </p>
                        <p v-if="form.errors.company_ids" class="text-sm text-red-600">{{ form.errors.company_ids }}</p>
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

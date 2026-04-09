<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import Heading from '@/components/Heading.vue';
import { type BreadcrumbItem } from '@/types';

const { t } = useI18n();

const breadcrumbs = computed((): BreadcrumbItem[] => [
    {
        title: t('nav.dashboard'),
        href: '/dashboard',
    },
    {
        title: t('companies.title'),
        href: '/companies',
    },
    {
        title: t('companies.create_company'),
        href: '/companies/create',
    },
]);

const form = useForm({
    name_en: '',
    name_ar: '',
    company_email: '',
    description_en: '',
    description_ar: '',
    website: '',
    logo: null as File | null,
});

const logoPreview = ref<string | null>(null);

const handleLogoChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];

    if (!file) {
        form.logo = null;
        logoPreview.value = null;
        return;
    }

    const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
    if (!allowedTypes.includes(file.type)) {
        form.setError('logo', t('companies.logo_invalid_type'));
        target.value = '';
        return;
    }

    if (file.size > 2 * 1024 * 1024) {
        form.setError('logo', t('companies.logo_invalid_size'));
        target.value = '';
        return;
    }

    form.clearErrors('logo');
    form.logo = file;

    const reader = new FileReader();
    reader.onload = (e) => {
        logoPreview.value = e.target?.result as string;
    };
    reader.readAsDataURL(file);
};

const removeSelectedLogo = () => {
    form.logo = null;
    logoPreview.value = null;
    const input = document.getElementById('logo') as HTMLInputElement | null;
    if (input) {
        input.value = '';
    }
};

const submit = () => {
    form.post(route('companies.store'), {
        forceFormData: true,
    });
};
</script>

<template>
    <Head :title="t('companies.create_company')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="max-w-2xl">
                                    <Heading :title="t('companies.create_company')" />
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1 mb-6">
                {{ t('companies.create_company_description') }}
            </p>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('companies.company_details') }}</CardTitle>
                    <CardDescription>
                        {{ t('companies.company_details_description') }}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-6">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="name_en">{{ t('companies.name_en') }}</Label>
                                <Input
                                    id="name_en"
                                    v-model="form.name_en"
                                    type="text"
                                    :placeholder="t('companies.name_en_placeholder')"
                                    required
                                />
                                <InputError :message="form.errors.name_en" />
                            </div>

                            <div class="space-y-2">
                                <Label for="name_ar">{{ t('companies.name_ar') }}</Label>
                                <Input
                                    id="name_ar"
                                    v-model="form.name_ar"
                                    type="text"
                                    :placeholder="t('companies.name_ar_placeholder')"
                                    required
                                    dir="rtl"
                                    class="text-right"
                                />
                                <InputError :message="form.errors.name_ar" />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="company_email">{{ t('companies.company_email') }}</Label>
                            <Input
                                id="company_email"
                                v-model="form.company_email"
                                type="email"
                                :placeholder="t('companies.company_email_placeholder')"
                                required
                            />
                            <InputError :message="form.errors.company_email" />
                        </div>

                        <div class="space-y-2">
                            <Label for="website">{{ t('companies.website') }}</Label>
                            <Input
                                id="website"
                                v-model="form.website"
                                type="url"
                                :placeholder="t('companies.website_placeholder')"
                            />
                            <InputError :message="form.errors.website" />
                        </div>

                        <div class="space-y-2">
                            <Label for="logo">{{ t('companies.logo') }}</Label>
                            <Input
                                id="logo"
                                type="file"
                                accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                                @change="handleLogoChange"
                            />
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ t('companies.logo_help') }}
                            </p>
                            <div v-if="logoPreview" class="space-y-2">
                                <img :src="logoPreview" alt="Logo preview" class="h-20 w-20 rounded-md border object-contain p-1" />
                                <Button variant="outline" type="button" @click="removeSelectedLogo">
                                    {{ t('companies.remove_logo') }}
                                </Button>
                            </div>
                            <InputError :message="form.errors.logo" />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="description_en">{{ t('companies.description_en') }}</Label>
                                <textarea
                                    id="description_en"
                                    v-model="form.description_en"
                                    rows="4"
                                    :placeholder="t('companies.description_en_placeholder')"
                                    class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                />
                                <InputError :message="form.errors.description_en" />
                            </div>

                            <div class="space-y-2">
                                <Label for="description_ar">{{ t('companies.description_ar') }}</Label>
                                <textarea
                                    id="description_ar"
                                    v-model="form.description_ar"
                                    rows="4"
                                    :placeholder="t('companies.description_ar_placeholder')"
                                    dir="rtl"
                                    class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 text-right"
                                />
                                <InputError :message="form.errors.description_ar" />
                            </div>
                        </div>

                        <div class="flex items-center space-x-4">
                            <Button type="submit" :disabled="form.processing">
                                {{ form.processing ? t('common.creating') : t('companies.create_company') }}
                            </Button>
                            <Button variant="outline" type="button" as-child>
                                <a :href="route('companies.index')">{{ t('common.cancel') }}</a>
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template> 
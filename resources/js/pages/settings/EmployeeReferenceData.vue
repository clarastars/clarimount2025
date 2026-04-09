<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { type BreadcrumbItem } from '@/types';

interface NationalityItem {
    id: number;
    code: string;
    name_en: string;
    name_ar: string;
    is_active: boolean;
}

interface CountryItem {
    id: number;
    code: string;
    code_alpha3: string;
    name_en: string;
    name_ar: string;
    is_active: boolean;
}

interface Props {
    nationalities: NationalityItem[];
    countries: CountryItem[];
}

const props = defineProps<Props>();
const { t } = useI18n();

const breadcrumbs = computed((): BreadcrumbItem[] => [
    {
        title: t('settings.employee_reference_data'),
        href: '/settings/employee-reference-data',
    },
]);

const createNationalityForm = useForm({
    code: '',
    name_en: '',
    name_ar: '',
    is_active: true,
});

const createCountryForm = useForm({
    code: '',
    code_alpha3: '',
    name_en: '',
    name_ar: '',
    is_active: true,
});

const editingNationalityId = ref<number | null>(null);
const editNationalityForm = useForm({
    code: '',
    name_en: '',
    name_ar: '',
    is_active: true,
});

const editingCountryId = ref<number | null>(null);
const editCountryForm = useForm({
    code: '',
    code_alpha3: '',
    name_en: '',
    name_ar: '',
    is_active: true,
});

const startEditNationality = (item: NationalityItem) => {
    editingNationalityId.value = item.id;
    editNationalityForm.code = item.code;
    editNationalityForm.name_en = item.name_en;
    editNationalityForm.name_ar = item.name_ar;
    editNationalityForm.is_active = item.is_active;
    editNationalityForm.clearErrors();
};

const startEditCountry = (item: CountryItem) => {
    editingCountryId.value = item.id;
    editCountryForm.code = item.code;
    editCountryForm.code_alpha3 = item.code_alpha3;
    editCountryForm.name_en = item.name_en;
    editCountryForm.name_ar = item.name_ar;
    editCountryForm.is_active = item.is_active;
    editCountryForm.clearErrors();
};

const createNationality = () => {
    createNationalityForm.post(route('settings.employee-reference-data.nationalities.store'), {
        preserveScroll: true,
        onSuccess: () => createNationalityForm.reset('code', 'name_en', 'name_ar'),
    });
};

const updateNationality = () => {
    if (!editingNationalityId.value) return;
    editNationalityForm.put(
        route('settings.employee-reference-data.nationalities.update', editingNationalityId.value),
        {
            preserveScroll: true,
            onSuccess: () => {
                editingNationalityId.value = null;
            },
        },
    );
};

const createCountry = () => {
    createCountryForm.post(route('settings.employee-reference-data.countries.store'), {
        preserveScroll: true,
        onSuccess: () => createCountryForm.reset('code', 'code_alpha3', 'name_en', 'name_ar'),
    });
};

const updateCountry = () => {
    if (!editingCountryId.value) return;
    editCountryForm.put(
        route('settings.employee-reference-data.countries.update', editingCountryId.value),
        {
            preserveScroll: true,
            onSuccess: () => {
                editingCountryId.value = null;
            },
        },
    );
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('settings.employee_reference_data')" />

        <SettingsLayout>
            <div class="space-y-8">
                <HeadingSmall
                    :title="t('settings.employee_reference_data')"
                    :description="t('settings.employee_reference_data_description')"
                />

                <Card>
                    <CardHeader>
                        <CardTitle>{{ t('settings.nationalities') }}</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <form class="grid grid-cols-1 md:grid-cols-5 gap-3 items-start" @submit.prevent="createNationality">
                            <div>
                                <Label for="n-code">{{ t('settings.code') }}</Label>
                                <Input id="n-code" v-model="createNationalityForm.code" maxlength="10" placeholder="EG" />
                                <InputError class="mt-1" :message="createNationalityForm.errors.code" />
                            </div>
                            <div>
                                <Label for="n-name-en">{{ t('settings.name_en') }}</Label>
                                <Input id="n-name-en" v-model="createNationalityForm.name_en" placeholder="Egyptian" />
                                <InputError class="mt-1" :message="createNationalityForm.errors.name_en" />
                            </div>
                            <div>
                                <Label for="n-name-ar">{{ t('settings.name_ar') }}</Label>
                                <Input id="n-name-ar" v-model="createNationalityForm.name_ar" placeholder="مصري" />
                                <InputError class="mt-1" :message="createNationalityForm.errors.name_ar" />
                            </div>
                            <div class="flex items-center gap-2 h-10 pt-6">
                                <input id="n-active" v-model="createNationalityForm.is_active" type="checkbox" class="h-4 w-4" />
                                <Label for="n-active">{{ t('settings.active') }}</Label>
                            </div>
                            <Button type="submit" class="mt-6" :disabled="createNationalityForm.processing">{{ t('common.add') }}</Button>
                        </form>

                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[700px] text-sm">
                                <thead>
                                    <tr class="border-b text-left">
                                        <th class="py-2">{{ t('settings.code') }}</th>
                                        <th class="py-2">{{ t('settings.name_en') }}</th>
                                        <th class="py-2">{{ t('settings.name_ar') }}</th>
                                        <th class="py-2">{{ t('settings.active') }}</th>
                                        <th class="py-2">{{ t('common.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="item in props.nationalities" :key="item.id" class="border-b">
                                        <td class="py-2">{{ item.code }}</td>
                                        <td class="py-2">{{ item.name_en }}</td>
                                        <td class="py-2">{{ item.name_ar }}</td>
                                        <td class="py-2">{{ item.is_active ? t('common.yes') : t('common.no') }}</td>
                                        <td class="py-2">
                                            <Button size="sm" variant="outline" @click="startEditNationality(item)">{{ t('common.edit') }}</Button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <form
                            v-if="editingNationalityId"
                            class="grid grid-cols-1 md:grid-cols-5 gap-3 items-start border rounded-md p-4 bg-muted/20"
                            @submit.prevent="updateNationality"
                        >
                            <div>
                                <Label>{{ t('settings.code') }}</Label>
                                <Input v-model="editNationalityForm.code" maxlength="10" />
                                <InputError class="mt-1" :message="editNationalityForm.errors.code" />
                            </div>
                            <div>
                                <Label>{{ t('settings.name_en') }}</Label>
                                <Input v-model="editNationalityForm.name_en" />
                                <InputError class="mt-1" :message="editNationalityForm.errors.name_en" />
                            </div>
                            <div>
                                <Label>{{ t('settings.name_ar') }}</Label>
                                <Input v-model="editNationalityForm.name_ar" />
                                <InputError class="mt-1" :message="editNationalityForm.errors.name_ar" />
                            </div>
                            <div class="flex items-center gap-2 h-10 pt-6">
                                <input v-model="editNationalityForm.is_active" type="checkbox" class="h-4 w-4" />
                                <Label>{{ t('settings.active') }}</Label>
                            </div>
                            <div class="flex gap-2 mt-6">
                                <Button type="submit" :disabled="editNationalityForm.processing">{{ t('common.save') }}</Button>
                                <Button type="button" variant="outline" @click="editingNationalityId = null">{{ t('common.cancel') }}</Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{{ t('settings.residence_countries') }}</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <form class="grid grid-cols-1 md:grid-cols-6 gap-3 items-start" @submit.prevent="createCountry">
                            <div>
                                <Label for="c-code2">{{ t('settings.code_alpha2') }}</Label>
                                <Input id="c-code2" v-model="createCountryForm.code" maxlength="2" placeholder="EG" />
                                <InputError class="mt-1" :message="createCountryForm.errors.code" />
                            </div>
                            <div>
                                <Label for="c-code3">{{ t('settings.code_alpha3') }}</Label>
                                <Input id="c-code3" v-model="createCountryForm.code_alpha3" maxlength="3" placeholder="EGY" />
                                <InputError class="mt-1" :message="createCountryForm.errors.code_alpha3" />
                            </div>
                            <div>
                                <Label for="c-name-en">{{ t('settings.name_en') }}</Label>
                                <Input id="c-name-en" v-model="createCountryForm.name_en" placeholder="Egypt" />
                                <InputError class="mt-1" :message="createCountryForm.errors.name_en" />
                            </div>
                            <div>
                                <Label for="c-name-ar">{{ t('settings.name_ar') }}</Label>
                                <Input id="c-name-ar" v-model="createCountryForm.name_ar" placeholder="مصر" />
                                <InputError class="mt-1" :message="createCountryForm.errors.name_ar" />
                            </div>
                            <div class="flex items-center gap-2 h-10 pt-6">
                                <input id="c-active" v-model="createCountryForm.is_active" type="checkbox" class="h-4 w-4" />
                                <Label for="c-active">{{ t('settings.active') }}</Label>
                            </div>
                            <Button type="submit" class="mt-6" :disabled="createCountryForm.processing">{{ t('common.add') }}</Button>
                        </form>

                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[860px] text-sm">
                                <thead>
                                    <tr class="border-b text-left">
                                        <th class="py-2">{{ t('settings.code_alpha2') }}</th>
                                        <th class="py-2">{{ t('settings.code_alpha3') }}</th>
                                        <th class="py-2">{{ t('settings.name_en') }}</th>
                                        <th class="py-2">{{ t('settings.name_ar') }}</th>
                                        <th class="py-2">{{ t('settings.active') }}</th>
                                        <th class="py-2">{{ t('common.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="item in props.countries" :key="item.id" class="border-b">
                                        <td class="py-2">{{ item.code }}</td>
                                        <td class="py-2">{{ item.code_alpha3 }}</td>
                                        <td class="py-2">{{ item.name_en }}</td>
                                        <td class="py-2">{{ item.name_ar }}</td>
                                        <td class="py-2">{{ item.is_active ? t('common.yes') : t('common.no') }}</td>
                                        <td class="py-2">
                                            <Button size="sm" variant="outline" @click="startEditCountry(item)">{{ t('common.edit') }}</Button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <form
                            v-if="editingCountryId"
                            class="grid grid-cols-1 md:grid-cols-6 gap-3 items-start border rounded-md p-4 bg-muted/20"
                            @submit.prevent="updateCountry"
                        >
                            <div>
                                <Label>{{ t('settings.code_alpha2') }}</Label>
                                <Input v-model="editCountryForm.code" maxlength="2" />
                                <InputError class="mt-1" :message="editCountryForm.errors.code" />
                            </div>
                            <div>
                                <Label>{{ t('settings.code_alpha3') }}</Label>
                                <Input v-model="editCountryForm.code_alpha3" maxlength="3" />
                                <InputError class="mt-1" :message="editCountryForm.errors.code_alpha3" />
                            </div>
                            <div>
                                <Label>{{ t('settings.name_en') }}</Label>
                                <Input v-model="editCountryForm.name_en" />
                                <InputError class="mt-1" :message="editCountryForm.errors.name_en" />
                            </div>
                            <div>
                                <Label>{{ t('settings.name_ar') }}</Label>
                                <Input v-model="editCountryForm.name_ar" />
                                <InputError class="mt-1" :message="editCountryForm.errors.name_ar" />
                            </div>
                            <div class="flex items-center gap-2 h-10 pt-6">
                                <input v-model="editCountryForm.is_active" type="checkbox" class="h-4 w-4" />
                                <Label>{{ t('settings.active') }}</Label>
                            </div>
                            <div class="flex gap-2 mt-6">
                                <Button type="submit" :disabled="editCountryForm.processing">{{ t('common.save') }}</Button>
                                <Button type="button" variant="outline" @click="editingCountryId = null">{{ t('common.cancel') }}</Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>


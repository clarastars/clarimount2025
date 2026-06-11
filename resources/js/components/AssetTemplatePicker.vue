<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import Icon from '@/components/Icon.vue';
import type { AssetCategory, Company } from '@/types';

export interface AssetTemplateOption {
    id: number;
    name: string;
    display_name?: string;
    manufacturer?: string | null;
    model_name?: string | null;
    model_number?: string | null;
    category_name?: string;
    is_global?: boolean;
    asset_category?: { id: number; name: string } | null;
    company?: { id: number; name_en: string } | null;
}

interface Props {
    modelValue: AssetTemplateOption | null;
    categories: AssetCategory[];
    companies: Company[];
    defaultCompanyId?: number | null;
    searchUrl?: string;
    byCategoryUrl?: string;
    storeUrl?: string;
    inputId?: string;
}

const props = withDefaults(defineProps<Props>(), {
    searchUrl: '/api/asset-templates/search',
    byCategoryUrl: '/api/asset-templates/by-category',
    storeUrl: '/asset-templates',
    inputId: 'template_search',
});

const emit = defineEmits<{
    'update:modelValue': [AssetTemplateOption | null];
}>();

const { t } = useI18n();

const templateSearchQuery = ref('');
const templateSearchResults = ref<AssetTemplateOption[]>([]);
const isTemplateSearching = ref(false);
const showTemplateSearchResults = ref(false);
const isTemplateBrowserOpen = ref(false);
const isTemplateDialogOpen = ref(false);
const templatesByCategory = ref<Record<string, AssetTemplateOption[]>>({});
const isLoadingTemplates = ref(false);
const activeCategoryTab = ref('');
const templateImagePreview = ref<string | null>(null);
const templateImageError = ref<string | null>(null);

const selectedTemplate = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const templateForm = useForm({
    name: '',
    manufacturer: '',
    model_name: '',
    model_number: '',
    asset_category_id: '',
    company_id: props.defaultCompanyId ? String(props.defaultCompanyId) : '',
    is_global: false,
    image: null as File | null,
});

watch(
    () => props.defaultCompanyId,
    (companyId) => {
        if (companyId && !templateForm.company_id) {
            templateForm.company_id = String(companyId);
        }
    },
    { immediate: true }
);

const getCsrfToken = (): string =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

let templateSearchTimeout: number;

const searchTemplates = async () => {
    if (templateSearchQuery.value.length < 2) {
        templateSearchResults.value = [];
        showTemplateSearchResults.value = false;
        return;
    }

    isTemplateSearching.value = true;

    try {
        const response = await fetch(
            `${props.searchUrl}?q=${encodeURIComponent(templateSearchQuery.value)}`
        );
        templateSearchResults.value = await response.json();
        showTemplateSearchResults.value = true;
    } catch (error) {
        console.error('Template search failed:', error);
        templateSearchResults.value = [];
    } finally {
        isTemplateSearching.value = false;
    }
};

const handleTemplateSearchInput = () => {
    clearTimeout(templateSearchTimeout);
    templateSearchTimeout = window.setTimeout(searchTemplates, 300);
};

const applyTemplateSelection = (template: AssetTemplateOption) => {
    selectedTemplate.value = template;
    templateSearchQuery.value = template.display_name || template.name;
    showTemplateSearchResults.value = false;
};

const clearTemplateSelection = () => {
    selectedTemplate.value = null;
    templateSearchQuery.value = '';
    templateSearchResults.value = [];
    showTemplateSearchResults.value = false;
};

const hideTemplateSearchResults = () => {
    setTimeout(() => {
        showTemplateSearchResults.value = false;
    }, 200);
};

const openTemplateBrowser = async () => {
    isTemplateBrowserOpen.value = true;
    isLoadingTemplates.value = true;

    try {
        const response = await fetch(props.byCategoryUrl);
        const data = await response.json();
        templatesByCategory.value = data;

        const categories = Object.keys(data);
        if (categories.length > 0) {
            activeCategoryTab.value = categories[0];
        }
    } catch (error) {
        console.error('Failed to load templates by category:', error);
        templatesByCategory.value = {};
    } finally {
        isLoadingTemplates.value = false;
    }
};

const selectTemplateFromBrowser = (template: AssetTemplateOption) => {
    applyTemplateSelection(template);
    isTemplateBrowserOpen.value = false;
};

const handleTemplateImageSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;

    if (!file) {
        templateForm.image = null;
        templateImagePreview.value = null;
        return;
    }

    if (!file.type.startsWith('image/')) {
        templateImageError.value = t('assets.invalid_file_type');
        return;
    }

    templateImageError.value = null;
    templateForm.image = file;

    const reader = new FileReader();
    reader.onload = (e) => {
        templateImagePreview.value = e.target?.result as string;
    };
    reader.readAsDataURL(file);
};

const removeTemplateImage = () => {
    templateForm.image = null;
    templateImagePreview.value = null;
    templateImageError.value = null;

    const fileInput = document.getElementById(`${props.inputId}_image`) as HTMLInputElement | null;
    if (fileInput) {
        fileInput.value = '';
    }
};

const createAssetTemplate = async () => {
    templateForm.processing = true;
    templateForm.clearErrors();

    try {
        const formData = new FormData();
        formData.append('name', templateForm.name);
        formData.append('manufacturer', templateForm.manufacturer);
        formData.append('model_name', templateForm.model_name);
        formData.append('model_number', templateForm.model_number);
        formData.append('asset_category_id', templateForm.asset_category_id.toString());
        formData.append('company_id', templateForm.company_id.toString());
        formData.append('is_global', templateForm.is_global ? '1' : '0');
        formData.append('_from_modal', '1');

        if (templateForm.image) {
            formData.append('image', templateForm.image);
        }

        const response = await fetch(props.storeUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: formData,
        });

        const data = await response.json();

        if (response.ok && data.success) {
            isTemplateDialogOpen.value = false;
            templateForm.reset();
            templateForm.company_id = props.defaultCompanyId ? String(props.defaultCompanyId) : '';
            removeTemplateImage();

            const newTemplate: AssetTemplateOption = {
                id: data.template.id,
                name: data.template.name,
                manufacturer: data.template.manufacturer,
                model_name: data.template.model_name,
                model_number: data.template.model_number,
                asset_category: data.template.asset_category,
                is_global: data.template.is_global,
                display_name: data.template.display_name,
                category_name: data.template.asset_category?.name,
            };

            applyTemplateSelection(newTemplate);
            return;
        }

        if (data.errors) {
            const validFields = [
                'name',
                'manufacturer',
                'model_name',
                'model_number',
                'asset_category_id',
                'company_id',
                'image',
            ] as const;

            Object.keys(data.errors).forEach((key) => {
                if (validFields.includes(key as (typeof validFields)[number])) {
                    templateForm.setError(
                        key as (typeof validFields)[number],
                        data.errors[key][0]
                    );
                }
            });
        }
    } catch (error) {
        console.error('Failed to create template:', error);
        templateForm.setError('name', t('custody.quick_create_failed'));
    } finally {
        templateForm.processing = false;
    }
};
</script>

<template>
    <div class="space-y-2">
        <Label :for="inputId">{{ t('assets.template') }} *</Label>
        <div class="flex gap-2">
            <div class="relative flex-1">
                <Input
                    :id="inputId"
                    v-model="templateSearchQuery"
                    type="text"
                    :placeholder="selectedTemplate ? (selectedTemplate.display_name || selectedTemplate.name) : t('assets.search_template_placeholder')"
                    @input="handleTemplateSearchInput"
                    @focus="templateSearchQuery.length >= 2 && (showTemplateSearchResults = true)"
                    @blur="hideTemplateSearchResults"
                    :disabled="selectedTemplate !== null"
                />

                <button
                    v-if="selectedTemplate"
                    type="button"
                    class="absolute top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 ltr:right-2 rtl:left-2"
                    @click="clearTemplateSelection"
                >
                    <Icon name="X" class="h-4 w-4" />
                </button>

                <div
                    v-if="isTemplateSearching"
                    class="absolute top-1/2 -translate-y-1/2 ltr:right-2 rtl:left-2"
                >
                    <Icon name="Loader2" class="h-4 w-4 animate-spin text-gray-400" />
                </div>

                <div
                    v-if="showTemplateSearchResults && templateSearchResults.length > 0"
                    class="absolute z-50 mt-1 max-h-60 w-full overflow-y-auto rounded-md border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800"
                >
                    <div
                        v-for="template in templateSearchResults"
                        :key="template.id"
                        class="cursor-pointer border-b border-gray-100 px-3 py-2 last:border-b-0 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700"
                        @mousedown.prevent="applyTemplateSelection(template)"
                    >
                        <div class="text-sm font-medium">{{ template.display_name || template.name }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            <span v-if="template.category_name">{{ template.category_name }}</span>
                        </div>
                    </div>
                </div>

                <div
                    v-if="showTemplateSearchResults && templateSearchResults.length === 0 && !isTemplateSearching && templateSearchQuery.length >= 2"
                    class="absolute z-50 mt-1 w-full rounded-md border border-gray-200 bg-white px-3 py-2 shadow-lg dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ t('assets.no_templates_found') }}
                    </div>
                </div>
            </div>

            <Button variant="outline" type="button" @click="openTemplateBrowser">
                <Icon name="List" class="mr-2 h-4 w-4 rtl:mr-0 rtl:ml-2" />
                {{ t('assets.choose_template') }}
            </Button>

            <Dialog v-model:open="isTemplateDialogOpen">
                <DialogTrigger as-child>
                    <Button variant="outline" type="button">
                        <Icon name="Plus" class="mr-2 h-4 w-4 rtl:mr-0 rtl:ml-2" />
                        {{ t('asset_templates.create_template') }}
                    </Button>
                </DialogTrigger>
                <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{{ t('asset_templates.create_template') }}</DialogTitle>
                        <DialogDescription>
                            {{ t('asset_templates.create_template_description') }}
                        </DialogDescription>
                    </DialogHeader>
                    <form class="space-y-4" @submit.prevent="createAssetTemplate">
                        <div class="space-y-2">
                            <Label :for="`${inputId}_name`">{{ t('asset_templates.name') }} *</Label>
                            <Input :id="`${inputId}_name`" v-model="templateForm.name" required />
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label :for="`${inputId}_manufacturer`">{{ t('asset_templates.manufacturer') }}</Label>
                                <Input :id="`${inputId}_manufacturer`" v-model="templateForm.manufacturer" />
                            </div>
                            <div class="space-y-2">
                                <Label :for="`${inputId}_model_name`">{{ t('asset_templates.model_name') }}</Label>
                                <Input :id="`${inputId}_model_name`" v-model="templateForm.model_name" />
                            </div>
                        </div>
                        <div class="space-y-2">
                            <Label :for="`${inputId}_model_number`">{{ t('asset_templates.model_number') }}</Label>
                            <Input :id="`${inputId}_model_number`" v-model="templateForm.model_number" />
                        </div>
                        <div class="space-y-2">
                            <Label :for="`${inputId}_category`">{{ t('asset_templates.category') }} *</Label>
                            <select
                                :id="`${inputId}_category`"
                                v-model="templateForm.asset_category_id"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                required
                            >
                                <option value="">{{ t('asset_templates.select_category') }}</option>
                                <option v-for="category in categories" :key="category.id" :value="category.id">
                                    {{ category.name }}
                                </option>
                            </select>
                        </div>
                        <div class="space-y-2">
                            <Label :for="`${inputId}_company`">{{ t('asset_templates.company') }} *</Label>
                            <select
                                :id="`${inputId}_company`"
                                v-model="templateForm.company_id"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                required
                            >
                                <option value="">{{ t('asset_templates.select_company') }}</option>
                                <option v-for="company in companies" :key="company.id" :value="company.id">
                                    {{ company.name_en }} {{ company.name_ar ? `(${company.name_ar})` : '' }}
                                </option>
                            </select>
                        </div>
                        <div class="flex items-center gap-2">
                            <input
                                :id="`${inputId}_is_global`"
                                v-model="templateForm.is_global"
                                type="checkbox"
                                class="h-4 w-4 rounded border-gray-300"
                            />
                            <Label :for="`${inputId}_is_global`">{{ t('asset_templates.is_global') }}</Label>
                        </div>
                        <div class="space-y-2">
                            <Label :for="`${inputId}_image`">{{ t('asset_templates.image') }}</Label>
                            <input
                                :id="`${inputId}_image`"
                                type="file"
                                accept="image/*"
                                @change="handleTemplateImageSelect"
                            />
                            <img
                                v-if="templateImagePreview"
                                :src="templateImagePreview"
                                alt="Template preview"
                                class="mt-2 max-h-32 rounded-md border object-contain"
                            />
                            <p v-if="templateImageError" class="text-sm text-red-600">{{ templateImageError }}</p>
                        </div>
                    </form>
                    <DialogFooter>
                        <Button variant="outline" type="button" @click="isTemplateDialogOpen = false">
                            {{ t('common.cancel') }}
                        </Button>
                        <Button type="button" :disabled="templateForm.processing" @click="createAssetTemplate">
                            <Icon
                                v-if="templateForm.processing"
                                name="Loader2"
                                class="mr-2 h-4 w-4 animate-spin rtl:mr-0 rtl:ml-2"
                            />
                            {{ t('common.create') }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </div>

    <Dialog v-model:open="isTemplateBrowserOpen">
        <DialogContent class="max-h-[90vh] overflow-hidden sm:max-w-4xl">
            <DialogHeader>
                <DialogTitle>{{ t('assets.choose_template') }}</DialogTitle>
                <DialogDescription>
                    {{ t('assets.browse_templates_by_category') }}
                </DialogDescription>
            </DialogHeader>
            <div class="flex h-[60vh] flex-col">
                <div v-if="isLoadingTemplates" class="flex h-full items-center justify-center">
                    <div class="text-center">
                        <Icon name="Loader2" class="mx-auto mb-2 h-8 w-8 animate-spin text-gray-400" />
                        <p class="text-sm text-gray-500">{{ t('assets.loading_templates') }}</p>
                    </div>
                </div>
                <div v-else-if="Object.keys(templatesByCategory).length > 0" class="flex h-full">
                    <div class="w-64 overflow-y-auto border-r border-gray-200 dark:border-gray-700">
                        <div class="p-4">
                            <h4 class="mb-3 text-sm font-medium">{{ t('assets.categories') }}</h4>
                            <div class="space-y-1">
                                <button
                                    v-for="(templates, categoryName) in templatesByCategory"
                                    :key="categoryName"
                                    type="button"
                                    class="w-full rounded-md px-3 py-2 text-sm transition-colors"
                                    :class="activeCategoryTab === categoryName
                                        ? 'bg-primary text-primary-foreground'
                                        : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800'"
                                    @click="activeCategoryTab = String(categoryName)"
                                >
                                    <div class="flex items-center justify-between">
                                        <span>{{ categoryName }}</span>
                                        <span class="rounded-full bg-gray-200 px-2 py-1 text-xs dark:bg-gray-700">
                                            {{ templates.length }}
                                        </span>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="flex-1 overflow-y-auto">
                        <div class="p-4">
                            <h4 class="mb-3 text-sm font-medium">
                                {{ activeCategoryTab }} ({{ templatesByCategory[activeCategoryTab]?.length || 0 }})
                            </h4>
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
                                <div
                                    v-for="template in templatesByCategory[activeCategoryTab]"
                                    :key="template.id"
                                    class="cursor-pointer rounded-lg border border-gray-200 p-3 transition-colors hover:border-primary hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                                    @click="selectTemplateFromBrowser(template)"
                                >
                                    <div class="mb-1 text-sm font-medium">{{ template.name }}</div>
                                    <div class="space-y-1 text-xs text-gray-500">
                                        <div v-if="template.manufacturer">
                                            <strong>{{ t('assets.manufacturer') }}:</strong> {{ template.manufacturer }}
                                        </div>
                                        <div v-if="template.model_name">
                                            <strong>{{ t('assets.model') }}:</strong> {{ template.model_name }}
                                        </div>
                                        <div>
                                            <strong>{{ t('assets.scope') }}:</strong>
                                            <span v-if="template.is_global" class="text-blue-600">
                                                {{ t('asset_templates.global') }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="flex h-full items-center justify-center">
                    <p class="text-sm text-gray-500">{{ t('assets.no_templates_available') }}</p>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>

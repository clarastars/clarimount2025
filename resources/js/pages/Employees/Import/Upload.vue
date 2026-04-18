<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Select } from '@/components/ui/select';
import Icon from '@/components/Icon.vue';
import Heading from '@/components/Heading.vue';
import { useI18n } from 'vue-i18n';
import { computed, ref, reactive } from 'vue';
import type { Company, BreadcrumbItem } from '@/types';

const { t } = useI18n();

interface Props {
    companies: Company[];
    currentCompany?: Company;
}

const props = defineProps<Props>();

const breadcrumbs = computed((): BreadcrumbItem[] => [
    {
        title: t('nav.dashboard'),
        href: '/dashboard',
    },
    {
        title: t('employees.title'),
        href: '/employees',
    },
    {
        title: t('employees.import_csv'),
        href: '/employees/import',
    },
    {
        title: t('employees.upload_file'),
        href: '/employees/import/upload',
    },
]);

const fileInput = ref<HTMLInputElement | null>(null);
const selectedFile = ref<File | null>(null);
const isDragging = ref(false);
const isUploading = ref(false);
const uploadProgress = ref(0);
const validationResult = ref<any>(null);
const validationErrors = ref<string[]>([]);

// Company selection state
const selectedCompanyId = ref<string>(props.currentCompany?.id?.toString() || props.companies[0]?.id?.toString() || '');

/** create = new rows (id optional for mixed updates); update = every row must be an existing employee id (partial update). */
const importMode = ref<'create' | 'update'>('create');

const selectedCompany = computed(() => {
    return props.companies.find(c => c.id.toString() === selectedCompanyId.value);
});

const state = reactive({
    step: 'upload', // 'upload', 'validating', 'results', 'importing', 'complete'
    importId: null as string | null,
    importSummary: null as any,
});

const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        selectedFile.value = target.files[0];
        resetValidation();
    }
};

const handleDrop = (event: DragEvent) => {
    event.preventDefault();
    isDragging.value = false;
    
    if (event.dataTransfer?.files && event.dataTransfer.files.length > 0) {
        selectedFile.value = event.dataTransfer.files[0];
        resetValidation();
    }
};

const handleDragOver = (event: DragEvent) => {
    event.preventDefault();
    isDragging.value = true;
};

const handleDragLeave = (event: DragEvent) => {
    event.preventDefault();
    isDragging.value = false;
};

const resetValidation = () => {
    validationResult.value = null;
    validationErrors.value = [];
    state.step = 'upload';
};

const validateFile = async () => {
    if (!selectedFile.value || !selectedCompanyId.value) return;
    
    state.step = 'validating';
    isUploading.value = true;
    uploadProgress.value = 0;
    
    // Simulate upload progress
    const progressInterval = setInterval(() => {
        uploadProgress.value = Math.min(uploadProgress.value + 10, 90);
    }, 100);
    
    try {
        const formData = new FormData();
        formData.append('file', selectedFile.value);
        formData.append('company_id', selectedCompanyId.value);
        formData.append('import_mode', importMode.value);
        
        const response = await fetch(route('employees.import.process'), {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        });
        
        clearInterval(progressInterval);
        uploadProgress.value = 100;
        
        const result = await response.json();
        
        if (result.success) {
            validationResult.value = result;
            state.importId = result.import_id;
            state.importSummary = result.summary;
            state.step = 'results';
        } else {
            validationErrors.value = result.errors || [result.message];
            state.step = 'upload';
        }
    } catch (error) {
        clearInterval(progressInterval);
        validationErrors.value = [t('employees.file_processing_error')];
        state.step = 'upload';
    } finally {
        isUploading.value = false;
    }
};

const executeImport = async () => {
    if (!state.importId) return;
    
    state.step = 'importing';
    
    try {
        const response = await fetch(route('employees.import.execute'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                import_id: state.importId,
            }),
        });
        
        const result = await response.json();
        
        if (result.success) {
            state.step = 'complete';
            state.importSummary = result.summary;
            
            // Redirect to employees index after a short delay
            setTimeout(() => {
                router.visit(route('employees.index'), {
                    preserveState: false,
                });
            }, 3000);
        } else {
            validationErrors.value = [result.message];
            state.step = 'results';
        }
    } catch (error) {
        validationErrors.value = [t('employees.import_execution_error')];
        state.step = 'results';
    }
};

const resetUpload = () => {
    selectedFile.value = null;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
    resetValidation();
};

const isValidFileType = (file: File): boolean => {
    const allowedTypes = ['text/csv', 'application/vnd.ms-excel', 'text/plain'];
    return allowedTypes.includes(file.type) || file.name.toLowerCase().endsWith('.csv');
};

const formatFileSize = (bytes: number): string => {
    if (bytes === 0) return t('employees.file_size_zero');
    const k = 1024;
    const sizes = [
        t('employees.file_size_bytes'),
        t('employees.file_size_kb'),
        t('employees.file_size_mb'),
        t('employees.file_size_gb')
    ];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};
</script>

<template>
    <Head :title="t('employees.upload_file')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6">
            <!-- Header -->
            <div>
                <Heading :title="t('employees.upload_file')" />
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    {{ t('employees.upload_csv_subtitle') }}
                </p>
            </div>

            <!-- Progress Steps -->
            <div class="flex items-center justify-center space-x-4">
                <div class="flex items-center">
                    <div class="flex items-center justify-center w-8 h-8 rounded-full bg-blue-600 text-white text-sm">
                        1
                    </div>
                    <span class="ml-2 text-sm font-medium">{{ t('employees.upload_file') }}</span>
                </div>
                <div class="w-8 h-px bg-gray-300"></div>
                <div class="flex items-center">
                    <div :class="['flex items-center justify-center w-8 h-8 rounded-full text-sm', 
                                 state.step === 'validating' || state.step === 'results' || state.step === 'importing' || state.step === 'complete' 
                                 ? 'bg-blue-600 text-white' : 'bg-gray-300 text-gray-600']">
                        2
                    </div>
                    <span class="ml-2 text-sm font-medium">{{ t('employees.validate_data') }}</span>
                </div>
                <div class="w-8 h-px bg-gray-300"></div>
                <div class="flex items-center">
                    <div :class="['flex items-center justify-center w-8 h-8 rounded-full text-sm',
                                 state.step === 'importing' || state.step === 'complete' 
                                 ? 'bg-blue-600 text-white' : 'bg-gray-300 text-gray-600']">
                        3
                    </div>
                    <span class="ml-2 text-sm font-medium">{{ t('employees.import_data') }}</span>
                </div>
            </div>

            <!-- Upload Step -->
            <div v-if="state.step === 'upload'">
                <!-- Company Selection -->
                <Card v-if="companies.length > 1">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Icon name="Building" class="h-5 w-5 text-blue-600" />
                            {{ t('employees.select_company') }}
                        </CardTitle>
                        <CardDescription>
                            {{ t('employees.select_company_import_description') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="max-w-sm">
                            <select 
                                v-model="selectedCompanyId"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <option value="">{{ t('employees.select_company_placeholder') }}</option>
                                <option 
                                    v-for="company in companies" 
                                    :key="company.id" 
                                    :value="company.id.toString()"
                                >
                                    {{ company.name_en }}
                                    <span v-if="company.name_ar">
                                        ({{ company.name_ar }})
                                    </span>
                                </option>
                            </select>
                        </div>
                        <div v-if="selectedCompany" class="mt-3 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                            <div class="flex items-center gap-2">
                                <Icon name="Building" class="h-4 w-4 text-blue-600" />
                                <div class="text-sm">
                                    <span class="font-medium">{{ t('employees.selected_company') }}:</span>
                                    {{ selectedCompany.name_en }}
                                    <span v-if="selectedCompany.name_ar" class="text-gray-600 ml-1">
                                        ({{ selectedCompany.name_ar }})
                                    </span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Single Company Display -->
                <Card v-else-if="companies.length === 1">
                    <CardContent class="pt-6">
                        <div class="flex items-center gap-2 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                            <Icon name="Building" class="h-4 w-4 text-blue-600" />
                            <div class="text-sm">
                                <span class="font-medium">{{ t('employees.importing_to_company') }}:</span>
                                {{ companies[0].name_en }}
                                <span v-if="companies[0].name_ar" class="text-gray-600 ml-1">
                                    ({{ companies[0].name_ar }})
                                </span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Validation Errors -->
                <Card v-if="validationErrors.length > 0" class="border-red-200 bg-red-50 dark:bg-red-900/20">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-red-800 dark:text-red-200">
                            <Icon name="AlertCircle" class="h-5 w-5" />
                            {{ t('employees.validation_errors') }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul class="space-y-1">
                            <li v-for="error in validationErrors" :key="error" class="text-sm text-red-700 dark:text-red-200">
                                • {{ error }}
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <!-- Import mode -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Icon name="Settings" class="h-5 w-5 text-blue-600" />
                            {{ t('employees.import_mode_label') }}
                        </CardTitle>
                        <CardDescription>
                            {{ t('employees.import_mode_description') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <label class="flex items-start gap-3 cursor-pointer rounded-lg border border-transparent p-2 hover:bg-muted/50">
                            <input
                                v-model="importMode"
                                type="radio"
                                value="create"
                                class="mt-1"
                            />
                            <div>
                                <div class="font-medium text-sm">{{ t('employees.import_mode_create') }}</div>
                                <p class="text-xs text-gray-600 dark:text-gray-400 mt-0.5">
                                    {{ t('employees.import_mode_create_hint') }}
                                </p>
                            </div>
                        </label>
                        <label class="flex items-start gap-3 cursor-pointer rounded-lg border border-transparent p-2 hover:bg-muted/50">
                            <input
                                v-model="importMode"
                                type="radio"
                                value="update"
                                class="mt-1"
                            />
                            <div>
                                <div class="font-medium text-sm">{{ t('employees.import_mode_update') }}</div>
                                <p class="text-xs text-gray-600 dark:text-gray-400 mt-0.5">
                                    {{ t('employees.import_mode_update_hint') }}
                                </p>
                            </div>
                        </label>
                    </CardContent>
                </Card>

                <!-- File Upload -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Icon name="Upload" class="h-5 w-5 text-blue-600" />
                            {{ t('employees.upload_csv_file') }}
                        </CardTitle>
                        <CardDescription>
                            {{ t('employees.upload_csv_description') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div 
                            @drop="handleDrop"
                            @dragover="handleDragOver"
                            @dragleave="handleDragLeave"
                            :class="[
                                'border-2 border-dashed rounded-lg p-8 text-center transition-colors cursor-pointer',
                                isDragging 
                                    ? 'border-blue-400 bg-blue-50 dark:bg-blue-900/20' 
                                    : 'border-gray-300 hover:border-gray-400'
                            ]"
                            @click="fileInput?.click()"
                        >
                            <input 
                                ref="fileInput"
                                type="file"
                                accept=".csv"
                                @change="handleFileSelect"
                                class="hidden"
                            />
                            
                            <div v-if="!selectedFile" class="space-y-4">
                                <Icon name="Upload" class="mx-auto h-12 w-12 text-gray-400" />
                                <div>
                                    <p class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                        {{ t('employees.drop_csv_file') }}
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ t('employees.or_click_to_browse') }}
                                    </p>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ t('employees.max_file_size') }}: 10{{ t('employees.file_size_mb') }}
                                </div>
                            </div>
                            
                            <div v-else class="space-y-4">
                                <Icon name="FileSpreadsheet" class="mx-auto h-12 w-12 text-green-600" />
                                <div>
                                    <p class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                        {{ selectedFile.name }}
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ formatFileSize(selectedFile.size) }}
                                    </p>
                                </div>
                                <div class="flex gap-2 justify-center">
                                    <Button @click="resetUpload" variant="outline" size="sm">
                                        <Icon name="X" class="mr-2 h-4 w-4" />
                                        {{ t('common.remove') }}
                                    </Button>
                                    <Button @click="fileInput?.click()" variant="outline" size="sm">
                                        <Icon name="RefreshCw" class="mr-2 h-4 w-4" />
                                        {{ t('common.change') }}
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- File Info -->
                <Card v-if="selectedFile && !isValidFileType(selectedFile)" class="border-red-200 bg-red-50 dark:bg-red-900/20">
                    <CardContent class="pt-6">
                        <div class="flex items-center gap-2 text-red-700 dark:text-red-200">
                            <Icon name="AlertCircle" class="h-5 w-5" />
                            <p class="font-medium">{{ t('employees.invalid_file_type') }}</p>
                        </div>
                        <p class="text-sm text-red-600 dark:text-red-300 mt-1">
                            {{ t('employees.csv_file_required') }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Validation Step -->
            <div v-if="state.step === 'validating'">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Icon name="Loader2" class="h-5 w-5 animate-spin text-blue-600" />
                            {{ t('employees.validating_file') }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-4">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div 
                                    class="bg-blue-600 h-2 rounded-full transition-all duration-300"
                                    :style="{ width: `${uploadProgress}%` }"
                                ></div>
                            </div>
                            <p class="text-sm text-gray-600 dark:text-gray-400 text-center">
                                {{ t('employees.processing_csv_file') }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Results Step -->
            <div v-if="state.step === 'results'">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Icon name="CheckCircle" class="h-5 w-5 text-green-600" />
                            {{ t('employees.validation_complete') }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
                                    <div class="text-2xl font-bold text-blue-600">{{ state.importSummary?.total_records || 0 }}</div>
                                    <div class="text-sm text-blue-800 dark:text-blue-200">{{ t('employees.total_records') }}</div>
                                </div>
                                <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg">
                                    <div class="text-2xl font-bold text-green-600">{{ state.importSummary?.new_records || 0 }}</div>
                                    <div class="text-sm text-green-800 dark:text-green-200">{{ t('employees.new_records') }}</div>
                                </div>
                                <div class="bg-orange-50 dark:bg-orange-900/20 p-4 rounded-lg">
                                    <div class="text-2xl font-bold text-orange-600">{{ state.importSummary?.update_records || 0 }}</div>
                                    <div class="text-sm text-orange-800 dark:text-orange-200">{{ t('employees.update_records') }}</div>
                                </div>
                            </div>
                            
                            <div class="border border-green-200 bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
                                <div class="flex items-start gap-2">
                                    <Icon name="CheckCircle" class="h-4 w-4 text-green-600 mt-0.5" />
                                    <p class="text-green-800 dark:text-green-200">
                                        {{ t('employees.validation_success_message') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Importing Step -->
            <div v-if="state.step === 'importing'">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Icon name="Loader2" class="h-5 w-5 animate-spin text-blue-600" />
                            {{ t('employees.importing_data') }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-4">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full animate-pulse"></div>
                            </div>
                            <p class="text-sm text-gray-600 dark:text-gray-400 text-center">
                                {{ t('employees.importing_employees') }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Complete Step -->
            <div v-if="state.step === 'complete'">
                <Card class="border-green-200 bg-green-50 dark:bg-green-900/20">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-green-800 dark:text-green-200">
                            <Icon name="CheckCircle" class="h-5 w-5" />
                            {{ t('employees.import_complete') }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="bg-green-100 dark:bg-green-800/20 p-4 rounded-lg">
                                    <div class="text-2xl font-bold text-green-600">{{ state.importSummary?.created || 0 }}</div>
                                    <div class="text-sm text-green-800 dark:text-green-200">{{ t('employees.created_employees') }}</div>
                                </div>
                                <div class="bg-blue-100 dark:bg-blue-800/20 p-4 rounded-lg">
                                    <div class="text-2xl font-bold text-blue-600">{{ state.importSummary?.updated || 0 }}</div>
                                    <div class="text-sm text-blue-800 dark:text-blue-200">{{ t('employees.updated_employees') }}</div>
                                </div>
                            </div>
                            
                            <p class="text-sm text-green-800 dark:text-green-200">
                                {{ t('employees.import_complete_message') }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Action Buttons -->
            <div class="flex justify-between">
                <Button variant="ghost" asChild>
                    <Link :href="route('employees.import')">
                        <Icon name="ArrowLeft" class="mr-2 rtl:mr-0 rtl:ml-2 h-4 w-4" />
                        {{ t('common.back') }}
                    </Link>
                </Button>
                
                <div class="flex gap-2">
                    <Button 
                        v-if="state.step === 'upload'" 
                        @click="validateFile" 
                        :disabled="!selectedFile || !isValidFileType(selectedFile) || !selectedCompanyId || isUploading"
                    >
                        <Icon name="Upload" class="mr-2 rtl:mr-0 rtl:ml-2 h-4 w-4" />
                        {{ t('employees.validate_file') }}
                    </Button>
                    
                    <Button 
                        v-if="state.step === 'results'" 
                        @click="executeImport"
                        class="bg-green-600 hover:bg-green-700"
                    >
                        <Icon name="Database" class="mr-2 rtl:mr-0 rtl:ml-2 h-4 w-4" />
                        {{ t('employees.start_import') }}
                    </Button>
                    
                    <Button 
                        v-if="state.step === 'complete'" 
                        asChild
                        class="bg-blue-600 hover:bg-blue-700"
                    >
                        <Link :href="route('employees.index')">
                            <Icon name="Users" class="mr-2 rtl:mr-0 rtl:ml-2 h-4 w-4" />
                            {{ t('employees.view_employees') }}
                        </Link>
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template> 
<template>
    <div>
        <Head :title="t('custody.document_title')" />

        <div class="container mx-auto py-8 print:py-0">
            <!-- Print Controls -->
            <div class="flex justify-between items-center mb-6 print:hidden">
                <div>
                    <h1 class="text-2xl font-bold">{{ t('custody.document_title') }}</h1>
                    <p class="text-muted-foreground">{{ t('custody.document_id') }}: {{ custodyChange.id }}</p>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" @click="goBack">
                        <Icon name="ArrowLeft" class="mr-2 h-4 w-4" />
                        {{ t('custody.back') }}
                    </Button>
                    <Button @click="printDocument">
                        <Icon name="Printer" class="mr-2 h-4 w-4" />
                        {{ t('custody.print') }}
                    </Button>
                </div>
            </div>

            <!-- Document Content -->
            <div class="bg-white shadow-lg print:shadow-none">
                <div class="p-8 print:p-4">
                    <!-- Company Info -->
                    <div class="text-center mb-6">
                        <h2>{{ companyName }}</h2>
                    </div>

                    <!-- Header -->
                    <div class="text-center border-b-2 border-gray-800 pb-4 mb-6">
                        <h1 class="text-2xl font-bold mb-2">نموذج استلام عهدة</h1>
                        <p><strong>{{ t('custody.document_id') }}:</strong> {{ custodyChange.id.toString().padStart(4, '0') }}</p>
                        <p><strong>{{ t('custody.date') }}:</strong> <span dir="ltr">{{ new Date(custodyChange.created_at).toLocaleDateString() }}</span></p>
                        <Badge :class="getStatusBadgeClass(custodyChange.status)" class="mt-2">
                            {{ t('custody.status_' + custodyChange.status) }}
                        </Badge>
                    </div>
                    
                    <!-- Employee Info -->
                    <div class="grid grid-cols-2 gap-8 mb-6">
                        <div>
                            <div class="mb-2">
                                <span class="font-semibold text-gray-600">{{ t('custody.employee_name') }}:</span> {{ employee.full_name }}
                            </div>
                            <div class="mb-2">
                                <span class="font-semibold text-gray-600">{{ t('custody.employee_id') }}:</span> {{ employee.employee_id }}
                            </div>
                            <div class="mb-2">
                                <span class="font-semibold text-gray-600">{{ t('custody.email') }}:</span> {{ employee.work_email || employee.personal_email }}
                            </div>
                        </div>
                        <div>
                            <div class="mb-2">
                                <span class="font-semibold text-gray-600">{{ t('custody.department') }}:</span> {{ departmentName }}
                            </div>
                            <div class="mb-2">
                                <span class="font-semibold text-gray-600">{{ t('custody.job_title') }}:</span> {{ employee.job_title || t('custody.na') }}
                            </div>
                            <div class="mb-2">
                                <span class="font-semibold text-gray-600">{{ t('custody.updated_by') }}:</span> {{ custodyChange.updatedBy?.name || 'System' }}
                            </div>
                        </div>
                    </div>

                    <!-- Changes Summary -->
                    <div v-if="custodyChange.changes_summary" class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
                        <strong>{{ t('custody.change_summary') }}:</strong> {{ custodyChange.changes_summary }}
                    </div>
                    
                    <!-- Previous Custody -->
                    <div class="mb-8">
                        <h2 class="text-lg font-bold mb-4">{{ t('custody.previous_custody') }} ({{ previousAssets.length }} {{ t('custody.items') }})</h2>
                        <div v-if="previousAssets.length > 0" class="overflow-x-auto">
                            <table class="custody-document-table w-full border-collapse border border-gray-300 text-sm text-center">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="border border-gray-300 px-3 py-2">{{ t('custody.asset_tag') }}</th>
                                        <th class="border border-gray-300 px-3 py-2">{{ t('custody.description') }}</th>
                                        <th class="border border-gray-300 px-3 py-2">{{ t('custody.model_name') }}</th>
                                        <th class="border border-gray-300 px-3 py-2">{{ t('custody.serial_number') }}</th>
                                        <th class="border border-gray-300 px-3 py-2">{{ t('custody.category') }}</th>
                                        <th class="border border-gray-300 px-3 py-2">{{ t('custody.location') }}</th>
                                        <th class="border border-gray-300 px-3 py-2">{{ t('custody.condition') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="asset in previousAssets" :key="asset.id">
                                        <td class="border border-gray-300 px-3 py-2">{{ asset.asset_tag }}</td>
                                        <td class="border border-gray-300 px-3 py-2">{{ asset.model_name }}{{ asset.asset_template?.name ? ` - ${asset.asset_template.name}` : '' }}</td>
                                        <td class="border border-gray-300 px-3 py-2">{{ asset.model_number || t('custody.na') }}</td>
                                        <td class="border border-gray-300 px-3 py-2">{{ asset.serial_number || t('custody.na') }}</td>
                                        <td class="border border-gray-300 px-3 py-2">{{ asset.category?.name || t('custody.na') }}</td>
                                        <td class="border border-gray-300 px-3 py-2">{{ asset.location?.name || t('custody.na') }}</td>
                                        <td class="border border-gray-300 px-3 py-2">{{ formatAssetCondition(asset.condition) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="text-center py-8 text-gray-500 italic">
                            {{ t('custody.no_previous_assets') }}
                        </div>
                    </div>
                    
                    <!-- New Custody -->
                    <div class="mb-8">
                        <h2 class="text-lg font-bold mb-4">{{ t('custody.new_custody') }} ({{ newAssets.length }} {{ t('custody.items') }})</h2>
                        <div v-if="newAssets.length > 0" class="overflow-x-auto">
                            <table class="custody-document-table w-full border-collapse border border-gray-300 text-sm text-center">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="border border-gray-300 px-3 py-2">{{ t('custody.asset_tag') }}</th>
                                        <th class="border border-gray-300 px-3 py-2">{{ t('custody.description') }}</th>
                                        <th class="border border-gray-300 px-3 py-2">{{ t('custody.model_name') }}</th>
                                        <th class="border border-gray-300 px-3 py-2">{{ t('custody.serial_number') }}</th>
                                        <th class="border border-gray-300 px-3 py-2">{{ t('custody.category') }}</th>
                                        <th class="border border-gray-300 px-3 py-2">{{ t('custody.location') }}</th>
                                        <th class="border border-gray-300 px-3 py-2">{{ t('custody.condition') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="asset in newAssets" :key="asset.id">
                                        <td class="border border-gray-300 px-3 py-2">{{ asset.asset_tag }}</td>
                                        <td class="border border-gray-300 px-3 py-2">{{ asset.model_name }}{{ asset.asset_template?.name ? ` - ${asset.asset_template.name}` : '' }}</td>
                                        <td class="border border-gray-300 px-3 py-2">{{ asset.model_number || t('custody.na') }}</td>
                                        <td class="border border-gray-300 px-3 py-2">{{ asset.serial_number || t('custody.na') }}</td>
                                        <td class="border border-gray-300 px-3 py-2">{{ asset.category?.name || t('custody.na') }}</td>
                                        <td class="border border-gray-300 px-3 py-2">{{ asset.location?.name || t('custody.na') }}</td>
                                        <td class="border border-gray-300 px-3 py-2">{{ formatAssetCondition(asset.condition) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="text-center py-8 text-gray-500 italic">
                            {{ t('custody.no_new_assets') }}
                        </div>
                    </div>

                    <!-- Terms and Conditions -->
                    <div class="mb-8">
                        <h3 class="text-lg font-bold mb-4">{{ t('custody.terms_and_conditions') }}</h3>
                        <ul class="text-sm space-y-2 pl-6">
                            <li class="list-disc">{{ t('custody.term_1') }}</li>
                            <li class="list-disc">{{ t('custody.term_2') }}</li>
                            <li class="list-disc">{{ t('custody.term_3') }}</li>
                            <li class="list-disc">{{ t('custody.term_4') }}</li>
                            <li class="list-disc">{{ t('custody.term_5') }}</li>
                        </ul>
                    </div>
                    
                    <!-- Signatures -->
                    <div class="print:mt-12">
                        <h3 class="text-lg font-bold mb-6">{{ t('custody.signatures') }}</h3>
                        <div class="grid grid-cols-2 gap-8">
                            <div>
                                <div class="border border-gray-800 h-20 relative mb-4">
                                    <div class="absolute bottom-1 left-2 text-xs text-gray-600">{{ t('custody.employee_signature') }}</div>
                                </div>
                                <div class="text-center">
                                    <strong>{{ employee.full_name }}</strong><br>
                                    <small>{{ employee.job_title || t('custody.employee_default') }}</small><br>
                                    <small>{{ t('custody.date_label') }} _______________________</small>
                                </div>
                            </div>
                            <div>
                                <div class="border border-gray-800 h-20 relative mb-4">
                                    <div class="absolute bottom-1 left-2 text-xs text-gray-600">{{ t('custody.admin_manager_signature') }}</div>
                                </div>
                                <div class="text-center">
                                    <strong>{{ custodyChange.updatedBy?.name || '____________________' }}</strong><br>
                                    <small>{{ t('custody.it_administrator') }}</small><br>
                                    <small>{{ t('custody.date_label') }} _______________________</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="mt-8 text-center text-xs text-gray-500 print:mt-12">
                        <p v-html="t('custody.document_generated_on', { date: `<span dir='ltr'>${new Date(generatedAt).toLocaleDateString()}</span>` })"></p>
                        <p>{{ t('custody.document_id') }}: {{ custodyChange.id.toString().padStart(4, '0') }} | {{ t('custody.status') }}: {{ t('custody.status_' + custodyChange.status) }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import Icon from '@/components/Icon.vue';
import { useI18n } from 'vue-i18n';
import { onMounted, computed } from 'vue';
import type { Employee, CustodyChange, Department } from '@/types';

interface Props {
    custodyChange: CustodyChange;
    employee: Employee;
    previousAssets: any[];
    newAssets: any[];
    generatedAt: string;
    locale?: string;
}

const props = defineProps<Props>();
const { locale, t } = useI18n();

// Computed property to get department name
const departmentName = computed(() => {
    if (!props.employee.department) {
        return t('custody.na');
    }
    
    // If department is an object (relationship loaded), use its name
    if (typeof props.employee.department === 'object' && props.employee.department.name) {
        return props.employee.department.name;
    }
    
    // If department is a string, use it directly
    if (typeof props.employee.department === 'string') {
        return props.employee.department;
    }
    
    return t('custody.na');
});

const companyName = computed(() => {
    if (!props.employee.company) {
        return t('custody.na');
    }

    if (typeof props.employee.company === 'object') {
        return locale.value === 'ar'
            ? (props.employee.company.name_ar || props.employee.company.name_en || t('custody.na'))
            : (props.employee.company.name_en || props.employee.company.name_ar || t('custody.na'));
    }

    if (typeof props.employee.company === 'string') {
        return props.employee.company;
    }

    return t('custody.na');
});

// Set locale to Arabic when component mounts
onMounted(() => {
    if (props.locale) {
        locale.value = props.locale;
    }
});

const formatAssetCondition = (condition?: string): string => {
    if (!condition) {
        return t('custody.na');
    }

    if (condition === 'good') {
        return t('custody.condition_good');
    }

    if (condition === 'damaged') {
        return t('custody.condition_damaged');
    }

    return condition;
};

const getStatusBadgeClass = (status: string) => {
    switch (status) {
        case 'pending':
            return 'bg-yellow-100 text-yellow-800';
        case 'approved':
            return 'bg-blue-100 text-blue-800';
        case 'signed':
            return 'bg-purple-100 text-purple-800';
        case 'completed':
            return 'bg-green-100 text-green-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

const printDocument = () => {
    window.print();
};

const goBack = () => {
    window.history.back();
};
</script>

<style>
@media print {
    @page {
        margin: 0.5in;
    }
    
    body {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
        font-size: 12px;
    }
    
    /* Make all text smaller in print */
    .text-2xl { font-size: 16px !important; }
    .text-xl { font-size: 14px !important; }
    .text-lg { font-size: 13px !important; }
    .text-base { font-size: 11px !important; }
    .text-sm { font-size: 10px !important; }
    .text-xs { font-size: 8px !important; }
    
    /* Table text */
    .custody-document-table { font-size: 9px !important; }
    .custody-document-table th,
    .custody-document-table td {
        font-size: 9px !important;
        padding: 2px 4px !important;
        text-align: center !important;
    }
    
    /* Terms and conditions */
    ul li { font-size: 10px !important; }
    
    /* Signature sections */
    .text-center small { font-size: 9px !important; }
    
    /* Reduce spacing for print */
    .mb-8 { margin-bottom: 1rem !important; }
    .mb-6 { margin-bottom: 0.75rem !important; }
    .mb-4 { margin-bottom: 0.5rem !important; }
    
    /* Company header */
    h2 { font-size: 14px !important; }
    
    /* Make signature boxes smaller */
    .h-20 { height: 60px !important; }
}
</style> 
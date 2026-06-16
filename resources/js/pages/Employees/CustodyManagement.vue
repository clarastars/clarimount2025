<template>
    <AppLayout>
        <div class="container mx-auto py-8">
            <div class="space-y-6">
                <div class="space-y-2">
                    <Breadcrumbs :breadcrumbs="breadcrumbs" />
                    <div class="flex items-center justify-between">
                        <div class="space-y-1">
                            <Heading :title="`${t('custody.update_custody')} - ${employee.full_name}`" />
                            <div class="flex items-center gap-2">
                                <Badge :class="getStatusBadgeClass(employee.employment_status)">
                                    {{ t(`employees.status_${employee.employment_status}`) }}
                                </Badge>
                                <span class="text-sm font-mono text-muted-foreground">{{ employee.employee_id }}</span>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <Button variant="outline" @click="resetChanges" :disabled="loading || !hasChanges">
                                {{ t('custody.reset_changes') }}
                            </Button>
                            <Button 
                                @click="saveCustodyUpdate" 
                                :disabled="loading || !hasChanges"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-semibold"
                            >
                                <Icon name="Save" class="mr-2 h-4 w-4" />
                                {{ loading ? t('custody.saving') : t('custody.save_custody_update') }}
                            </Button>
                            <Button variant="outline" asChild>
                                <Link :href="route('employees.show', employee.id)">
                                    <Icon name="ArrowLeft" class="mr-2 h-4 w-4" />
                                    {{ t('custody.back_to_employee') }}
                                </Link>
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Employee Custody -->
                <Card>
                    <CardHeader>
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="space-y-1">
                                <CardTitle class="flex flex-wrap items-center gap-2">
                                    <Icon name="Package" class="h-5 w-5 text-blue-600" />
                                    {{ t('custody.employee_custody') }} ({{ updatedAssets.length }})
                                    <Badge
                                        v-if="hasChanges"
                                        variant="outline"
                                        class="border-amber-300 bg-amber-50 text-amber-800"
                                    >
                                        {{ t('custody.unsaved_changes') }}
                                    </Badge>
                                </CardTitle>
                                <p v-if="hasChanges && addedAssetsCount > 0" class="text-sm text-muted-foreground">
                                    {{ t('custody.assets_to_add_on_save', { count: addedAssetsCount }) }}
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <Button
                                    variant="outline"
                                    @click="showAssetSearch = true"
                                    :disabled="loading"
                                >
                                    <Icon name="Plus" class="mr-2 h-4 w-4 rtl:mr-0 rtl:ml-2" />
                                    {{ t('custody.add_asset') }}
                                </Button>
                                <Button
                                    variant="default"
                                    class="bg-blue-600 hover:bg-blue-700 text-white font-semibold"
                                    @click="openQuickCreateDialog"
                                    :disabled="loading"
                                >
                                    <Icon name="PackagePlus" class="mr-2 h-4 w-4 rtl:mr-0 rtl:ml-2" />
                                    {{ t('custody.create_and_assign_asset') }}
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div v-if="updatedAssets.length > 0" class="overflow-hidden rounded-lg border">
                            <div
                                class="hidden grid-cols-[1fr_1.2fr_1fr_1fr_auto] gap-3 border-b bg-muted/40 px-4 py-2 text-xs font-medium text-muted-foreground sm:grid"
                            >
                                <span>{{ t('assets.asset_tag') }}</span>
                                <span>{{ t('custody.asset_name') }}</span>
                                <span>{{ t('assets.serial_number') }}</span>
                                <span>{{ t('assets.category') }}</span>
                                <span class="w-10 text-center">{{ t('common.actions') }}</span>
                            </div>
                            <div
                                v-for="asset in updatedAssets"
                                :key="asset.id"
                                class="flex items-center justify-between gap-3 border-b px-4 py-3 last:border-b-0"
                                :class="isNewAsset(asset) ? 'bg-green-50/60 dark:bg-green-950/20' : ''"
                            >
                                <div class="min-w-0 flex-1 sm:grid sm:grid-cols-[1fr_1.2fr_1fr_1fr] sm:gap-3">
                                    <div class="flex items-center gap-2">
                                        <p class="font-mono font-medium">{{ asset.asset_tag }}</p>
                                        <Badge
                                            v-if="isNewAsset(asset)"
                                            class="bg-green-100 text-green-800 hover:bg-green-100"
                                        >
                                            {{ t('custody.new_asset_badge') }}
                                        </Badge>
                                    </div>
                                    <p class="truncate text-sm">
                                        {{ getAssetDisplayName(asset) }}
                                    </p>
                                    <p class="truncate font-mono text-sm text-muted-foreground">
                                        {{ asset.serial_number || '—' }}
                                    </p>
                                    <p class="truncate text-sm text-muted-foreground">
                                        {{ asset.assetCategory?.name || '—' }}
                                        <span v-if="asset.location?.name"> · {{ asset.location.name }}</span>
                                    </p>
                                </div>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="shrink-0 text-red-600 hover:bg-red-50 hover:text-red-700"
                                    :title="t('custody.remove_asset')"
                                    @click="removeAsset(asset)"
                                    :disabled="loading"
                                >
                                    <Icon name="Trash2" class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>

                        <div
                            v-else-if="pendingRemovedAssets.length === 0"
                            class="rounded-lg border border-dashed py-12 text-center text-muted-foreground"
                        >
                            <Icon name="Package" class="mx-auto mb-3 h-10 w-10 opacity-40" />
                            <p>{{ t('custody.no_assets_assigned') }}</p>
                            <p class="mt-1 text-sm">{{ t('custody.add_assets_hint') }}</p>
                        </div>

                        <div
                            v-if="pendingRemovedAssets.length > 0"
                            class="rounded-lg border border-red-200 bg-red-50/60 p-4 dark:border-red-900 dark:bg-red-950/20"
                        >
                            <p class="mb-3 text-sm font-medium text-red-800 dark:text-red-300">
                                {{ t('custody.assets_pending_removal', { count: pendingRemovedAssets.length }) }}
                            </p>
                            <div class="space-y-2">
                                <div
                                    v-for="asset in pendingRemovedAssets"
                                    :key="asset.id"
                                    class="flex items-center justify-between gap-3 rounded-md border border-red-100 bg-white/80 px-3 py-2 text-sm dark:border-red-900 dark:bg-background/50"
                                >
                                    <div class="min-w-0">
                                        <p class="font-mono font-medium">{{ asset.asset_tag }}</p>
                                        <p class="truncate text-muted-foreground">{{ getAssetDisplayName(asset) }}</p>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        class="shrink-0"
                                        @click="restoreAsset(asset)"
                                    >
                                        <Icon name="Undo2" class="mr-1 h-4 w-4 rtl:mr-0 rtl:ml-1" />
                                        {{ t('custody.undo_remove') }}
                                    </Button>
                                </div>
                            </div>
                            <p class="mt-3 text-xs text-muted-foreground">
                                {{ t('custody.removal_applies_on_save') }}
                            </p>
                        </div>

                        <div v-if="hasChanges" class="rounded-lg border bg-muted/30 p-4">
                            <Label for="changes_summary" class="mb-2">{{ t('custody.summary_optional') }}</Label>
                            <textarea
                                id="changes_summary"
                                v-model="changesSummary"
                                rows="2"
                                :placeholder="t('custody.summary_placeholder')"
                                class="flex min-h-[60px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            ></textarea>
                        </div>
                    </CardContent>
                </Card>
                
                <!-- Pending Custody Changes - Need Documents -->
                <Card v-if="pendingCustodyChanges.length > 0">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Icon name="AlertCircle" class="h-5 w-5 text-amber-600" />
                            {{ t('custody.pending_custody_changes') }}
                        </CardTitle>
                        <p class="text-sm text-muted-foreground">
                            {{ t('custody.pending_custody_changes_description') }}
                        </p>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <div 
                                v-for="change in pendingCustodyChanges" 
                                :key="change.id"
                                class="flex items-center justify-between p-3 border rounded-lg bg-amber-50 border-amber-200"
                            >
                                <div class="flex-1">
                                    <p class="font-medium">{{ change.changes_summary || t('custody.custody_updated') }}</p>
                                    <p class="text-sm text-muted-foreground">
                                        {{ new Date(change.created_at).toLocaleDateString() }} by {{ change.updatedBy?.name }}
                                    </p>
                                    <p class="text-xs text-amber-600 mt-1">
                                        <Icon name="Clock" class="h-3 w-3 inline mr-1" />
                                        {{ t('custody.waiting_for_document') }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Badge class="bg-amber-100 text-amber-800">
                                        {{ t('custody.status_' + change.status) }}
                                    </Badge>
                                    <Button variant="outline" size="sm" @click="viewCustodyDocument(change)">
                                        <Icon name="FileText" class="h-4 w-4" />
                                    </Button>
                                    <Button variant="default" size="sm" @click="uploadDocumentForChange(change)">
                                        <Icon name="Upload" class="h-4 w-4 mr-1" />
                                        {{ t('custody.upload_document_button') }}
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                
                <!-- Documented Custodies -->
                <Card v-if="otherCustodyChanges.length > 0">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Icon name="History" class="h-5 w-5 text-gray-600" />
                            {{ t('custody.documented_custodies') }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <div 
                                v-for="change in otherCustodyChanges" 
                                :key="change.id"
                                class="flex items-center justify-between p-3 border rounded-lg"
                            >
                                <div>
                                    <p class="font-medium">{{ change.changes_summary || t('custody.custody_updated') }}</p>
                                    <p class="text-sm text-muted-foreground">
                                        {{ new Date(change.created_at).toLocaleDateString() }} by {{ change.updatedBy?.name }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Badge :class="getStatusBadgeClass(change.status)">
                                        {{ t('custody.status_' + change.status) }}
                                    </Badge>
                                    <Button variant="outline" size="sm" @click="printCustodyDocument(change)" :title="t('custody.print_document')">
                                        <Icon name="Printer" class="h-4 w-4" />
                                    </Button>
                                    <Button variant="outline" size="sm" @click="viewCustodyDocument(change)" :title="t('custody.view_document')">
                                        <Icon name="FileText" class="h-4 w-4" />
                                    </Button>
                                    <Button v-if="change.status === 'pending'" variant="default" size="sm" @click="uploadDocumentForChange(change)">
                                        <Icon name="Upload" class="h-4 w-4 mr-1" />
                                        {{ t('custody.upload_document_button') }}
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                
            </div>
        </div>
        
        <!-- Asset Search Dialog -->
        <Dialog v-model:open="showAssetSearch">
            <DialogContent class="max-w-4xl">
                <DialogHeader>
                    <DialogTitle>{{ t('custody.select_assets_to_add') }}</DialogTitle>
                </DialogHeader>
                
                <div class="space-y-4">
                    <div>
                        <Input 
                            v-model="assetSearchQuery"
                            :placeholder="t('custody.search_assets_placeholder')"
                            @input="searchAssets"
                        />
                    </div>
                    
                    <div class="max-h-96 overflow-y-auto">
                        <div class="space-y-2">
                            <div 
                                v-for="asset in searchResults" 
                                :key="asset.id"
                                class="flex items-center justify-between p-3 border rounded-lg hover:bg-gray-50 cursor-pointer"
                                @click="toggleAssetSelection(asset)"
                            >
                                <div class="flex items-center gap-3">
                                    <Checkbox 
                                        :checked="selectedAssetIds.has(asset.id)"
                                        @update:checked="toggleAssetSelection(asset)"
                                    />
                                    <div class="h-8 w-8 rounded-full bg-gray-100 flex items-center justify-center">
                                        <Icon name="Package" class="h-4 w-4" />
                                    </div>
                                    <div>
                                        <p class="font-medium" v-if="asset.model_name && asset.model_name !== asset.asset_tag">{{ asset.model_name }}</p>
                                        <p class="font-medium" v-else>{{ asset.asset_tag }}</p>
                                        <p class="text-sm text-muted-foreground" v-if="asset.model_name && asset.model_name !== asset.asset_tag">{{ asset.asset_tag }}</p>
                                        <p class="text-xs text-muted-foreground">{{ asset.assetCategory?.name }} - {{ asset.location?.name }}</p>
                                        <p class="text-xs text-muted-foreground" v-if="asset.serial_number">{{ t('assets.serial_number') }}: {{ asset.serial_number }}</p>
                                        <p class="text-xs text-muted-foreground" v-if="asset.asset_template?.name">{{ t('assets.template') }}: {{ asset.asset_template.name }}</p>
                                    </div>
                                </div>
                                <Badge variant="outline">{{ t(`assets.status_${asset.status}`) }}</Badge>
                            </div>
                            
                            <div v-if="searchResults.length === 0 && assetSearchQuery" class="text-center py-8 text-muted-foreground">
                                {{ t('custody.no_available_assets_found') }}
                            </div>
                        </div>
                    </div>
                </div>
                
                <DialogFooter>
                    <Button variant="outline" @click="showAssetSearch = false">{{ t('common.cancel') }}</Button>
                    <Button 
                        @click="addSelectedAssets" 
                        :disabled="selectedAssetIds.size === 0"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-semibold"
                    >
                        {{ t('custody.add_selected_assets', { count: selectedAssetIds.size }) }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
        
        <!-- Quick Create Asset Dialog -->
        <Dialog v-model:open="showQuickCreate">
            <DialogContent class="max-w-3xl">
                <DialogHeader>
                    <DialogTitle>{{ t('custody.create_and_assign_asset') }}</DialogTitle>
                </DialogHeader>

                <p class="text-sm text-muted-foreground">
                    {{ t('custody.create_and_assign_asset_description') }}
                </p>

                <div class="space-y-4">
                    <AssetTemplatePicker
                        v-model="selectedTemplate"
                        :categories="assetCategories"
                        :companies="companies"
                        :default-company-id="employee.company_id"
                        search-url="/api/custody/asset-templates/search"
                        by-category-url="/api/custody/asset-templates/by-category"
                        store-url="/api/custody/asset-templates"
                        input-id="custody-template-search"
                    />
                    <p v-if="selectedTemplate" class="text-sm text-green-700 dark:text-green-400">
                        {{ t('custody.selected_template') }}: {{ selectedTemplate.display_name || selectedTemplate.name }}
                    </p>

                    <div class="space-y-2">
                        <Label for="quick-location">{{ t('custody.select_location') }}</Label>
                        <select
                            id="quick-location"
                            v-model="quickCreateForm.location_id"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            :disabled="locations.length === 0"
                        >
                            <option value="">{{ t('custody.select_location_placeholder') }}</option>
                            <optgroup
                                v-for="group in locationsByCompany"
                                :key="group.companyId"
                                :label="group.companyName"
                            >
                                <option
                                    v-for="location in group.locations"
                                    :key="location.id"
                                    :value="location.id"
                                >
                                    {{ location.name }}
                                </option>
                            </optgroup>
                        </select>
                        <p v-if="locations.length === 0" class="text-sm text-amber-600">
                            {{ t('custody.no_locations_available') }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="quick-serial">{{ t('custody.serial_number_optional') }}</Label>
                        <Input id="quick-serial" v-model="quickCreateForm.serial_number" />
                    </div>

                    <div class="space-y-2">
                        <Label for="quick-condition">{{ t('custody.asset_condition') }}</Label>
                        <select
                            id="quick-condition"
                            v-model="quickCreateForm.condition"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option value="good">{{ t('custody.condition_good') }}</option>
                            <option value="damaged">{{ t('custody.condition_damaged') }}</option>
                        </select>
                    </div>

                    <div class="flex justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="!canAddCurrentToQuickCreateList"
                            @click="addToQuickCreateList"
                        >
                            <Icon name="Plus" class="mr-2 h-4 w-4 rtl:mr-0 rtl:ml-2" />
                            {{ t('custody.add_to_create_list') }}
                        </Button>
                    </div>

                    <div v-if="pendingQuickCreateItems.length > 0" class="space-y-2">
                        <Label>{{ t('custody.assets_to_create') }} ({{ pendingQuickCreateItems.length }})</Label>
                        <div class="max-h-48 space-y-2 overflow-y-auto rounded-md border p-2">
                            <div
                                v-for="(item, index) in pendingQuickCreateItems"
                                :key="item.id"
                                class="flex items-start justify-between gap-3 rounded-md bg-muted/50 px-3 py-2 text-sm"
                            >
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium">{{ item.template_name }}</p>
                                    <p class="text-muted-foreground">
                                        {{ item.location_name }}
                                        <span v-if="item.serial_number"> · {{ item.serial_number }}</span>
                                        · {{ item.condition === 'good' ? t('custody.condition_good') : t('custody.condition_damaged') }}
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="shrink-0 text-red-600 hover:text-red-700"
                                    :title="t('custody.remove_from_create_list')"
                                    @click="removeFromQuickCreateList(index)"
                                >
                                    <Icon name="Trash2" class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter class="gap-2 sm:gap-0">
                    <Button variant="outline" @click="closeQuickCreateDialog">{{ t('common.cancel') }}</Button>
                    <Button
                        @click="submitQuickCreate"
                        :disabled="quickCreateSubmitting || quickCreateSubmitCount === 0"
                        class="bg-blue-600 hover:bg-blue-700 text-white"
                    >
                        {{
                            quickCreateSubmitting
                                ? t('custody.creating_asset')
                                : t('custody.create_and_assign_count', { count: quickCreateSubmitCount })
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Document Upload Dialog -->
        <Dialog v-model:open="showDocumentUpload">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ t('custody.upload_document_button') }}</DialogTitle>
                </DialogHeader>
                
                <div class="space-y-4">
                    <div>
                        <Label for="document-upload">{{ t('custody.select_document') }}</Label>
                        <Input 
                            id="document-upload"
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png,.gif"
                            @change="handleDocumentUpload"
                            class="mt-1"
                        />
                        <p class="text-sm text-muted-foreground mt-1">
                            {{ t('custody.upload_signed_document') }}
                        </p>
                    </div>
                    
                    <div v-if="selectedCustodyChange" class="p-3 bg-gray-50 rounded-lg">
                        <p class="font-medium">{{ selectedCustodyChange.changes_summary || t('custody.custody_change') }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ new Date(selectedCustodyChange.created_at).toLocaleDateString() }}
                        </p>
                    </div>
                </div>
                
                <DialogFooter>
                    <Button variant="outline" @click="showDocumentUpload = false">{{ t('common.cancel') }}</Button>
                    <Button 
                        @click="uploadDocument"
                        :disabled="!selectedDocument || loading"
                    >
                        {{ loading ? t('custody.uploading') : t('custody.upload_document_button') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import Icon from '@/components/Icon.vue';
import Heading from '@/components/Heading.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import AssetTemplatePicker, { type AssetTemplateOption } from '@/components/AssetTemplatePicker.vue';
import { fetchWithCsrf } from '@/lib/csrf';
import { useI18n } from 'vue-i18n';
import { computed, ref, onMounted, watch } from 'vue';
import type { Employee, Asset, AssetCategory, Company, CustodyChange, BreadcrumbItem } from '@/types';

const { t, locale } = useI18n();

interface LocationOption {
    id: number;
    name: string;
    building?: string | null;
    office_number?: string | null;
    company_id?: number;
    company_name_en?: string | null;
    company_name_ar?: string | null;
}

interface QuickCreateAssetItem {
    id: string;
    asset_template_id: number;
    template_name: string;
    location_id: number;
    location_name: string;
    serial_number: string;
    condition: 'good' | 'damaged';
}

interface Props {
    employee: Employee;
    currentAssets: Asset[];
    availableAssets: Asset[];
    recentCustodyChanges: CustodyChange[];
    locations?: LocationOption[];
    categories?: AssetCategory[];
    companies?: Company[];
}

const props = defineProps<Props>();
const locations = computed(() => props.locations ?? []);
const locationsByCompany = computed(() => {
    const groups = new Map<number, { companyId: number; companyName: string; locations: LocationOption[] }>();

    for (const location of locations.value) {
        const companyId = location.company_id ?? 0;
        const companyName = locale.value === 'ar'
            ? (location.company_name_ar || location.company_name_en || t('custody.na'))
            : (location.company_name_en || location.company_name_ar || t('custody.na'));

        if (!groups.has(companyId)) {
            groups.set(companyId, { companyId, companyName, locations: [] });
        }

        groups.get(companyId)!.locations.push(location);
    }

    return [...groups.values()].sort((a, b) => a.companyName.localeCompare(b.companyName, locale.value));
});
const assetCategories = computed(() => props.categories ?? []);
const companies = computed(() => props.companies ?? []);

// Reactive state
const loading = ref(false);
const updatedAssets = ref<Asset[]>([...props.currentAssets]);
const changesSummary = ref('');
const showAssetSearch = ref(false);
const assetSearchQuery = ref('');
const searchResults = ref<Asset[]>([...props.availableAssets]);
const selectedAssetIds = ref<Set<Asset['id']>>(new Set());
const showDocumentUpload = ref(false);
const showQuickCreate = ref(false);
const selectedCustodyChange = ref<CustodyChange | null>(null);
const selectedDocument = ref<File | null>(null);
const hasChanges = ref(false);
const selectedTemplate = ref<AssetTemplateOption | null>(null);
const quickCreateSubmitting = ref(false);
const pendingQuickCreateItems = ref<QuickCreateAssetItem[]>([]);
const quickCreateForm = ref({
    location_id: '' as string | number,
    serial_number: '',
    condition: 'good' as 'good' | 'damaged',
});

// Computed properties
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
        title: props.employee.full_name || 'Employee',
        href: `/employees/${props.employee.id}`,
    },
    {
        title: t('custody.update_custody'),
        href: `/employees/${props.employee.id}/custody`,
    },
]);

// Function to check if there are changes
const checkForChanges = () => {
    const currentAssetsArray = props.currentAssets || [];
    const updatedAssetsArray = updatedAssets.value || [];
    
    const currentIds = new Set(currentAssetsArray.map(a => a.id));
    const updatedIds = new Set(updatedAssetsArray.map(a => a.id));
    
    // Check if sizes are different
    if (currentIds.size !== updatedIds.size) {
        hasChanges.value = true;
        return;
    }
    
    // Check if any current asset was removed
    for (const id of currentIds) {
        if (!updatedIds.has(id)) {
            hasChanges.value = true;
            return;
        }
    }
    
    // Check if any new asset was added
    for (const id of updatedIds) {
        if (!currentIds.has(id)) {
            hasChanges.value = true;
            return;
        }
    }
    
    hasChanges.value = false;
};

// Watch for changes in updatedAssets
watch(updatedAssets, () => {
    checkForChanges();
}, { deep: true });

// Sync list when server data changes and the user has no pending edits
watch(() => props.currentAssets, (newAssets) => {
    if (!hasChanges.value) {
        updatedAssets.value = [...newAssets];
    }
    checkForChanges();
}, { deep: true });

// Initial check
checkForChanges();

const pendingCustodyChanges = computed(() => {
    return props.recentCustodyChanges.filter(change => 
        change.status === 'pending' && !change.document_path
    );
});

const otherCustodyChanges = computed(() => {
    return props.recentCustodyChanges.filter(change => 
        change.status !== 'pending' || change.document_path
    );
});

const currentAssetIds = computed(() => new Set(props.currentAssets.map((asset) => asset.id)));

const isNewAsset = (asset: Asset): boolean => !currentAssetIds.value.has(asset.id);

const getAssetDisplayName = (asset: Asset): string => {
    const templateName = asset.asset_template?.name || asset.assetTemplate?.name;

    if (templateName) {
        return templateName;
    }

    if (asset.model_name && asset.model_name !== asset.asset_tag) {
        return asset.model_name;
    }

    if (asset.manufacturer) {
        return asset.manufacturer;
    }

    return '—';
};

const addedAssetsCount = computed(() =>
    updatedAssets.value.filter((asset) => isNewAsset(asset)).length
);

const pendingRemovedAssets = computed(() => {
    const updatedIds = new Set(updatedAssets.value.map((asset) => asset.id));

    return props.currentAssets.filter((asset) => !updatedIds.has(asset.id));
});

const removedAssetsCount = computed(() => pendingRemovedAssets.value.length);

// Methods
const getStatusBadgeClass = (status: string) => {
    switch (status) {
        case 'active':
            return 'bg-green-100 text-green-800';
        case 'inactive':
            return 'bg-yellow-100 text-yellow-800';
        case 'terminated':
            return 'bg-red-100 text-red-800';
        case 'pending':
            return 'bg-amber-100 text-amber-800';
        case 'signed':
            return 'bg-blue-100 text-blue-800';
        case 'completed':
            return 'bg-green-100 text-green-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

const removeAsset = (asset: Asset) => {
    if (!confirm(t('custody.confirm_remove_asset', { tag: asset.asset_tag }))) {
        return;
    }

    updatedAssets.value = updatedAssets.value.filter(a => a.id !== asset.id);
    checkForChanges();
};

const restoreAsset = (asset: Asset) => {
    if (updatedAssets.value.some((entry) => entry.id === asset.id)) {
        return;
    }

    updatedAssets.value = [...updatedAssets.value, asset];
    checkForChanges();
};

const resetChanges = () => {
    updatedAssets.value = [...props.currentAssets];
    changesSummary.value = '';
    checkForChanges();
};

const handleDocumentUpload = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        selectedDocument.value = target.files[0];
    }
};

const searchAssets = async () => {
    if (assetSearchQuery.value.length < 2) {
        searchResults.value = props.availableAssets;
        return;
    }
    
    try {
        const response = await fetch(`/api/custody/available-assets?search=${encodeURIComponent(assetSearchQuery.value)}`);
        const assets = await response.json();
        searchResults.value = assets;
    } catch (error) {
        console.error('Asset search failed:', error);
        searchResults.value = [];
    }
};

const toggleAssetSelection = (asset: Asset) => {
    if (selectedAssetIds.value.has(asset.id)) {
        selectedAssetIds.value.delete(asset.id);
    } else {
        selectedAssetIds.value.add(asset.id);
    }
};

const getLocationName = (locationId: string | number): string => {
    const location = locations.value.find((entry) => entry.id === Number(locationId));
    if (!location) {
        return '';
    }

    return formatLocationOptionLabel(location);
};

const formatLocationOptionLabel = (location: LocationOption): string => {
    const companyName = locale.value === 'ar'
        ? (location.company_name_ar || location.company_name_en || '')
        : (location.company_name_en || location.company_name_ar || '');

    if (!companyName) {
        return location.name;
    }

    return `${location.name} (${companyName})`;
};

const buildQuickCreateItemFromForm = (): QuickCreateAssetItem | null => {
    if (!selectedTemplate.value || !quickCreateForm.value.location_id) {
        return null;
    }

    return {
        id: crypto.randomUUID(),
        asset_template_id: selectedTemplate.value.id,
        template_name: selectedTemplate.value.display_name || selectedTemplate.value.name,
        location_id: Number(quickCreateForm.value.location_id),
        location_name: getLocationName(quickCreateForm.value.location_id),
        serial_number: quickCreateForm.value.serial_number.trim(),
        condition: quickCreateForm.value.condition,
    };
};

const canAddCurrentToQuickCreateList = computed(() => buildQuickCreateItemFromForm() !== null);

const quickCreateSubmitCount = computed(() => {
    if (pendingQuickCreateItems.value.length > 0) {
        return pendingQuickCreateItems.value.length;
    }

    return buildQuickCreateItemFromForm() ? 1 : 0;
});

const getQuickCreateItemsToSubmit = (): QuickCreateAssetItem[] => {
    if (pendingQuickCreateItems.value.length > 0) {
        return [...pendingQuickCreateItems.value];
    }

    const currentItem = buildQuickCreateItemFromForm();

    return currentItem ? [currentItem] : [];
};

const resetQuickCreateFormFields = () => {
    selectedTemplate.value = null;
    quickCreateForm.value = {
        location_id: locations.value.length === 1 ? locations.value[0].id : quickCreateForm.value.location_id,
        serial_number: '',
        condition: 'good',
    };
};

const openQuickCreateDialog = () => {
    showQuickCreate.value = true;

    if (locations.value.length === 1) {
        quickCreateForm.value.location_id = locations.value[0].id;
    }
};

const closeQuickCreateDialog = () => {
    showQuickCreate.value = false;
    pendingQuickCreateItems.value = [];
    resetQuickCreateFormFields();
    quickCreateForm.value.location_id = locations.value.length === 1 ? locations.value[0].id : '';
};

const addToQuickCreateList = () => {
    const item = buildQuickCreateItemFromForm();

    if (!item) {
        return;
    }

    pendingQuickCreateItems.value = [...pendingQuickCreateItems.value, item];
    resetQuickCreateFormFields();
};

const removeFromQuickCreateList = (index: number) => {
    pendingQuickCreateItems.value = pendingQuickCreateItems.value.filter((_, itemIndex) => itemIndex !== index);
};

const submitQuickCreate = async () => {
    const items = getQuickCreateItemsToSubmit();

    if (items.length === 0) {
        alert(t('custody.no_assets_in_create_list'));
        return;
    }

    quickCreateSubmitting.value = true;

    try {
        const response = await fetchWithCsrf(route('employees.custody.quick-create-asset', props.employee.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                assets: items.map((item) => ({
                    asset_template_id: item.asset_template_id,
                    location_id: item.location_id,
                    serial_number: item.serial_number || null,
                    condition: item.condition,
                })),
            }),
        });

        if (response.status === 419) {
            alert(t('custody.session_expired_reload'));
            return;
        }

        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Quick create non-JSON response:', text);
            alert(t('custody.quick_create_failed'));
            return;
        }

        const result = await response.json();

        if (response.ok) {
            const fallbackMessage = items.length === 1
                ? t('custody.quick_create_success', { tag: result.asset?.asset_tag || '' })
                : t('custody.quick_create_multiple_success', { count: items.length });

            alert(result.message || fallbackMessage);
            closeQuickCreateDialog();
            router.reload();
            return;
        }

        const validationError = result.errors
            ? Object.values(result.errors).flat()[0]
            : null;

        alert(result.error || validationError || result.message || t('custody.quick_create_failed'));
    } catch (error) {
        console.error('Quick create asset failed:', error);
        alert(t('custody.quick_create_failed'));
    } finally {
        quickCreateSubmitting.value = false;
    }
};

const addSelectedAssets = () => {
    const assetsToAdd = searchResults.value.filter(asset => selectedAssetIds.value.has(asset.id));
    
    // Add assets that aren't already in the updated list
    // Use spread operator to ensure reactivity
    const newAssets = assetsToAdd.filter(asset => 
        !updatedAssets.value.find(a => a.id === asset.id)
    );
    
    if (newAssets.length > 0) {
        updatedAssets.value = [...updatedAssets.value, ...newAssets];
        checkForChanges();
    }
    
    // Clear selection and close dialog
    selectedAssetIds.value.clear();
    showAssetSearch.value = false;
    assetSearchQuery.value = '';
    searchResults.value = props.availableAssets;
};

const saveCustodyUpdate = async () => {
    if (!hasChanges.value) {
        alert(t('custody.no_changes_to_save'));
        return;
    }

    if (removedAssetsCount.value > 0) {
        const confirmed = confirm(
            t('custody.confirm_save_with_removals', { count: removedAssetsCount.value })
        );

        if (!confirmed) {
            return;
        }
    }
    
    loading.value = true;
    
    try {
        const url = route('employees.custody.store', props.employee.id);
        
        const requestData = {
            new_asset_ids: updatedAssets.value.map(a => a.id),
            changes_summary: changesSummary.value || ''
        };
        
        const response = await fetchWithCsrf(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(requestData),
        });

        if (response.status === 419) {
            return;
        }
        
        // Check if response is actually JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Non-JSON response received:', text);
            alert('Server returned non-JSON response. Check browser console for details.');
            return;
        }
        
        if (response.ok) {
            const result = await response.json();
            alert(t('custody.custody_updated_successfully'));
            
            // Redirect back to custody management page
            router.visit(route('employees.custody.show', props.employee.id));
        } else {
            const error = await response.json();
            console.error('Server error:', error);
            alert(t('custody.failed_to_update_custody') + ': ' + (error.error || error.message || t('custody.unknown_error')));
        }
    } catch (error) {
        console.error('Error updating custody:', error);
        alert(t('custody.failed_to_update_try_again'));
    } finally {
        loading.value = false;
    }
};

const viewCustodyDocument = (custodyChange: CustodyChange) => {
    window.open(route('custody.document', custodyChange.id), '_blank');
};

const printCustodyDocument = (custodyChange: CustodyChange) => {
    // Open the document in a new window and trigger print
    const printWindow = window.open(route('custody.document', custodyChange.id), '_blank');
    if (printWindow) {
        printWindow.addEventListener('load', () => {
            printWindow.print();
        });
    }
};

const uploadDocumentForChange = async (custodyChange: CustodyChange) => {
    selectedCustodyChange.value = custodyChange;
    showDocumentUpload.value = true;
};

const uploadDocument = async () => {
    if (!selectedDocument.value || !selectedCustodyChange.value) {
        alert(t('custody.select_document_to_upload'));
        return;
    }

    loading.value = true;
    try {
        const formData = new FormData();
        formData.append('document', selectedDocument.value);
        formData.append('type', 'signed');

        const response = await fetchWithCsrf(route('custody.upload', selectedCustodyChange.value.id), {
            method: 'POST',
            body: formData,
        });

        if (response.status === 419) {
            return;
        }

        if (response.ok) {
            const result = await response.json();
            alert(t('custody.document_uploaded_successfully'));
            // Close dialog and reset state
            showDocumentUpload.value = false;
            selectedCustodyChange.value = null;
            selectedDocument.value = null;
            // Refresh the page to show updated custody changes
            router.reload();
        } else {
            const error = await response.json();
            alert(t('custody.failed_to_upload_document') + ': ' + (error.error || t('custody.unknown_error')));
        }
    } catch (error) {
        console.error('Error uploading document:', error);
        alert(t('custody.failed_to_upload_try_again'));
    } finally {
        loading.value = false;
    }
};

// Initialize search results
onMounted(() => {
    searchResults.value = props.availableAssets;
});
</script> 
<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Label } from '@/components/ui/label';
import Icon from '@/components/Icon.vue';
import { fetchWithCsrf } from '@/lib/csrf';
import {
    EMPLOYEE_DOCUMENT_TYPES,
    type EmployeeDocumentItem,
    type EmployeeDocumentType,
} from '@/constants/employeeDocuments';

interface Props {
    employeeId?: number | null;
    documents?: EmployeeDocumentItem[];
    mode?: 'create' | 'edit' | 'show';
    errors?: Record<string, string>;
}

const props = withDefaults(defineProps<Props>(), {
    employeeId: null,
    documents: () => [],
    mode: 'edit',
    errors: () => ({}),
});

const emit = defineEmits<{
    'update:documents': [documents: EmployeeDocumentItem[]];
    'pending-change': [pending: Partial<Record<EmployeeDocumentType, File | null>>];
}>();

const { t } = useI18n();

const sectionOpen = ref(props.mode === 'edit');

const localDocuments = ref<EmployeeDocumentItem[]>([...props.documents]);
const pendingFiles = ref<Partial<Record<EmployeeDocumentType, File | null>>>({});
const uploadingType = ref<EmployeeDocumentType | null>(null);
const deletingType = ref<EmployeeDocumentType | null>(null);
const fieldErrors = ref<Record<string, string>>({});

const isReadOnly = computed(() => props.mode === 'show');
const isCreateMode = computed(() => props.mode === 'create');

const documentByType = computed(() => {
    const map = new Map<EmployeeDocumentType, EmployeeDocumentItem>();

    for (const document of localDocuments.value) {
        map.set(document.type, document);
    }

    return map;
});

const typeLabel = (type: EmployeeDocumentType): string => t(`employees.documents.types.${type}`);

const formatSize = (bytes: number): string => {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

const syncDocuments = () => {
    emit('update:documents', [...localDocuments.value]);
};

const syncPending = () => {
    emit('pending-change', { ...pendingFiles.value });
};

const onFileSelected = async (type: EmployeeDocumentType, event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    input.value = '';

    if (!file) {
        return;
    }

    fieldErrors.value = { ...fieldErrors.value, [type]: '' };

    if (isCreateMode.value || !props.employeeId) {
        pendingFiles.value = { ...pendingFiles.value, [type]: file };
        syncPending();
        return;
    }

    uploadingType.value = type;

    try {
        const formData = new FormData();
        formData.append('type', type);
        formData.append('file', file);

        const response = await fetchWithCsrf(route('employees.documents.store', props.employeeId), {
            method: 'POST',
            body: formData,
        });

        const payload = await response.json();

        if (!response.ok) {
            const message = payload?.message
                || payload?.errors?.file?.[0]
                || payload?.errors?.type?.[0]
                || t('employees.documents.upload_failed');
            fieldErrors.value = { ...fieldErrors.value, [type]: message };
            return;
        }

        const next = localDocuments.value.filter((item) => item.type !== type);
        next.push(payload.document as EmployeeDocumentItem);
        localDocuments.value = next;
        syncDocuments();
    } catch {
        fieldErrors.value = { ...fieldErrors.value, [type]: t('employees.documents.upload_failed') };
    } finally {
        uploadingType.value = null;
    }
};

const removeDocument = async (type: EmployeeDocumentType) => {
    if (isCreateMode.value || !props.employeeId) {
        pendingFiles.value = { ...pendingFiles.value, [type]: null };
        syncPending();
        return;
    }

    deletingType.value = type;

    try {
        const response = await fetchWithCsrf(route('employees.documents.destroy', [props.employeeId, type]), {
            method: 'DELETE',
        });

        if (!response.ok) {
            fieldErrors.value = { ...fieldErrors.value, [type]: t('employees.documents.delete_failed') };
            return;
        }

        localDocuments.value = localDocuments.value.filter((item) => item.type !== type);
        syncDocuments();
    } catch {
        fieldErrors.value = { ...fieldErrors.value, [type]: t('employees.documents.delete_failed') };
    } finally {
        deletingType.value = null;
    }
};

const currentFileForType = (type: EmployeeDocumentType): EmployeeDocumentItem | null => {
    return documentByType.value.get(type) ?? null;
};

const pendingFileForType = (type: EmployeeDocumentType): File | null => {
    return pendingFiles.value[type] ?? null;
};

const hasFile = (type: EmployeeDocumentType): boolean => {
    return Boolean(currentFileForType(type) || pendingFileForType(type));
};

const uploadedCount = computed(() => {
    return EMPLOYEE_DOCUMENT_TYPES.filter((type) => hasFile(type)).length;
});
</script>

<template>
    <Card>
        <Collapsible v-model:open="sectionOpen">
            <CollapsibleTrigger as-child>
                <CardHeader class="cursor-pointer hover:bg-gray-50">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <Icon name="FolderOpen" class="h-5 w-5 text-blue-600" />
                            <CardTitle class="text-xl">{{ t('employees.documents.title') }}</CardTitle>
                            <Badge v-if="uploadedCount > 0" variant="default">{{ uploadedCount }}/{{ EMPLOYEE_DOCUMENT_TYPES.length }}</Badge>
                        </div>
                        <Icon :name="!sectionOpen ? 'ChevronRight' : 'ChevronDown'" class="h-5 w-5" />
                    </div>
                </CardHeader>
            </CollapsibleTrigger>

            <CollapsibleContent>
                <CardContent class="space-y-4">
                    <p v-if="isCreateMode" class="text-sm text-muted-foreground">
                        {{ t('employees.documents.create_hint') }}
                    </p>
            <div
                v-for="type in EMPLOYEE_DOCUMENT_TYPES"
                :key="type"
                class="rounded-lg border border-slate-200 bg-slate-50/60 p-4"
            >
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0 flex-1 space-y-3">
                        <div>
                            <Label class="text-base font-medium text-slate-900">{{ typeLabel(type) }}</Label>
                            <p class="text-xs text-muted-foreground">{{ t('employees.documents.upload_hint') }}</p>
                        </div>

                        <div v-if="currentFileForType(type)" class="space-y-2">
                            <div
                                v-if="currentFileForType(type)?.is_image"
                                class="overflow-hidden rounded-md border border-slate-200 bg-white"
                            >
                                <img
                                    :src="currentFileForType(type)?.url"
                                    :alt="currentFileForType(type)?.name"
                                    class="max-h-48 w-full object-contain"
                                />
                            </div>

                            <div class="flex flex-wrap items-center gap-2 text-sm">
                                <a
                                    :href="currentFileForType(type)?.url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="font-medium text-blue-600 hover:underline"
                                >
                                    {{ currentFileForType(type)?.name }}
                                </a>
                                <span class="text-muted-foreground">
                                    ({{ formatSize(currentFileForType(type)?.size ?? 0) }})
                                </span>
                            </div>
                        </div>

                        <div v-else-if="pendingFileForType(type)" class="text-sm text-slate-700">
                            <span class="font-medium">{{ pendingFileForType(type)?.name }}</span>
                            <span class="text-muted-foreground">
                                ({{ formatSize(pendingFileForType(type)?.size ?? 0) }})
                            </span>
                            <span class="ms-2 text-xs text-amber-700">{{ t('employees.documents.pending_upload') }}</span>
                        </div>

                        <p v-else-if="isReadOnly" class="text-sm text-muted-foreground">
                            {{ t('employees.documents.no_file') }}
                        </p>

                        <InputError :message="fieldErrors[type] || errors[type]" />
                    </div>

                    <div v-if="!isReadOnly" class="flex flex-wrap gap-2">
                        <label class="inline-flex">
                            <input
                                type="file"
                                class="hidden"
                                accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,image/*"
                                :disabled="uploadingType === type || deletingType === type"
                                @change="onFileSelected(type, $event)"
                            />
                            <Button type="button" variant="outline" size="sm" as-child>
                                <span>
                                    {{
                                        uploadingType === type
                                            ? t('employees.documents.uploading')
                                            : hasFile(type)
                                              ? t('employees.documents.replace')
                                              : t('employees.documents.upload')
                                    }}
                                </span>
                            </Button>
                        </label>

                        <Button
                            v-if="hasFile(type)"
                            type="button"
                            variant="destructive"
                            size="sm"
                            :disabled="uploadingType === type || deletingType === type"
                            @click="removeDocument(type)"
                        >
                            {{ deletingType === type ? t('employees.documents.deleting') : t('employees.documents.delete') }}
                        </Button>
                    </div>

                    <div v-else-if="currentFileForType(type)" class="flex gap-2">
                        <Button as-child size="sm" variant="outline">
                            <a
                                :href="currentFileForType(type)?.url"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                {{ t('employees.documents.open') }}
                            </a>
                        </Button>
                    </div>
                </div>
            </div>
                </CardContent>
            </CollapsibleContent>
        </Collapsible>
    </Card>
</template>

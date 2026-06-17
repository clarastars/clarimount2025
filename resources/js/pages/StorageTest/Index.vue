<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

interface StorageFile {
    path: string;
    name: string;
    url: string;
    size: number;
    mime: string | null;
    last_modified: number;
    is_image: boolean;
}

interface Props {
    files: StorageFile[];
    disk: string;
    bucket: string | null;
    endpoint: string | null;
    status?: string | null;
}

const props = defineProps<Props>();
const { t } = useI18n();

const breadcrumbItems = computed((): BreadcrumbItem[] => [
    {
        title: t('storage_test.title'),
        href: '/storage-test',
    },
]);

const form = useForm<{ file: File | null }>({
    file: null,
});

const onFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    form.file = target.files?.[0] ?? null;
};

const submit = () => {
    if (!form.file) {
        return;
    }

    form.post(route('storage-test.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
        },
    });
};

const deleteFile = (path: string) => {
    router.delete(route('storage-test.destroy'), {
        data: { path },
        preserveScroll: true,
    });
};

const formatSize = (bytes: number): string => {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('storage_test.title')" />

        <div class="mx-auto max-w-4xl space-y-6 px-4 py-6">
            <HeadingSmall
                :title="t('storage_test.title')"
                :description="t('storage_test.description')"
            />

            <div v-if="status" class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                {{ status }}
            </div>

            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                <p><strong>{{ t('storage_test.disk') }}:</strong> {{ disk }}</p>
                <p><strong>{{ t('storage_test.bucket') }}:</strong> {{ bucket }}</p>
                <p class="break-all"><strong>{{ t('storage_test.endpoint') }}:</strong> {{ endpoint }}</p>
            </div>

            <form class="space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">{{ t('storage_test.upload') }}</h3>
                    <p class="mt-1 text-sm text-slate-600">{{ t('storage_test.upload_hint') }}</p>
                </div>

                <input
                    type="file"
                    accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,image/*"
                    class="block w-full text-sm text-slate-600 file:me-4 file:rounded-md file:border-0 file:bg-blue-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-blue-700"
                    @change="onFileChange"
                />
                <InputError :message="form.errors.file" />

                <Button type="submit" :disabled="form.processing || !form.file">
                    {{ form.processing ? t('storage_test.uploading') : t('storage_test.upload') }}
                </Button>
            </form>

            <div class="space-y-4">
                <h3 class="text-base font-semibold text-slate-900">{{ t('storage_test.files') }}</h3>

                <p v-if="files.length === 0" class="text-sm text-slate-500">
                    {{ t('storage_test.empty') }}
                </p>

                <div v-else class="grid gap-4 sm:grid-cols-2">
                    <div
                        v-for="file in files"
                        :key="file.path"
                        class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm"
                    >
                        <div v-if="file.is_image" class="aspect-video bg-slate-100">
                            <img :src="file.url" :alt="file.name" class="h-full w-full object-contain" />
                        </div>

                        <div class="space-y-2 p-4">
                            <p class="truncate font-medium text-slate-900" :title="file.name">{{ file.name }}</p>
                            <p class="text-xs text-slate-500">
                                {{ t('storage_test.size') }}: {{ formatSize(file.size) }}
                                <span v-if="file.mime"> · {{ t('storage_test.type') }}: {{ file.mime }}</span>
                            </p>

                            <div class="flex gap-2">
                                <Button as-child size="sm" variant="outline">
                                    <a :href="file.url" target="_blank" rel="noopener noreferrer">
                                        {{ t('storage_test.open') }}
                                    </a>
                                </Button>
                                <Button size="sm" variant="destructive" @click="deleteFile(file.path)">
                                    {{ t('storage_test.delete') }}
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

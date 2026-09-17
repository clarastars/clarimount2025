<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';

interface ImportResultRow {
    row: number;
    id_number: string;
    days_added: number | null;
    previous: number | null;
    new: number | null;
    employee_id: number | null;
    status: string;
    message: string;
}

interface ImportResult {
    import_id?: number;
    updated: number;
    skipped: number;
    rows_processed: number;
    results: ImportResultRow[];
}

interface UndoResultRow {
    employee_id: number | null;
    id_number: string | null;
    days_removed: number | null;
    previous: number | null;
    restored_to: number | null;
    status: string;
    message: string;
}

interface UndoResult {
    import_id: number;
    restored: number;
    adjusted: number;
    failed: number;
    results: UndoResultRow[];
}

interface RecentImport {
    id: number;
    original_filename: string | null;
    updated_count: number;
    skipped_count: number;
    rows_processed: number;
    can_undo: boolean;
    undone_at: string | null;
    created_at: string | null;
}

interface Props {
    lastResult?: ImportResult | null;
    undoResult?: UndoResult | null;
    recentImports?: RecentImport[];
}

const props = withDefaults(defineProps<Props>(), {
    lastResult: null,
    undoResult: null,
    recentImports: () => [],
});

const { t } = useI18n();
const page = usePage();

const breadcrumbItems = computed((): BreadcrumbItem[] => [
    {
        title: t('settings.leave_days_used_import'),
        href: '/settings/leave-days-used-import',
    },
]);

const form = useForm<{ file: File | null }>({
    file: null,
});

const fileInput = ref<HTMLInputElement | null>(null);
const selectedFileName = ref('');
const undoingId = ref<number | null>(null);

const flashSuccess = computed(() => {
    const flash = page.props.flash as { success?: string; warning?: string; error?: string } | undefined;
    return flash?.success ?? null;
});

const flashWarning = computed(() => {
    const flash = page.props.flash as { success?: string; warning?: string; error?: string } | undefined;
    return flash?.warning ?? null;
});

const flashError = computed(() => {
    const flash = page.props.flash as { success?: string; warning?: string; error?: string } | undefined;
    return flash?.error ?? null;
});

const skippedRows = computed(() =>
    (props.lastResult?.results ?? []).filter((row) => row.status === 'skipped'),
);

const skippedRowNumbers = computed(() =>
    skippedRows.value.map((row) => row.row).join(', '),
);

const onFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;
    form.file = file;
    selectedFileName.value = file?.name ?? '';
};

const submit = () => {
    form.post(route('settings.leave-days-used-import.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset('file');
            selectedFileName.value = '';
            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
    });
};

const undoImport = (importId: number) => {
    if (!confirm(t('settings.leave_days_used_import_undo_confirm'))) {
        return;
    }

    undoingId.value = importId;
    router.post(
        route('settings.leave-days-used-import.undo', importId),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                undoingId.value = null;
            },
        },
    );
};

const formatNumber = (value: number | null): string => {
    if (value === null || value === undefined) {
        return '—';
    }

    return Number(value).toFixed(2);
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('settings.leave_days_used_import')" />

        <SettingsLayout content-width="wide">
            <div class="space-y-6">
                <HeadingSmall
                    :title="t('settings.leave_days_used_import')"
                    :description="t('settings.leave_days_used_import_description')"
                />

                <div
                    v-if="flashError"
                    class="rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800"
                >
                    {{ flashError }}
                </div>

                <div
                    v-else-if="flashWarning"
                    class="rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                >
                    {{ flashWarning }}
                </div>

                <div
                    v-else-if="flashSuccess"
                    class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700"
                >
                    {{ flashSuccess }}
                </div>

                <div
                    v-if="skippedRows.length > 0"
                    class="rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950 space-y-2"
                >
                    <p class="font-medium">
                        {{ t('settings.leave_days_used_import_skipped_alert', { count: skippedRows.length }) }}
                    </p>
                    <p>
                        {{ t('settings.leave_days_used_import_skipped_rows', { rows: skippedRowNumbers }) }}
                    </p>
                    <ul class="list-disc space-y-1 pe-5">
                        <li v-for="(row, idx) in skippedRows" :key="`skip-${row.row}-${idx}`">
                            {{ t('settings.leave_days_used_import_col_row') }} {{ row.row }}
                            <span v-if="row.id_number"> — {{ row.id_number }}</span>
                            : {{ row.message }}
                        </li>
                    </ul>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{{ t('settings.leave_days_used_import_upload') }}</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <p class="text-sm text-muted-foreground">
                            {{ t('settings.leave_days_used_import_hint') }}
                        </p>

                        <ul class="list-disc space-y-1 pe-5 text-sm text-muted-foreground">
                            <li>{{ t('settings.leave_days_used_import_columns') }}</li>
                            <li>{{ t('settings.leave_days_used_import_add_behavior') }}</li>
                        </ul>

                        <div>
                            <a
                                :href="route('settings.leave-days-used-import.sample')"
                                class="inline-flex"
                            >
                                <Button type="button" variant="outline">
                                    {{ t('settings.leave_days_used_import_sample') }}
                                </Button>
                            </a>
                        </div>

                        <form class="space-y-4" @submit.prevent="submit">
                            <div class="grid gap-2">
                                <Label for="file">{{ t('settings.leave_days_used_import_file') }}</Label>
                                <input
                                    id="file"
                                    ref="fileInput"
                                    type="file"
                                    accept=".xlsx,.xls,.csv"
                                    class="block w-full text-sm file:me-3 file:rounded-md file:border-0 file:bg-primary file:px-3 file:py-2 file:text-sm file:font-medium file:text-primary-foreground"
                                    @change="onFileChange"
                                />
                                <p v-if="selectedFileName" class="text-xs text-muted-foreground">
                                    {{ selectedFileName }}
                                </p>
                                <InputError :message="form.errors.file" />
                            </div>

                            <Button type="submit" :disabled="form.processing || !form.file">
                                {{
                                    form.processing
                                        ? t('settings.leave_days_used_import_processing')
                                        : t('settings.leave_days_used_import_submit')
                                }}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card v-if="props.recentImports.length">
                    <CardHeader>
                        <CardTitle>{{ t('settings.leave_days_used_import_recent') }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="overflow-x-auto rounded-md border">
                            <table class="w-full text-sm">
                                <thead class="bg-muted/50">
                                    <tr>
                                        <th class="px-3 py-2 text-start">#</th>
                                        <th class="px-3 py-2 text-start">{{ t('settings.leave_days_used_import_filename') }}</th>
                                        <th class="px-3 py-2 text-start">{{ t('settings.leave_days_used_import_imported_at') }}</th>
                                        <th class="px-3 py-2 text-start">{{ t('settings.leave_days_used_import_updated_count') }}</th>
                                        <th class="px-3 py-2 text-start">{{ t('settings.leave_days_used_import_skipped_count') }}</th>
                                        <th class="px-3 py-2 text-start">{{ t('settings.leave_days_used_import_col_status') }}</th>
                                        <th class="px-3 py-2 text-start"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="item in props.recentImports"
                                        :key="item.id"
                                        class="border-t"
                                    >
                                        <td class="px-3 py-2">{{ item.id }}</td>
                                        <td class="px-3 py-2">{{ item.original_filename || '—' }}</td>
                                        <td class="px-3 py-2">{{ item.created_at || '—' }}</td>
                                        <td class="px-3 py-2">{{ item.updated_count }}</td>
                                        <td class="px-3 py-2">{{ item.skipped_count }}</td>
                                        <td class="px-3 py-2">
                                            <span v-if="item.undone_at" class="text-muted-foreground">
                                                {{ t('settings.leave_days_used_import_undone') }}
                                            </span>
                                            <span v-else class="text-green-700">
                                                {{ t('settings.leave_days_used_import_status_updated') }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2">
                                            <Button
                                                v-if="item.can_undo"
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                :disabled="undoingId === item.id"
                                                @click="undoImport(item.id)"
                                            >
                                                {{ t('settings.leave_days_used_import_undo') }}
                                            </Button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <Card v-if="props.undoResult">
                    <CardHeader>
                        <CardTitle>{{ t('settings.leave_days_used_import_undo_results') }}</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="overflow-x-auto rounded-md border">
                            <table class="w-full text-sm">
                                <thead class="bg-muted/50">
                                    <tr>
                                        <th class="px-3 py-2 text-start">{{ t('employees.id_number') }}</th>
                                        <th class="px-3 py-2 text-start">{{ t('settings.leave_days_used_import_col_days_removed') }}</th>
                                        <th class="px-3 py-2 text-start">{{ t('settings.leave_days_used_import_col_previous') }}</th>
                                        <th class="px-3 py-2 text-start">{{ t('settings.leave_days_used_import_col_restored_to') }}</th>
                                        <th class="px-3 py-2 text-start">{{ t('settings.leave_days_used_import_col_status') }}</th>
                                        <th class="px-3 py-2 text-start">{{ t('settings.leave_days_used_import_col_message') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(row, idx) in props.undoResult.results"
                                        :key="`undo-${row.employee_id}-${idx}`"
                                        class="border-t"
                                    >
                                        <td class="px-3 py-2">{{ row.id_number || '—' }}</td>
                                        <td class="px-3 py-2">{{ formatNumber(row.days_removed) }}</td>
                                        <td class="px-3 py-2">{{ formatNumber(row.previous) }}</td>
                                        <td class="px-3 py-2">{{ formatNumber(row.restored_to) }}</td>
                                        <td class="px-3 py-2">{{ row.status }}</td>
                                        <td class="px-3 py-2">{{ row.message }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <Card v-if="props.lastResult">
                    <CardHeader>
                        <CardTitle>{{ t('settings.leave_days_used_import_results') }}</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="flex flex-wrap gap-4 text-sm">
                            <div class="rounded-md border bg-muted/30 px-3 py-2">
                                <span class="font-medium">{{ t('settings.leave_days_used_import_updated_count') }}:</span>
                                {{ props.lastResult.updated }}
                            </div>
                            <div
                                class="rounded-md border px-3 py-2"
                                :class="props.lastResult.skipped > 0 ? 'border-amber-300 bg-amber-50 text-amber-950' : 'bg-muted/30'"
                            >
                                <span class="font-medium">{{ t('settings.leave_days_used_import_skipped_count') }}:</span>
                                {{ props.lastResult.skipped }}
                            </div>
                            <div class="rounded-md border bg-muted/30 px-3 py-2">
                                <span class="font-medium">{{ t('settings.leave_days_used_import_processed_count') }}:</span>
                                {{ props.lastResult.rows_processed }}
                            </div>
                        </div>

                        <div class="overflow-x-auto rounded-md border">
                            <table class="w-full text-sm">
                                <thead class="bg-muted/50">
                                    <tr>
                                        <th class="px-3 py-2 text-start">{{ t('settings.leave_days_used_import_col_row') }}</th>
                                        <th class="px-3 py-2 text-start">{{ t('employees.id_number') }}</th>
                                        <th class="px-3 py-2 text-start">{{ t('settings.leave_days_used_import_col_added') }}</th>
                                        <th class="px-3 py-2 text-start">{{ t('settings.leave_days_used_import_col_previous') }}</th>
                                        <th class="px-3 py-2 text-start">{{ t('settings.leave_days_used_import_col_new') }}</th>
                                        <th class="px-3 py-2 text-start">{{ t('settings.leave_days_used_import_col_status') }}</th>
                                        <th class="px-3 py-2 text-start">{{ t('settings.leave_days_used_import_col_message') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(row, idx) in props.lastResult.results"
                                        :key="`${row.row}-${idx}`"
                                        class="border-t"
                                        :class="row.status === 'skipped' ? 'bg-amber-50' : ''"
                                    >
                                        <td class="px-3 py-2">{{ row.row }}</td>
                                        <td class="px-3 py-2">{{ row.id_number || '—' }}</td>
                                        <td class="px-3 py-2">{{ formatNumber(row.days_added) }}</td>
                                        <td class="px-3 py-2">{{ formatNumber(row.previous) }}</td>
                                        <td class="px-3 py-2">{{ formatNumber(row.new) }}</td>
                                        <td class="px-3 py-2">
                                            <span
                                                :class="row.status === 'updated' ? 'text-green-700' : 'text-amber-800 font-medium'"
                                            >
                                                {{
                                                    row.status === 'updated'
                                                        ? t('settings.leave_days_used_import_status_updated')
                                                        : t('settings.leave_days_used_import_status_skipped')
                                                }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2">{{ row.message }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';

import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';

interface SearchResult {
    id: number;
    full_name: string;
    employee_id: string | null;
    company_name: string | null;
    fingerprint_device_id: string | null;
    employment_status: string | null;
}

interface PunchRow {
    id: number | null;
    punch_time: string;
    punch_time_riyadh: string;
    verify_mode: number | null;
    device_id: number | null;
    device_name: string | null;
    serial_number: string | null;
    source: string;
}

interface LookupResult {
    employee: {
        id: number;
        full_name: string;
        employee_id: string | null;
        company_name: string | null;
        fingerprint_device_id: string | null;
    };
    date: string;
    punches: PunchRow[];
    daily_summary: {
        first_punch: string | null;
        last_punch: string | null;
        punch_count: number;
        device_name: string | null;
    } | null;
    presentation: {
        status_ar: string | null;
        late_minutes: number | null;
        punch_count: number | null;
        first_punch: string | null;
        last_punch: string | null;
    } | null;
    api_punches: PunchRow[] | null;
}

interface Props {
    defaultDate: string;
}

const props = defineProps<Props>();
const { t } = useI18n();

const breadcrumbItems = computed((): BreadcrumbItem[] => [
    {
        title: t('settings.fingerprint_day_lookup'),
        href: '/settings/fingerprint-day-lookup',
    },
]);

const searchQuery = ref('');
const searchResults = ref<SearchResult[]>([]);
const searching = ref(false);
const selectedEmployee = ref<SearchResult | null>(null);
const selectedDate = ref(props.defaultDate);
const lookup = ref<LookupResult | null>(null);
const loadingLookup = ref(false);
const loadingApi = ref(false);
const errorMessage = ref('');

let searchTimer: ReturnType<typeof setTimeout> | null = null;

watch(searchQuery, (value) => {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }

    const q = value.trim();
    if (q.length < 2) {
        searchResults.value = [];
        return;
    }

    searchTimer = setTimeout(async () => {
        searching.value = true;
        errorMessage.value = '';
        try {
            const { data } = await axios.get(route('settings.fingerprint-day-lookup.search'), {
                params: { q },
            });
            searchResults.value = data.results ?? [];
        } catch {
            errorMessage.value = t('settings.fingerprint_day_lookup_search_error');
            searchResults.value = [];
        } finally {
            searching.value = false;
        }
    }, 300);
});

const selectEmployee = (employee: SearchResult) => {
    selectedEmployee.value = employee;
    searchQuery.value = employee.full_name;
    searchResults.value = [];
    lookup.value = null;
};

const runLookup = async (fromApi = false) => {
    if (!selectedEmployee.value) {
        errorMessage.value = t('settings.fingerprint_day_lookup_select_employee');
        return;
    }

    if (!selectedDate.value) {
        errorMessage.value = t('settings.fingerprint_day_lookup_select_date');
        return;
    }

    errorMessage.value = '';
    if (fromApi) {
        loadingApi.value = true;
    } else {
        loadingLookup.value = true;
    }

    try {
        const { data } = await axios.get(route('settings.fingerprint-day-lookup.show'), {
            params: {
                employee_id: selectedEmployee.value.id,
                date: selectedDate.value,
                from_api: fromApi ? 1 : 0,
            },
        });
        lookup.value = data;
    } catch {
        errorMessage.value = t('settings.fingerprint_day_lookup_load_error');
    } finally {
        loadingLookup.value = false;
        loadingApi.value = false;
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('settings.fingerprint_day_lookup')" />

        <SettingsLayout content-width="wide">
            <div class="space-y-6">
                <HeadingSmall
                    :title="t('settings.fingerprint_day_lookup')"
                    :description="t('settings.fingerprint_day_lookup_description')"
                />

                <div
                    v-if="errorMessage"
                    class="rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800"
                >
                    {{ errorMessage }}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{{ t('settings.fingerprint_day_lookup_filters') }}</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-2 relative">
                            <Label for="employee-search">{{ t('settings.fingerprint_day_lookup_employee') }}</Label>
                            <Input
                                id="employee-search"
                                v-model="searchQuery"
                                type="search"
                                autocomplete="off"
                                :placeholder="t('settings.fingerprint_day_lookup_employee_placeholder')"
                            />
                            <p v-if="searching" class="text-xs text-muted-foreground">
                                {{ t('settings.fingerprint_day_lookup_searching') }}
                            </p>
                            <div
                                v-if="searchResults.length"
                                class="absolute top-full z-20 mt-1 w-full rounded-md border bg-background shadow-md max-h-64 overflow-y-auto"
                            >
                                <button
                                    v-for="employee in searchResults"
                                    :key="employee.id"
                                    type="button"
                                    class="w-full px-3 py-2 text-start text-sm hover:bg-muted border-b last:border-b-0"
                                    @click="selectEmployee(employee)"
                                >
                                    <div class="font-medium">{{ employee.full_name }}</div>
                                    <div class="text-xs text-muted-foreground">
                                        #{{ employee.id }}
                                        <span v-if="employee.employee_id"> · {{ employee.employee_id }}</span>
                                        <span v-if="employee.company_name"> · {{ employee.company_name }}</span>
                                        <span v-if="employee.fingerprint_device_id">
                                            · PIN {{ employee.fingerprint_device_id }}
                                        </span>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <div
                            v-if="selectedEmployee"
                            class="rounded-md border bg-muted/30 px-3 py-2 text-sm"
                        >
                            <div class="font-medium">{{ selectedEmployee.full_name }}</div>
                            <div class="text-muted-foreground text-xs mt-1">
                                #{{ selectedEmployee.id }}
                                <span v-if="selectedEmployee.employee_id"> · {{ selectedEmployee.employee_id }}</span>
                                <span v-if="selectedEmployee.company_name"> · {{ selectedEmployee.company_name }}</span>
                                <span v-if="selectedEmployee.fingerprint_device_id">
                                    · PIN {{ selectedEmployee.fingerprint_device_id }}
                                </span>
                                <span v-else class="text-amber-700">
                                    · {{ t('settings.fingerprint_day_lookup_no_pin') }}
                                </span>
                            </div>
                        </div>

                        <div class="grid gap-2 max-w-xs">
                            <Label for="punch-date">{{ t('settings.fingerprint_day_lookup_date') }}</Label>
                            <Input id="punch-date" v-model="selectedDate" type="date" />
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <Button type="button" :disabled="loadingLookup" @click="runLookup(false)">
                                {{
                                    loadingLookup
                                        ? t('settings.fingerprint_day_lookup_loading')
                                        : t('settings.fingerprint_day_lookup_submit')
                                }}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                :disabled="loadingApi || !selectedEmployee"
                                @click="runLookup(true)"
                            >
                                {{
                                    loadingApi
                                        ? t('settings.fingerprint_day_lookup_loading_api')
                                        : t('settings.fingerprint_day_lookup_from_api')
                                }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <template v-if="lookup">
                    <Card>
                        <CardHeader>
                            <CardTitle>{{ t('settings.fingerprint_day_lookup_summary') }}</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3 text-sm">
                            <div class="flex flex-wrap gap-4">
                                <div class="rounded-md border bg-muted/30 px-3 py-2">
                                    <span class="font-medium">{{ t('settings.fingerprint_day_lookup_raw_count') }}:</span>
                                    {{ lookup.punches.length }}
                                </div>
                                <div v-if="lookup.daily_summary" class="rounded-md border bg-muted/30 px-3 py-2">
                                    <span class="font-medium">{{ t('settings.fingerprint_day_lookup_daily_count') }}:</span>
                                    {{ lookup.daily_summary.punch_count }}
                                </div>
                                <div v-if="lookup.presentation" class="rounded-md border bg-muted/30 px-3 py-2">
                                    <span class="font-medium">{{ t('settings.fingerprint_day_lookup_status') }}:</span>
                                    {{ lookup.presentation.status_ar || '—' }}
                                    <span v-if="lookup.presentation.late_minutes != null">
                                        · {{ lookup.presentation.late_minutes }} {{ t('settings.fingerprint_day_lookup_late_minutes') }}
                                    </span>
                                </div>
                            </div>

                            <div v-if="lookup.daily_summary" class="text-muted-foreground">
                                {{ t('settings.fingerprint_day_lookup_first') }}:
                                {{ lookup.daily_summary.first_punch || '—' }}
                                ·
                                {{ t('settings.fingerprint_day_lookup_last') }}:
                                {{ lookup.daily_summary.last_punch || '—' }}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{{ t('settings.fingerprint_day_lookup_raw_title') }}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div v-if="!lookup.punches.length" class="text-sm text-muted-foreground">
                                {{ t('settings.fingerprint_day_lookup_raw_empty') }}
                            </div>
                            <div v-else class="overflow-x-auto rounded-md border">
                                <table class="w-full text-sm">
                                    <thead class="bg-muted/50">
                                        <tr>
                                            <th class="px-3 py-2 text-start">#</th>
                                            <th class="px-3 py-2 text-start">{{ t('settings.fingerprint_day_lookup_col_time') }}</th>
                                            <th class="px-3 py-2 text-start">{{ t('settings.fingerprint_day_lookup_col_device') }}</th>
                                            <th class="px-3 py-2 text-start">{{ t('settings.fingerprint_day_lookup_col_verify') }}</th>
                                            <th class="px-3 py-2 text-start">{{ t('settings.fingerprint_day_lookup_col_source') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="(punch, idx) in lookup.punches"
                                            :key="punch.id ?? `raw-${idx}`"
                                            class="border-t"
                                        >
                                            <td class="px-3 py-2">{{ idx + 1 }}</td>
                                            <td class="px-3 py-2 font-medium">{{ punch.punch_time_riyadh }}</td>
                                            <td class="px-3 py-2">
                                                {{ punch.device_name || punch.serial_number || '—' }}
                                            </td>
                                            <td class="px-3 py-2">{{ punch.verify_mode ?? '—' }}</td>
                                            <td class="px-3 py-2">{{ punch.source }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>

                    <Card v-if="lookup.api_punches">
                        <CardHeader>
                            <CardTitle>{{ t('settings.fingerprint_day_lookup_api_title') }}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div v-if="!lookup.api_punches.length" class="text-sm text-muted-foreground">
                                {{ t('settings.fingerprint_day_lookup_api_empty') }}
                            </div>
                            <div v-else class="overflow-x-auto rounded-md border">
                                <table class="w-full text-sm">
                                    <thead class="bg-muted/50">
                                        <tr>
                                            <th class="px-3 py-2 text-start">#</th>
                                            <th class="px-3 py-2 text-start">{{ t('settings.fingerprint_day_lookup_col_time') }}</th>
                                            <th class="px-3 py-2 text-start">{{ t('settings.fingerprint_day_lookup_col_source') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="(punch, idx) in lookup.api_punches"
                                            :key="`api-${idx}`"
                                            class="border-t"
                                        >
                                            <td class="px-3 py-2">{{ idx + 1 }}</td>
                                            <td class="px-3 py-2 font-medium">{{ punch.punch_time_riyadh }}</td>
                                            <td class="px-3 py-2">{{ punch.source }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                </template>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>

<script setup lang="ts">
import PageHeader from '@/components/PageHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed, reactive, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import {
    CalendarClock,
    ArrowUpRight,
    AlertTriangle,
    ShieldAlert,
    CalendarDays,
    FileText,
    Wallet,
    Banknote,
    Inbox,
    ChevronDown,
    ChevronUp,
} from 'lucide-vue-next';

const { t } = useI18n();

interface ExpiringEmployeeRow {
    employee_id: number;
    display_name: string;
    full_name: string;
    expiry_field: string;
    expiry_label_key: string;
    expiry_date: string;
    days_remaining: number;
}

interface PendingItem {
    id: number;
    type: string;
    title: string;
    subtitle: string;
    meta: string;
    step_title?: string | null;
    url: string;
    created_at?: string | null;
}

interface PendingBucket {
    visible: boolean;
    count: number;
    preview: PendingItem[];
    view_all_url?: string | null;
}

interface PendingApprovals {
    leaves: PendingBucket;
    salary_certificates: PendingBucket;
    entitlement_settlements: PendingBucket;
    salary_runs: PendingBucket;
    total_count: number;
}

interface Props {
    canViewExpiryDocuments?: boolean;
    expiringEmployeesPreview: ExpiringEmployeeRow[];
    expiredEmployeesPreview: ExpiringEmployeeRow[];
    expiringEmployeesCount: number;
    expiredEmployeesCount: number;
    expiryDaysThreshold: number;
    pendingApprovals?: PendingApprovals | null;
}

const props = withDefaults(defineProps<Props>(), {
    canViewExpiryDocuments: false,
    pendingApprovals: null,
});

const breadcrumbs = computed((): BreadcrumbItem[] => []);

const canViewExpiry = computed(() => props.canViewExpiryDocuments === true);

const emptyBucket = (): PendingBucket => ({
    visible: false,
    count: 0,
    preview: [],
    view_all_url: null,
});

const pending = computed((): PendingApprovals => props.pendingApprovals ?? {
    leaves: emptyBucket(),
    salary_certificates: emptyBucket(),
    entitlement_settlements: emptyBucket(),
    salary_runs: emptyBucket(),
    total_count: 0,
});

const pendingSections = computed(() => [
    {
        key: 'leaves',
        title: t('dashboard.pending.leaves'),
        icon: CalendarDays,
        bucket: pending.value.leaves,
        tone: 'blue',
    },
    {
        key: 'salary_certificates',
        title: t('dashboard.pending.salary_certificates'),
        icon: FileText,
        bucket: pending.value.salary_certificates,
        tone: 'violet',
    },
    {
        key: 'entitlement_settlements',
        title: t('dashboard.pending.entitlement_settlements'),
        icon: Wallet,
        bucket: pending.value.entitlement_settlements,
        tone: 'amber',
    },
    {
        key: 'salary_runs',
        title: t('dashboard.pending.salary_runs'),
        icon: Banknote,
        bucket: pending.value.salary_runs,
        tone: 'emerald',
    },
].filter((section) => section.bucket.visible));

const hasPendingSections = computed(() => pendingSections.value.length > 0);

const openPendingSections = reactive<Record<string, boolean>>({});

watch(
    pendingSections,
    (sections) => {
        for (const section of sections) {
            if (openPendingSections[section.key] === undefined) {
                openPendingSections[section.key] = true;
            }
        }
    },
    { immediate: true },
);

function isPendingSectionOpen(key: string): boolean {
    return openPendingSections[key] !== false;
}

function togglePendingSection(key: string): void {
    openPendingSections[key] = !isPendingSectionOpen(key);
}

const summaryToneClass = (tone: string) => {
    const map: Record<string, string> = {
        blue: 'border-blue-200 bg-blue-50/60 text-blue-700 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-300',
        violet: 'border-violet-200 bg-violet-50/60 text-violet-700 dark:border-violet-900 dark:bg-violet-950/30 dark:text-violet-300',
        amber: 'border-amber-200 bg-amber-50/60 text-amber-700 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300',
        emerald: 'border-emerald-200 bg-emerald-50/60 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300',
    };

    return map[tone] ?? map.blue;
};

const iconToneClass = (tone: string) => {
    const map: Record<string, string> = {
        blue: 'bg-blue-100 text-blue-600 dark:bg-blue-900/40 dark:text-blue-300',
        violet: 'bg-violet-100 text-violet-600 dark:bg-violet-900/40 dark:text-violet-300',
        amber: 'bg-amber-100 text-amber-600 dark:bg-amber-900/40 dark:text-amber-300',
        emerald: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-300',
    };

    return map[tone] ?? map.blue;
};

const getToneIcon = () => CalendarClock;

const formatRemainingText = (daysRemaining: number) => {
    if (daysRemaining < 0) {
        const days = Math.abs(daysRemaining);
        return t('employees.expiry.expired_days_ago', { days });
    }

    if (daysRemaining === 0) {
        return t('employees.expiry.expires_today');
    }

    return t('employees.expiry.days_remaining', { days: daysRemaining });
};
</script>

<template>
    <Head :title="t('nav.dashboard')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageHeader
            :title="t('nav.dashboard')"
            :description="hasPendingSections ? t('dashboard.pending.section_description') : t('employees.expiry.upcoming_expirations')"
        >
            <template v-if="canViewExpiry && (expiringEmployeesCount > 0 || expiredEmployeesCount > 0)" #actions>
                <Button asChild variant="outline" size="sm">
                    <Link :href="route('employees.expiring-documents.index')">
                        {{ t('employees.expiry.view_all') }} ({{ expiringEmployeesCount + expiredEmployeesCount }})
                        <ArrowUpRight class="ms-2 size-4" />
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <!-- Pending approvals summary + lists -->
        <div v-if="hasPendingSections" class="mb-6 space-y-6">
            <div>
                <h2 class="mb-1 text-lg font-semibold tracking-tight">{{ t('dashboard.pending.section_title') }}</h2>
                <p class="text-sm text-muted-foreground">{{ t('dashboard.pending.section_description') }}</p>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <Card
                    v-for="section in pendingSections"
                    :key="'summary-' + section.key"
                    class="cursor-pointer border transition-opacity hover:opacity-90"
                    :class="summaryToneClass(section.tone)"
                    @click="togglePendingSection(section.key)"
                >
                    <CardContent class="flex items-center justify-between gap-3 p-4">
                        <div class="min-w-0 space-y-1">
                            <p class="text-xs font-medium opacity-80">{{ section.title }}</p>
                            <p class="text-2xl font-bold tabular-nums">{{ section.bucket.count }}</p>
                            <p class="text-[11px] opacity-70">{{ t('dashboard.pending.awaiting_you') }}</p>
                        </div>
                        <div class="flex flex-col items-end gap-2">
                            <div class="rounded-lg p-2.5" :class="iconToneClass(section.tone)">
                                <component :is="section.icon" class="size-5" />
                            </div>
                            <component
                                :is="isPendingSectionOpen(section.key) ? ChevronUp : ChevronDown"
                                class="size-4 opacity-70"
                            />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <template v-for="section in pendingSections" :key="'list-' + section.key">
                <Card v-if="section.bucket.count > 0">
                    <CardHeader class="border-b border-border/60 bg-muted/40 px-5 py-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <CardTitle class="flex items-center gap-2 text-base font-semibold text-foreground">
                                <component :is="section.icon" class="size-4" />
                                {{ section.title }}
                                <Badge variant="outline" class="ms-1 font-normal tabular-nums">
                                    {{ section.bucket.count }}
                                </Badge>
                            </CardTitle>
                            <Button
                                variant="outline"
                                size="sm"
                                type="button"
                                @click="togglePendingSection(section.key)"
                            >
                                {{
                                    isPendingSectionOpen(section.key)
                                        ? t('dashboard.pending.collapse')
                                        : t('dashboard.pending.expand')
                                }}
                                <component
                                    :is="isPendingSectionOpen(section.key) ? ChevronUp : ChevronDown"
                                    class="ms-2 size-4"
                                />
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent v-show="isPendingSectionOpen(section.key)" class="p-5">
                        <div class="grid gap-3 md:grid-cols-1 lg:grid-cols-2">
                            <Card
                                v-for="item in section.bucket.preview"
                                :key="section.key + '-' + item.id"
                                class="group cursor-pointer rounded-lg border border-gray-200 bg-white transition-colors duration-150 hover:border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-gray-600"
                                @click="$inertia.visit(item.url)"
                            >
                                <CardContent class="p-5">
                                    <div class="flex items-start justify-between gap-5">
                                        <div class="min-w-0 flex-1 space-y-3">
                                            <div class="flex items-start gap-3">
                                                <div class="flex-shrink-0 rounded p-2" :class="iconToneClass(section.tone)">
                                                    <component :is="section.icon" class="h-4 w-4" />
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <h3 class="mb-1 text-sm font-medium leading-tight text-gray-900 dark:text-gray-100">
                                                        {{ item.title }}
                                                    </h3>
                                                    <p class="text-xs text-gray-600 dark:text-gray-400">
                                                        {{ item.subtitle }}
                                                    </p>
                                                </div>
                                            </div>

                                            <div class="space-y-1 pl-9 text-xs text-gray-500 dark:text-gray-500">
                                                <p>{{ item.meta }}</p>
                                                <p v-if="item.step_title">
                                                    <span class="font-medium text-gray-700 dark:text-gray-300">
                                                        {{ t('dashboard.pending.current_step') }}:
                                                    </span>
                                                    {{ item.step_title }}
                                                </p>
                                            </div>
                                        </div>

                                        <div class="flex flex-shrink-0 flex-col items-end gap-2">
                                            <Badge
                                                variant="outline"
                                                class="rounded border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-normal text-amber-700 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300"
                                            >
                                                {{ t('dashboard.pending.awaiting_you') }}
                                            </Badge>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                asChild
                                                @click.stop
                                                class="h-7 rounded px-3 text-xs font-normal"
                                            >
                                                <Link :href="item.url">
                                                    {{ t('dashboard.pending.view_request') }}
                                                </Link>
                                            </Button>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </CardContent>
                </Card>
            </template>

            <Card v-if="pending.total_count === 0">
                <CardContent class="py-10 text-center">
                    <div class="flex flex-col items-center gap-2.5">
                        <div class="rounded bg-gray-100 p-3 dark:bg-gray-800">
                            <Inbox class="h-6 w-6 text-gray-400 dark:text-gray-500" />
                        </div>
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('dashboard.pending.no_items') }}
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Expired Documents Section -->
        <Card v-if="canViewExpiry && expiredEmployeesPreview && expiredEmployeesPreview.length > 0" class="mb-6">
            <CardHeader class="border-b border-border/60 bg-muted/40 px-5 py-4">
                <CardTitle class="flex items-center gap-2 text-base font-semibold text-foreground">
                    <AlertTriangle class="size-4 text-destructive" />
                    {{ t('employees.expiry.expired_documents') }}
                </CardTitle>
            </CardHeader>
            <CardContent class="p-5">
                <div class="grid gap-3 md:grid-cols-1 lg:grid-cols-2">
                    <Card
                        v-for="row in expiredEmployeesPreview"
                        :key="'expired-' + row.employee_id + '-' + row.expiry_field"
                        class="group cursor-pointer rounded-lg border border-gray-200 bg-white transition-colors duration-150 hover:border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-gray-600"
                        @click="$inertia.visit(route('employees.show', row.employee_id))"
                    >
                        <CardContent class="p-5">
                            <div class="flex items-start justify-between gap-5">
                                <div class="min-w-0 flex-1 space-y-3">
                                    <div class="flex items-start gap-3">
                                        <div class="flex-shrink-0 rounded bg-gray-100 p-2 dark:bg-gray-800">
                                            <component
                                                :is="getToneIcon()"
                                                class="h-4 w-4 text-gray-500 dark:text-gray-400"
                                            />
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <h3 class="mb-1 text-sm font-medium leading-tight text-gray-900 dark:text-gray-100">
                                                {{ row.display_name || row.full_name }}
                                            </h3>
                                            <p class="text-xs text-gray-600 dark:text-gray-400">
                                                {{ t(row.expiry_label_key) }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-1.5 pl-9 text-xs text-gray-500 dark:text-gray-500">
                                        <CalendarClock class="h-3.5 w-3.5 text-gray-400" />
                                        <span>{{ t('employees.expiry.expiry_date') }}:</span>
                                        <span class="font-medium text-gray-700 dark:text-gray-300">
                                            {{ new Date(row.expiry_date).toLocaleDateString() }}
                                        </span>
                                    </div>
                                </div>

                                <div class="flex flex-shrink-0 flex-col items-end gap-2">
                                    <Badge
                                        variant="outline"
                                        class="rounded border-gray-300 bg-gray-50 px-2 py-0.5 text-[10px] font-normal text-gray-600 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400"
                                    >
                                        {{ formatRemainingText(row.days_remaining) }}
                                    </Badge>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        asChild
                                        @click.stop
                                        class="h-7 rounded border-gray-300 px-3 text-xs font-normal text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                                    >
                                        <Link :href="route('employees.show', row.employee_id)">
                                            {{ t('employees.view') }}
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </CardContent>
        </Card>

        <!-- Upcoming Expirations Section -->
        <Card v-if="canViewExpiry">
            <CardHeader class="border-b border-border/60 bg-muted/40 px-5 py-4">
                <CardTitle class="text-base font-semibold text-foreground">
                    {{ t('employees.expiry.upcoming_expirations') }}
                </CardTitle>
            </CardHeader>
            <CardContent class="p-5">
                <div
                    v-if="!expiringEmployeesPreview || expiringEmployeesPreview.length === 0"
                    class="py-10 text-center"
                >
                    <div class="flex flex-col items-center gap-2.5">
                        <div class="rounded bg-gray-100 p-3 dark:bg-gray-800">
                            <ShieldAlert class="h-6 w-6 text-gray-400 dark:text-gray-500" />
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('employees.expiry.no_upcoming', { days: expiryDaysThreshold }) }}
                            </p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-500">
                                {{ t('employees.expiry.all_documents_valid') }}
                            </p>
                        </div>
                    </div>
                </div>

                <div v-else class="grid gap-3 md:grid-cols-1 lg:grid-cols-2">
                    <Card
                        v-for="row in expiringEmployeesPreview"
                        :key="row.employee_id + '-' + row.expiry_field"
                        class="group cursor-pointer rounded-lg border border-gray-200 bg-white transition-colors duration-150 hover:border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-gray-600"
                        @click="$inertia.visit(route('employees.show', row.employee_id))"
                    >
                        <CardContent class="p-5">
                            <div class="flex items-start justify-between gap-5">
                                <div class="min-w-0 flex-1 space-y-3">
                                    <div class="flex items-start gap-3">
                                        <div class="flex-shrink-0 rounded bg-gray-100 p-2 dark:bg-gray-800">
                                            <component
                                                :is="getToneIcon()"
                                                class="h-4 w-4 text-gray-500 dark:text-gray-400"
                                            />
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <h3 class="mb-1 text-sm font-medium leading-tight text-gray-900 dark:text-gray-100">
                                                {{ row.display_name || row.full_name }}
                                            </h3>
                                            <p class="text-xs text-gray-600 dark:text-gray-400">
                                                {{ t(row.expiry_label_key) }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-1.5 pl-9 text-xs text-gray-500 dark:text-gray-500">
                                        <CalendarClock class="h-3.5 w-3.5 text-gray-400" />
                                        <span>{{ t('employees.expiry.expiry_date') }}:</span>
                                        <span class="font-medium text-gray-700 dark:text-gray-300">
                                            {{ new Date(row.expiry_date).toLocaleDateString() }}
                                        </span>
                                    </div>
                                </div>

                                <div class="flex flex-shrink-0 flex-col items-end gap-2">
                                    <Badge
                                        variant="outline"
                                        class="rounded border-gray-300 bg-gray-50 px-2 py-0.5 text-[10px] font-normal text-gray-600 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400"
                                    >
                                        {{ formatRemainingText(row.days_remaining) }}
                                    </Badge>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        asChild
                                        @click.stop
                                        class="h-7 rounded border-gray-300 px-3 text-xs font-normal text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                                    >
                                        <Link :href="route('employees.show', row.employee_id)">
                                            {{ t('employees.view') }}
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </CardContent>
        </Card>
    </AppLayout>
</template>

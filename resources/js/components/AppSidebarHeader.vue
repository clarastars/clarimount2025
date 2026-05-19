<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import NotificationBell from '@/components/NotificationBell.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { router, usePage } from '@inertiajs/vue3';
import { Search, User } from 'lucide-vue-next';
import type { BreadcrumbItemType } from '@/types';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItemType[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

interface SearchEmployeeResult {
    id: number;
    full_name: string;
    employee_id?: string | null;
    company_name?: string | null;
    employment_status?: string | null;
}

const page = usePage();
const authProps = computed(() => (page.props.auth as {
    is_employee?: boolean;
    can_use_employee_global_search?: boolean;
}) ?? {});
const uiProps = computed(() => (page.props.ui as { show_employee_global_search?: boolean }) ?? {});
const showGlobalSearch = computed(() =>
    (uiProps.value.show_employee_global_search ?? true)
    && (authProps.value.can_use_employee_global_search ?? false),
);

const searchQuery = ref('');
const suggestions = ref<SearchEmployeeResult[]>([]);
const openSuggestions = ref(false);
const loading = ref(false);
const activeIndex = ref(-1);
const searchWrapRef = ref<HTMLElement | null>(null);

let searchTimer: ReturnType<typeof setTimeout> | null = null;

const performSearch = async (query: string): Promise<void> => {
    const trimmed = query.trim();
    if (trimmed.length < 2) {
        suggestions.value = [];
        openSuggestions.value = false;
        activeIndex.value = -1;
        return;
    }

    loading.value = true;
    try {
        const response = await fetch(`/api/employees/global-search?q=${encodeURIComponent(trimmed)}`, {
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            suggestions.value = [];
            openSuggestions.value = false;
            return;
        }

        const data = (await response.json()) as SearchEmployeeResult[];
        suggestions.value = Array.isArray(data) ? data : [];
        openSuggestions.value = suggestions.value.length > 0;
        activeIndex.value = suggestions.value.length > 0 ? 0 : -1;
    } catch {
        suggestions.value = [];
        openSuggestions.value = false;
    } finally {
        loading.value = false;
    }
};

watch(searchQuery, (newValue: string) => {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }
    searchTimer = setTimeout(() => {
        void performSearch(newValue);
    }, 250);
});

const selectEmployee = (employee: SearchEmployeeResult): void => {
    searchQuery.value = employee.full_name;
    suggestions.value = [];
    openSuggestions.value = false;
    activeIndex.value = -1;
    router.visit(`/employees/${employee.id}`);
};

const onSearchKeydown = (event: KeyboardEvent): void => {
    if (!openSuggestions.value || suggestions.value.length === 0) {
        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        activeIndex.value = (activeIndex.value + 1) % suggestions.value.length;
        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        activeIndex.value = (activeIndex.value - 1 + suggestions.value.length) % suggestions.value.length;
        return;
    }

    if (event.key === 'Enter') {
        event.preventDefault();
        const target = suggestions.value[activeIndex.value] ?? suggestions.value[0];
        if (target) {
            selectEmployee(target);
        }
        return;
    }

    if (event.key === 'Escape') {
        openSuggestions.value = false;
        activeIndex.value = -1;
    }
};

const closeSuggestionsOnOutsideClick = (event: MouseEvent): void => {
    if (!searchWrapRef.value) {
        return;
    }
    const target = event.target as Node | null;
    if (target && !searchWrapRef.value.contains(target)) {
        openSuggestions.value = false;
        activeIndex.value = -1;
    }
};

document.addEventListener('click', closeSuggestionsOnOutsideClick);

onBeforeUnmount(() => {
    document.removeEventListener('click', closeSuggestionsOnOutsideClick);
    if (searchTimer) {
        clearTimeout(searchTimer);
    }
});
</script>

<template>
    <header
        class="flex min-h-12 shrink-0 flex-col gap-2 border-b bg-background px-3 py-2 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:md:h-12 md:grid md:h-16 md:grid-cols-[1fr_minmax(0,36rem)_1fr] md:items-center md:gap-3 md:px-4 md:py-0 lg:px-6"
    >
        <div class="flex min-h-9 min-w-0 items-center gap-2 md:min-h-0">
            <SidebarTrigger class="-ms-1 shrink-0" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <div
            v-if="showGlobalSearch"
            ref="searchWrapRef"
            class="relative w-full min-w-0 max-w-none md:max-w-xl md:justify-self-center"
        >
            <div class="relative w-full">
                <Search
                    class="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <input
                    v-model="searchQuery"
                    type="search"
                    enterkeyhint="search"
                    autocomplete="off"
                    class="h-10 w-full min-w-0 rounded-md border border-input bg-background py-2 ps-10 pe-10 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    :placeholder="$t('settings.employee_global_search_placeholder')"
                    @focus="searchQuery.trim().length >= 2 && suggestions.length > 0 ? openSuggestions = true : null"
                    @keydown="onSearchKeydown"
                >
                <span
                    v-if="loading"
                    class="absolute end-3 top-1/2 -translate-y-1/2 text-xs text-muted-foreground"
                >
                    ...
                </span>
            </div>

            <div
                v-if="openSuggestions"
                class="absolute z-50 mt-1 max-h-[min(20rem,70vh)] w-full overflow-auto rounded-md border bg-popover shadow-lg"
            >
                <button
                    v-for="(employee, index) in suggestions"
                    :key="employee.id"
                    type="button"
                    class="flex w-full cursor-pointer items-start gap-2 px-3 py-2 text-start hover:bg-accent"
                    :class="index === activeIndex ? 'bg-accent' : ''"
                    @mouseenter="activeIndex = index"
                    @click="selectEmployee(employee)"
                >
                    <User class="mt-0.5 h-4 w-4 text-muted-foreground" />
                    <div class="min-w-0">
                        <div class="truncate text-sm font-medium">{{ employee.full_name }}</div>
                        <div class="truncate text-xs text-muted-foreground">
                            <span v-if="employee.employee_id">#{{ employee.employee_id }}</span>
                            <span v-if="employee.company_name"> - {{ employee.company_name }}</span>
                        </div>
                    </div>
                </button>
            </div>
        </div>

        <div class="flex shrink-0 items-center justify-end gap-2 md:justify-self-end">
            <NotificationBell />
        </div>

    </header>
</template>

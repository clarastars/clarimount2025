<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
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
const authProps = computed(() => (page.props.auth as { is_employee?: boolean }) ?? {});
const uiProps = computed(() => (page.props.ui as { show_employee_global_search?: boolean }) ?? {});
const showGlobalSearch = computed(() => (uiProps.value.show_employee_global_search ?? true) && !authProps.value.is_employee);

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
        class="sticky top-0 z-30 grid h-16 shrink-0 grid-cols-[1fr_auto_1fr] items-center gap-3 border-b bg-background/95 px-6 backdrop-blur transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex items-center gap-2 min-w-0">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <div
            v-if="showGlobalSearch"
            ref="searchWrapRef"
            class="relative w-full min-w-[320px] max-w-xl justify-self-center"
        >
            <div class="relative">
                <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <input
                    v-model="searchQuery"
                    type="text"
                    class="h-10 w-full rounded-md border border-input bg-background px-10 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    :placeholder="$t('settings.employee_global_search_placeholder')"
                    @focus="searchQuery.trim().length >= 2 && suggestions.length > 0 ? openSuggestions = true : null"
                    @keydown="onSearchKeydown"
                >
                <span
                    v-if="loading"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-muted-foreground"
                >
                    ...
                </span>
            </div>

            <div
                v-if="openSuggestions"
                class="absolute mt-1 max-h-80 w-full overflow-auto rounded-md border bg-popover shadow-lg"
            >
                <button
                    v-for="(employee, index) in suggestions"
                    :key="employee.id"
                    type="button"
                    class="flex w-full cursor-pointer items-start gap-2 px-3 py-2 text-left hover:bg-accent"
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

        <div class="justify-self-end" />

    </header>
</template>

<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { useI18n } from 'vue-i18n'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import Icon from '@/components/Icon.vue'
import type { BreadcrumbItem } from '@/types'

const { t } = useI18n()

interface Shift {
    id: number
    name: string
    start_time: string
    end_time: string
    grace_minutes: number
    employees_count: number
}

interface Props {
    shifts: {
        data: Shift[]
        links: any[]
        meta: any
    }
    filters?: { search?: string }
}

const props = defineProps<Props>()

const breadcrumbs = computed((): BreadcrumbItem[] => [
    { title: t('nav.dashboard'), href: '/dashboard' },
    { title: t('nav.settings'), href: '#' },
    { title: t('nav.shifts'), href: '/shifts' },
])

const search = ref(props.filters?.search || '')

let searchTimeout: number
watch(search, () => {
    clearTimeout(searchTimeout)
    searchTimeout = window.setTimeout(() => {
        router.get(route('shifts.index'), { search: search.value || undefined }, { preserveState: true, replace: true })
    }, 300)
})

function formatTime(time: string) {
    if (!time) return '-'
    const s = String(time)
    const tIndex = s.indexOf('T')
    if (tIndex !== -1) return s.substring(tIndex + 1, tIndex + 6)
    if (s.length >= 5 && /^\d{2}:\d{2}/.test(s)) return s.substring(0, 5)
    return s
}

const deleteShift = (shift: Shift) => {
    if (shift.employees_count > 0) {
        alert(t('shifts.cannot_delete_has_employees', { count: shift.employees_count }))
        return
    }
    if (confirm(t('shifts.confirm_delete'))) {
        router.delete(route('shifts.destroy', shift.id), { preserveScroll: true })
    }
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                            {{ t('shifts.title') }}
                        </h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            {{ t('shifts.description') }}
                        </p>
                    </div>
                    <Button @click="router.visit(route('shifts.create'))" class="gap-2">
                        <Icon name="Plus" class="w-4 h-4" />
                        {{ t('shifts.create_shift') }}
                    </Button>
                </div>

                <Card class="mb-6">
                    <CardContent class="p-4">
                        <div class="relative max-w-xs">
                            <div class="absolute inset-y-0 left-0 rtl:left-auto rtl:right-0 pl-3 rtl:pl-0 rtl:pr-3 flex items-center pointer-events-none">
                                <Icon name="Search" class="h-4 w-4 text-gray-400" />
                            </div>
                            <Input
                                v-model="search"
                                :placeholder="t('shifts.search_placeholder')"
                                class="w-full pl-10 rtl:pl-3 rtl:pr-10"
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card v-if="shifts?.data?.length > 0">
                    <CardContent class="p-0">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-6 py-4 text-start text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                            {{ t('shifts.name') }}
                                        </th>
                                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                            {{ t('shifts.start_time') }}
                                        </th>
                                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                            {{ t('shifts.end_time') }}
                                        </th>
                                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                            {{ t('shifts.grace_minutes') }}
                                        </th>
                                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                            {{ t('shifts.employees_count') }}
                                        </th>
                                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                            {{ t('common.actions') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-for="shift in shifts.data" :key="shift.id" class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                        <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">
                                            {{ shift.name }}
                                        </td>
                                        <td class="px-6 py-4 text-center text-sm text-gray-600 dark:text-gray-400">
                                            {{ formatTime(shift.start_time) }}
                                        </td>
                                        <td class="px-6 py-4 text-center text-sm text-gray-600 dark:text-gray-400">
                                            {{ formatTime(shift.end_time) }}
                                        </td>
                                        <td class="px-6 py-4 text-center text-sm text-gray-600 dark:text-gray-400">
                                            {{ shift.grace_minutes ?? 0 }}
                                        </td>
                                        <td class="px-6 py-4 text-center text-sm text-gray-600 dark:text-gray-400">
                                            {{ shift.employees_count ?? 0 }}
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex justify-center gap-2">
                                                <Button
                                                    @click="router.visit(route('shifts.edit', shift.id))"
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    {{ t('common.edit') }}
                                                </Button>
                                                <Button
                                                    @click="deleteShift(shift)"
                                                    variant="destructive"
                                                    size="sm"
                                                    :disabled="(shift.employees_count ?? 0) > 0"
                                                >
                                                    {{ t('common.delete') }}
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-if="shifts.links && shifts.links.length > 3" class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center">
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                {{ t('common.showing') }} <span class="font-medium">{{ shifts.meta?.from ?? 0 }}</span> {{ t('common.to') }} <span class="font-medium">{{ shifts.meta?.to ?? 0 }}</span> {{ t('common.of') }} <span class="font-medium">{{ shifts.meta?.total ?? 0 }}</span> {{ t('common.results') }}
                            </span>
                            <div class="flex gap-1">
                                <Button
                                    v-for="link in shifts.links"
                                    :key="link.label"
                                    @click="link.url && router.visit(link.url)"
                                    :variant="link.active ? 'default' : 'outline'"
                                    size="sm"
                                    :disabled="!link.url"
                                    v-html="link.label"
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card v-else>
                    <CardContent class="p-12 text-center">
                        <Icon name="Clock" class="w-12 h-12 text-gray-400 mx-auto mb-4" />
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">{{ t('shifts.no_shifts') }}</h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-6">{{ t('shifts.no_shifts_description') }}</p>
                        <Button @click="router.visit(route('shifts.create'))">{{ t('shifts.create_shift') }}</Button>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>

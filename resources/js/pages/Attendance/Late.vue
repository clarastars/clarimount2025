<template>
  <AppLayout :breadcrumbs="breadcrumbs">
    <template #header>
      <div class="flex justify-between items-center">
        <div>
          <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
            {{ $t('attendance.late_title') }}
          </h2>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ $t('attendance.late_description') }}
          </p>
        </div>
      </div>
    </template>

    <div class="py-12 w-full">
      <div class="w-full px-4 sm:px-6 lg:px-8">
        <!-- Filters Section -->
        <Card class="mb-6">
          <CardHeader>
            <CardTitle>{{ $t('common.filters') }}</CardTitle>
          </CardHeader>
          <CardContent>
            <form @submit.prevent="applyFilters" class="space-y-4">
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Filter Type -->
                <div>
                  <Label for="filter-type" class="mb-2 block">{{ $t('attendance.filter_period') }}</Label>
                  <select
                    id="filter-type"
                    v-model="filterType"
                    @change="onFilterTypeChange"
                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                  >
                    <option value="today">{{ $t('attendance.filter_today') }}</option>
                    <option value="week">{{ $t('attendance.filter_week') }}</option>
                    <option value="month">{{ $t('attendance.filter_month') }}</option>
                    <option value="custom">{{ $t('attendance.filter_custom') }}</option>
                  </select>
                </div>

                <!-- From Date (shown when custom) -->
                <div v-if="filterType === 'custom'">
                  <Label for="from-date" class="mb-2 block">{{ $t('attendance.from_date') }}</Label>
                  <Input
                    id="from-date"
                    v-model="fromDate"
                    type="date"
                    class="w-full"
                  />
                </div>

                <!-- To Date (shown when custom) -->
                <div v-if="filterType === 'custom'">
                  <Label for="to-date" class="mb-2 block">{{ $t('attendance.to_date') }}</Label>
                  <Input
                    id="to-date"
                    v-model="toDate"
                    type="date"
                    class="w-full"
                  />
                </div>

                <!-- Search -->
                <div>
                  <Label for="search-query" class="mb-2 block">{{ $t('common.search') }}</Label>
                  <div class="relative">
                    <div class="absolute inset-y-0 left-0 rtl:left-auto rtl:right-0 pl-3 rtl:pl-0 rtl:pr-3 flex items-center pointer-events-none">
                      <Icon name="Search" class="h-4 w-4 text-gray-400" />
                    </div>
                    <Input
                      id="search-query"
                      v-model="searchQuery"
                      :placeholder="$t('attendance.search_employee')"
                      class="w-full pl-10 rtl:pl-3 rtl:pr-10"
                    />
                  </div>
                </div>
              </div>

              <div class="flex gap-2">
                <Button type="submit" variant="default">
                  {{ $t('common.apply') }}
                </Button>
                <Button type="button" @click="resetFilters" variant="outline">
                  {{ $t('common.reset') }}
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
          <Card>
            <CardContent class="p-6">
              <div class="flex items-center">
                <div class="p-2 bg-red-100 rounded-lg dark:bg-red-900/20">
                  <AlertTriangle class="w-6 h-6 text-red-600 dark:text-red-400" />
                </div>
                <div class="ml-4">
                  <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                    {{ $t('attendance.total_late_records') }}
                  </p>
                  <p class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ stats?.total_late_records || 0 }}
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent class="p-6">
              <div class="flex items-center">
                <div class="p-2 bg-orange-100 rounded-lg dark:bg-orange-900/20">
                  <Clock class="w-6 h-6 text-orange-600 dark:text-orange-400" />
                </div>
                <div class="ml-4">
                  <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                    {{ $t('attendance.total_late_minutes') }}
                  </p>
                  <p class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ stats?.total_late_minutes || 0 }}
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent class="p-6">
              <div class="flex items-center">
                <div class="p-2 bg-yellow-100 rounded-lg dark:bg-yellow-900/20">
                  <Clock class="w-6 h-6 text-yellow-600 dark:text-yellow-400" />
                </div>
                <div class="ml-4">
                  <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                    {{ $t('attendance.average_late_minutes') }}
                  </p>
                  <p class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ Math.round(stats?.average_late_minutes || 0) }}
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        <!-- Late Attendance Table -->
        <Card>
          <CardHeader>
            <CardTitle class="flex items-center gap-2">
              <AlertTriangle class="w-5 h-5 text-red-600" />
              {{ $t('attendance.late_records') }}
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div v-if="lateAttendance && lateAttendance.data && lateAttendance.data.length > 0">
              <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                  <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                      <th class="px-6 py-4 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider border-r border-gray-200 dark:border-gray-700">
                        {{ $t('attendance.employee_name') }}
                      </th>
                      <th class="px-6 py-4 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider border-r border-gray-200 dark:border-gray-700">
                        {{ $t('attendance.date') }}
                      </th>
                      <th class="px-6 py-4 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider border-r border-gray-200 dark:border-gray-700">
                        {{ $t('attendance.device_pin') }}
                      </th>
                      <th class="px-6 py-4 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider border-r border-gray-200 dark:border-gray-700">
                        {{ $t('attendance.check_in') }}
                      </th>
                      <th class="px-6 py-4 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider border-r border-gray-200 dark:border-gray-700">
                        {{ $t('attendance.check_out') }}
                      </th>
                      <th class="px-6 py-4 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider border-r border-gray-200 dark:border-gray-700">
                        {{ $t('attendance.attendance_late_minutes') }}
                      </th>
                      <th class="px-6 py-4 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                        {{ $t('attendance.device_name') }}
                      </th>
                    </tr>
                  </thead>
                  <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                    <tr v-for="record in lateAttendance.data" :key="record.id" class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                      <td class="px-6 py-4 text-center border-r border-gray-200 dark:border-gray-700">
                        <div class="flex flex-col items-center justify-center">
                          <div class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ getEmployeeName(record) }}
                          </div>
                          <div v-if="record.emp_code" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            {{ record.emp_code }}
                          </div>
                        </div>
                      </td>
                      <td class="px-6 py-4 text-center border-r border-gray-200 dark:border-gray-700">
                        <div class="text-sm text-gray-900 dark:text-white">
                          {{ formatDateOnly(record.att_date) }}
                        </div>
                      </td>
                      <td class="px-6 py-4 text-center border-r border-gray-200 dark:border-gray-700">
                        <div class="flex justify-center">
                          <Badge variant="secondary" class="px-3 py-1">
                            {{ record.device_pin }}
                          </Badge>
                        </div>
                      </td>
                      <td class="px-6 py-4 text-center border-r border-gray-200 dark:border-gray-700">
                        <div class="flex flex-col items-center justify-center space-y-1">
                          <div class="text-base font-semibold text-gray-900 dark:text-white">
                            {{ formatDateTime(record.first_punch) }}
                          </div>
                          <Badge v-if="record.first_verify_mode !== null" variant="outline" class="text-xs mt-1">
                            {{ $t('attendance.verify_mode') }}: {{ getVerifyModeName(record.first_verify_mode) }}
                          </Badge>
                        </div>
                      </td>
                      <td class="px-6 py-4 text-center border-r border-gray-200 dark:border-gray-700">
                        <div class="flex flex-col items-center justify-center space-y-1">
                          <div class="text-base font-semibold text-gray-900 dark:text-white">
                            {{ formatDateTime(record.last_punch) }}
                          </div>
                          <Badge v-if="record.last_verify_mode !== null" variant="outline" class="text-xs mt-1">
                            {{ $t('attendance.verify_mode') }}: {{ getVerifyModeName(record.last_verify_mode) }}
                          </Badge>
                        </div>
                      </td>
                      <td class="px-6 py-4 text-center border-r border-gray-200 dark:border-gray-700">
                        <div class="flex justify-center">
                          <Badge variant="destructive" class="px-3 py-1">
                            {{ record.late_minutes }} {{ $t('common.minutes') }}
                          </Badge>
                        </div>
                      </td>
                      <td class="px-6 py-4 text-center">
                        <div class="text-sm text-gray-700 dark:text-gray-300">
                          {{ record.device_name || record.serial_number || '-' }}
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Pagination -->
              <div v-if="lateAttendance.links" class="mt-6 flex items-center justify-between">
                <div class="flex-1 flex justify-between sm:hidden">
                  <Button v-if="lateAttendance.prev_page_url" @click="router.visit(lateAttendance.prev_page_url)" variant="outline">
                    {{ $t('common.previous') }}
                  </Button>
                  <Button v-if="lateAttendance.next_page_url" @click="router.visit(lateAttendance.next_page_url)" variant="outline">
                    {{ $t('common.next') }}
                  </Button>
                </div>
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                  <div>
                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                      <template v-for="link in lateAttendance.links" :key="link.label">
                        <Button
                          v-if="link.url"
                          @click="router.visit(link.url)"
                          :variant="link.active ? 'default' : 'outline'"
                          size="sm"
                          class="mr-1"
                        >
                          <span v-html="translatePaginationLabel(link.label)"></span>
                        </Button>
                        <Button v-else variant="outline" size="sm" disabled class="mr-1">
                          <span v-html="translatePaginationLabel(link.label)"></span>
                        </Button>
                      </template>
                    </nav>
                  </div>
                  <div>
                    <p class="text-sm text-gray-700 dark:text-gray-300">
                      {{ $t('common.showing') }}
                      <span class="font-medium">{{ lateAttendance.from || 0 }}</span>
                      {{ $t('common.to') }}
                      <span class="font-medium">{{ lateAttendance.to || 0 }}</span>
                      {{ $t('common.of') }}
                      <span class="font-medium">{{ lateAttendance.total || 0 }}</span>
                      {{ $t('common.results') }}
                    </p>
                  </div>
                </div>
              </div>
            </div>
            <div v-else class="text-center py-12">
              <AlertTriangle class="w-12 h-12 text-gray-400 mx-auto mb-4" />
              <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
                {{ $t('attendance.no_late_records') }}
              </h3>
              <p class="text-gray-600 dark:text-gray-400">
                {{ $t('attendance.no_late_records_description') }}
              </p>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import Icon from '@/components/Icon.vue'
import { Clock, AlertTriangle } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import { router } from '@inertiajs/vue3'
import { ref, computed, onMounted } from 'vue'
import type { BreadcrumbItem } from '@/types'

const { t } = useI18n()

const props = defineProps({
  company: Object,
  lateAttendance: Object,
  stats: Object,
  filters: Object,
  dateRange: Object,
})

const breadcrumbs = computed((): BreadcrumbItem[] => [
  {
    title: t('nav.dashboard'),
    href: '/dashboard',
  },
  {
    title: t('companies.title'),
    href: '/companies',
  },
  {
    title: props.company?.name_ar || props.company?.name_en || t('companies.title'),
    href: `/companies/${props.company?.id}`,
  },
  {
    title: t('attendance.title'),
    href: `/companies/${props.company?.id}/attendance`,
  },
  {
    title: t('attendance.late_title'),
    href: `/companies/${props.company?.id}/attendance/late`,
  },
])

const filterType = ref(props.filters?.filter || 'month')
const fromDate = ref(props.filters?.from || '')
const toDate = ref(props.filters?.to || '')
const searchQuery = ref(props.filters?.search || '')

const onFilterTypeChange = () => {
  if (filterType.value !== 'custom') {
    fromDate.value = ''
    toDate.value = ''
  }
}

const applyFilters = () => {
  const params: any = {
    filter: filterType.value,
    search: searchQuery.value || undefined,
  }

  if (filterType.value === 'custom') {
    if (fromDate.value) params.from = fromDate.value
    if (toDate.value) params.to = toDate.value
  }

  router.get(route('attendance.late', props.company.id), params, {
    preserveState: true,
    preserveScroll: true,
  })
}

const resetFilters = () => {
  filterType.value = 'month'
  fromDate.value = ''
  toDate.value = ''
  searchQuery.value = ''
  router.get(route('attendance.late', props.company.id), {
    filter: 'month',
  }, {
    preserveState: true,
    preserveScroll: true,
  })
}

const formatDateTime = (dateTime: string | null) => {
  if (!dateTime) return '-'
  try {
    const date = new Date(dateTime)
    const formatter = new Intl.DateTimeFormat('en-US', {
      timeZone: 'Asia/Riyadh',
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
    })
    return formatter.format(date)
  } catch (e) {
    return '-'
  }
}

const formatDateOnly = (date: string | Date) => {
  if (!date) return '-'
  try {
    const dateObj = typeof date === 'string' ? new Date(date) : date
    const formatter = new Intl.DateTimeFormat('en-US', {
      timeZone: 'Asia/Riyadh',
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
    })
    return formatter.format(dateObj)
  } catch (e) {
    return '-'
  }
}

const getEmployeeName = (record: any) => {
  if (record.first_name && record.last_name) {
    return `${record.first_name} ${record.last_name}`
  }
  return t('attendance.unknown_employee', { pin: record.device_pin })
}

const getVerifyModeName = (mode: number) => {
  switch (mode) {
    case 0:
      return t('attendance.verify_mode_password')
    case 1:
      return t('attendance.verify_mode_fingerprint')
    case 15:
      return t('attendance.verify_mode_face')
    default:
      return t('attendance.verify_mode_unknown', { mode })
  }
}

const translatePaginationLabel = (label: string) => {
  if (!label) return ''
  return label
    .replace(/&laquo; Previous/g, `&laquo; ${t('common.previous')}`)
    .replace(/Next &raquo;/g, `${t('common.next')} &raquo;`)
    .replace(/&laquo;/g, '&laquo;')
    .replace(/&raquo;/g, '&raquo;')
}
</script>

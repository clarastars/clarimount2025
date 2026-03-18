<template>
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="py-12 w-full">
      <div class="w-full px-4 sm:px-6 lg:px-8">
        <!-- Header Section -->
        <div class="flex justify-between items-center mb-6">
          <div>
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
              {{ $t('attendance.title') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
              {{ $t('attendance.fingerprint_attendance') }}
            </p>
          </div>
          <div class="flex gap-2">
            <Button @click="router.visit(route('attendance.deductions', props.company.id))" class="gap-2 cursor-pointer bg-blue-600 hover:bg-blue-700">
              <Banknote class="w-4 h-4" />
              {{ $t('attendance.deductions') }}
            </Button>
            <Button @click="router.visit(route('attendance.late', props.company.id))" variant="destructive" class="gap-2 cursor-pointer">
              <AlertTriangle class="w-4 h-4" />
              {{ $t('attendance.view_late') }}
            </Button>
          </div>
        </div>

        <!-- Fingerprint Attendance Section -->
        <Card class="mb-8">
          <CardHeader>
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
              <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 w-full sm:w-auto">
                <!-- Search Input -->
                <div class="relative w-full sm:w-64">
                  <div class="absolute inset-y-0 left-0 rtl:left-auto rtl:right-0 pl-3 rtl:pl-0 rtl:pr-3 flex items-center pointer-events-none">
                    <Icon name="Search" class="h-4 w-4 text-gray-400" />
                  </div>
                  <Input
                    v-model="searchQuery"
                    :placeholder="$t('attendance.search_employee')"
                    class="w-full pl-10 rtl:pl-3 rtl:pr-10"
                  />
                </div>
                <!-- Filter Type Select -->
                <div class="flex items-center gap-2">
                  <Label for="filter-type" class="text-sm whitespace-nowrap">
                    {{ $t('attendance.filter_period') }}
                  </Label>
                  <select
                    id="filter-type"
                    v-model="filterType"
                    @change="handleFilterChange"
                    class="h-10 px-3 py-2 text-sm border border-gray-300 dark:border-gray-700 rounded-md bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  >
                    <option value="today">{{ $t('attendance.filter_today') }}</option>
                    <option value="week">{{ $t('attendance.filter_week') }}</option>
                    <option value="month">{{ $t('attendance.filter_month') }}</option>
                    <option value="custom">{{ $t('attendance.filter_custom') }}</option>
                  </select>
                </div>
                <!-- Status Filter -->
                <div class="flex items-center gap-2">
                  <Label for="status-filter" class="text-sm whitespace-nowrap">
                    {{ $t('attendance.filter_status') }}
                  </Label>
                  <select
                    id="status-filter"
                    v-model="statusFilter"
                    @change="applyFilters"
                    class="h-10 px-3 py-2 text-sm border border-gray-300 dark:border-gray-700 rounded-md bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  >
                    <option value="">{{ $t('attendance.filter_status_all') }}</option>
                    <option value="on_time">{{ $t('attendance.status_on_time') }}</option>
                    <option value="late">{{ $t('attendance.status_late') }}</option>
                  </select>
                </div>
                <!-- Custom Date Range (shown only when filter is custom) -->
                <div v-if="filterType === 'custom'" class="flex items-center gap-2">
                  <Label for="from-date" class="text-sm whitespace-nowrap">
                    {{ $t('attendance.from_date') }}
                  </Label>
                  <Input
                    id="from-date"
                    v-model="fromDate"
                    type="date"
                    class="w-40"
                  />
                  <Label for="to-date" class="text-sm whitespace-nowrap">
                    {{ $t('attendance.to_date') }}
                  </Label>
                  <Input
                    id="to-date"
                    v-model="toDate"
                    type="date"
                    class="w-40"
                  />
                  <Button @click="applyFilters" variant="default" class="whitespace-nowrap">
                    {{ $t('common.apply') }}
                  </Button>
                </div>
              </div>
              <CardTitle class="flex items-center gap-2">
                <Fingerprint class="w-5 h-5" />
                {{ $t('attendance.fingerprint_title') }}
              </CardTitle>
            </div>
          </CardHeader>
          <CardContent>
            <!-- Fingerprint Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
              <Card>
                <CardContent class="p-4">
                  <div class="flex items-center">
                    <div class="p-2 bg-green-100 rounded-lg dark:bg-green-900/20">
                      <Fingerprint class="w-5 h-5 text-green-600 dark:text-green-400" />
                    </div>
                    <div class="ml-4">
                      <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                        {{ getPresentLabel() }}
                      </p>
                      <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ fingerprintStats?.present_count || 0 }}
                      </p>
                    </div>
                  </div>
                </CardContent>
              </Card>
              <Card>
                <CardContent class="p-4">
                  <div class="flex items-center">
                    <div class="p-2 bg-blue-100 rounded-lg dark:bg-blue-900/20">
                      <Clock class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div class="ml-4">
                      <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                        {{ $t('attendance.total_punches') }}
                      </p>
                      <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ fingerprintStats?.total_punches || 0 }}
                      </p>
                    </div>
                  </div>
                </CardContent>
              </Card>
            </div>

            <!-- Fingerprint Attendance Table -->
            <div v-if="fingerprintAttendance && fingerprintAttendance.data && fingerprintAttendance.data.length > 0">
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
                        {{ $t('attendance.punch_count') }}
                      </th>
                      <th class="px-6 py-4 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider border-r border-gray-200 dark:border-gray-700">
                        {{ $t('attendance.attendance_status') }}
                      </th>
                      <th class="px-6 py-4 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider border-r border-gray-200 dark:border-gray-700">
                        {{ $t('attendance.attendance_late_minutes') }}
                      </th>
                      <th class="px-6 py-4 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider border-r border-gray-200 dark:border-gray-700">
                        {{ $t('attendance.penalty_action') }}
                      </th>
                      <th class="px-6 py-4 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider border-r border-gray-200 dark:border-gray-700">
                        {{ $t('attendance.penalty_reason') }}
                      </th>
                      <th class="px-6 py-4 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider border-r border-gray-200 dark:border-gray-700">
                        {{ $t('attendance.penalty_approval') }}
                      </th>
                    </tr>
                  </thead>
                  <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                    <tr v-for="record in fingerprintAttendance.data" :key="record.id" class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
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
                        <div class="flex justify-center">
                          <Badge variant="outline" class="px-3 py-1">
                            {{ formatAttendanceDate(record.att_date) }}
                          </Badge>
                        </div>
                      </td>
                    <td class="px-6 py-4 text-center border-r border-gray-200 dark:border-gray-700">
                        <div class="flex justify-center">
                          <Badge v-if="record.device_pin" variant="secondary" class="px-3 py-1">
                            {{ record.device_pin }}
                          </Badge>
                          <span
                            v-else
                            class="text-sm text-red-600 dark:text-red-400 font-medium"
                          >
                            غير مربوط ببصمة
                          </span>
                        </div>
                      </td>
                      <td class="px-6 py-4 text-center border-r border-gray-200 dark:border-gray-700">
                        <div class="flex flex-col items-center justify-center space-y-1">
                          <div v-if="record.first_punch" class="text-base font-semibold text-gray-900 dark:text-white">
                            {{ formatDateTime(record.first_punch) }}
                          </div>
                          <span v-else class="text-sm text-gray-500 dark:text-gray-400">-</span>
                          <Badge v-if="record.first_verify_mode !== null" variant="outline" class="text-xs mt-1">
                            {{ $t('attendance.verify_mode') }}: {{ getVerifyModeName(record.first_verify_mode) }}
                          </Badge>
                        </div>
                      </td>
                      <td class="px-6 py-4 text-center border-r border-gray-200 dark:border-gray-700">
                        <div class="flex flex-col items-center justify-center space-y-1">
                          <div v-if="record.last_punch" class="text-base font-semibold text-gray-900 dark:text-white">
                            {{ formatDateTime(record.last_punch) }}
                          </div>
                          <span v-else class="text-sm text-gray-500 dark:text-gray-400">-</span>
                          <Badge v-if="record.last_verify_mode !== null" variant="outline" class="text-xs mt-1">
                            {{ $t('attendance.verify_mode') }}: {{ getVerifyModeName(record.last_verify_mode) }}
                          </Badge>
                        </div>
                      </td>
                      <td class="px-6 py-4 text-center border-r border-gray-200 dark:border-gray-700">
                        <div class="flex justify-center">
                          <Badge variant="info" class="px-3 py-1">
                            {{ record.punch_count || 0 }}
                          </Badge>
                        </div>
                      </td>
                      <td class="px-6 py-4 text-center border-r border-gray-200 dark:border-gray-700">
                        <div class="flex justify-center">
                          <Badge :variant="getStatusVariant(record.status_ar)" class="px-3 py-1">
                            {{ record.status_ar || '-' }}
                          </Badge>
                        </div>
                      </td>
                      <td class="px-6 py-4 text-center border-r border-gray-200 dark:border-gray-700">
                        <div class="flex justify-center">
                          <span class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ formatLateMinutes(record.late_minutes, record.status_ar) }}
                          </span>
                        </div>
                      </td>
                      <td class="px-6 py-4 text-center border-r border-gray-200 dark:border-gray-700">
                        <div class="flex flex-col justify-center items-center gap-1">
                          <Badge v-if="record.penalty?.action_text" :variant="getPenaltyVariant(record.penalty?.action_type)" class="px-3 py-1">
                            {{ record.penalty.action_text }}
                          </Badge>
                          <span v-if="record.penalty?.late_minutes_deduction_amount != null && Number(record.penalty.late_minutes_deduction_amount) > 0" class="text-xs text-amber-600 dark:text-amber-400">
                            {{ $t('attendance.late_minutes_deduction') }}: {{ formatCurrency(Number(record.penalty.late_minutes_deduction_amount)) }}
                          </span>
                          <template v-if="!record.penalty?.action_text && !(record.penalty?.late_minutes_deduction_amount != null && Number(record.penalty.late_minutes_deduction_amount) > 0)">
                            <span class="text-sm text-gray-500 dark:text-gray-400">-</span>
                          </template>
                        </div>
                      </td>
                      <td class="px-6 py-4 text-center border-r border-gray-200 dark:border-gray-700">
                        <div class="flex justify-center">
                          <span v-if="record.penalty?.reason_text" class="text-xs text-gray-600 dark:text-gray-400 max-w-xs truncate" :title="record.penalty.reason_text">
                            {{ record.penalty.reason_text }}
                          </span>
                          <span v-else class="text-xs text-gray-500 dark:text-gray-400">-</span>
                        </div>
                      </td>
                      <td class="px-6 py-4 text-center border-r border-gray-200 dark:border-gray-700">
                        <div class="flex justify-center items-center gap-2">
                          <template v-if="record.penalty">
                            <!-- Pending: Show Approve/Reject buttons -->
                            <template v-if="record.penalty.approval_status === 'pending'">
                              <Button 
                                @click="approvePenalty(record.penalty.id)" 
                                size="sm" 
                                variant="default"
                                class="bg-green-600 hover:bg-green-700 text-white"
                              >
                                <Icon name="Check" class="h-4 w-4 mr-1" />
                                {{ $t('attendance.approve') }}
                              </Button>
                              <Button 
                                @click="openRejectModal(record.penalty)" 
                                size="sm" 
                                variant="destructive"
                              >
                                <Icon name="X" class="h-4 w-4 mr-1" />
                                {{ $t('attendance.reject') }}
                              </Button>
                            </template>
                            <!-- Approved: Show approved status -->
                            <template v-else-if="record.penalty.approval_status === 'approved'">
                              <Badge variant="default" class="bg-green-600 text-white">
                                <Icon name="CheckCircle" class="h-3 w-3 mr-1" />
                                {{ $t('attendance.approved') }}
                              </Badge>
                              <span v-if="record.penalty.approved_at" class="text-xs text-gray-500 dark:text-gray-400">
                                {{ formatDate(record.penalty.approved_at) }}
                              </span>
                            </template>
                            <!-- Rejected: Show rejected status -->
                            <template v-else-if="record.penalty.approval_status === 'rejected'">
                              <Badge variant="destructive">
                                <Icon name="XCircle" class="h-3 w-3 mr-1" />
                                {{ $t('attendance.rejected') }}
                              </Badge>
                              <div v-if="record.penalty.rejection_reason" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                {{ record.penalty.rejection_reason }}
                              </div>
                              <a 
                                v-if="record.penalty.rejection_attachment_path" 
                                :href="getAttachmentUrl(record.penalty.rejection_attachment_path)"
                                target="_blank"
                                class="text-xs text-blue-600 hover:underline mt-1 block"
                              >
                                {{ $t('attendance.view_attachment') }}
                              </a>
                            </template>
                          </template>
                          <span v-else class="text-xs text-gray-500 dark:text-gray-400">-</span>
                        </div>
                      </td>
                      
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Pagination -->
              <div v-if="fingerprintAttendance.links" class="mt-6 flex items-center justify-between">
                <div class="flex-1 flex justify-between sm:hidden">
                  <Button v-if="fingerprintAttendance.prev_page_url" @click="router.visit(fingerprintAttendance.prev_page_url)" variant="outline">
                    {{ $t('common.previous') }}
                  </Button>
                  <Button v-if="fingerprintAttendance.next_page_url" @click="router.visit(fingerprintAttendance.next_page_url)" variant="outline">
                    {{ $t('common.next') }}
                  </Button>
                </div>
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                  <div>
                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                      <template v-for="link in fingerprintAttendance.links" :key="link.label">
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
                      <span class="font-medium">{{ fingerprintAttendance.from || 0 }}</span>
                      {{ $t('common.to') }}
                      <span class="font-medium">{{ fingerprintAttendance.to || 0 }}</span>
                      {{ $t('common.of') }}
                      <span class="font-medium">{{ fingerprintAttendance.total || 0 }}</span>
                      {{ $t('common.results') }}
                    </p>
                  </div>
                </div>
              </div>
            </div>
            <div v-else class="text-center py-12">
              <Fingerprint class="w-12 h-12 text-gray-400 mx-auto mb-4" />
              <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
                {{ $t('attendance.no_fingerprint_records') }}
              </h3>
              <p class="text-gray-600 dark:text-gray-400">
                {{ $t('attendance.no_fingerprint_records_description') }}
              </p>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>

    <!-- Reject Penalty Modal -->
    <Dialog :open="rejectModalOpen" @update:open="closeRejectModal">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{{ $t('attendance.reject_penalty') }}</DialogTitle>
          <DialogDescription>
            {{ $t('attendance.reject_penalty_description') }}
          </DialogDescription>
        </DialogHeader>
        <div class="space-y-4">
          <div>
            <Label for="rejection_reason">{{ $t('attendance.rejection_reason') }} ({{ $t('common.optional') }})</Label>
            <textarea
              id="rejection_reason"
              v-model="rejectionReason"
              rows="4"
              class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
              :placeholder="$t('attendance.rejection_reason_placeholder')"
            />
          </div>
          <div>
            <Label for="rejection_attachment">{{ $t('attendance.rejection_attachment') }} ({{ $t('common.optional') }})</Label>
            <Input
              id="rejection_attachment"
              type="file"
              accept=".pdf,.jpg,.jpeg,.png"
              @change="(e) => rejectionAttachment = e.target.files[0]"
            />
            <p class="text-xs text-gray-500 mt-1">{{ $t('attendance.rejection_attachment_hint') }}</p>
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="closeRejectModal">
            {{ $t('common.cancel') }}
          </Button>
          <Button variant="destructive" @click="rejectPenalty">
            {{ $t('attendance.reject') }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import Icon from '@/components/Icon.vue'
import {
  FileText,
  Clock,
  AlertTriangle,
  Banknote,
  Plus,
  Eye,
  RotateCcw,
  MoreVertical,
  Fingerprint,
  Calendar
} from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import { router } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import type { BreadcrumbItem } from '@/types'

const { t } = useI18n()

const props = defineProps({
  company: Object,
  imports: Object,
  syncStats: Object,
  fingerprintAttendance: Object,
  fingerprintStats: Object,
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
])

const filterType = ref(props.filters?.filter || 'today')
const fromDate = ref((props.filters as any)?.from || '')
const toDate = ref((props.filters as any)?.to || '')
const statusFilter = ref((props.filters as any)?.status || '')
const searchQuery = ref(props.filters?.search || '')

// Debounced search
let searchTimeout
watch(searchQuery, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    applyFilters()
  }, 300)
})

const handleFilterChange = () => {
  // Reset date range when changing filter type
  if (filterType.value !== 'custom') {
    fromDate.value = ''
    toDate.value = ''
    applyFilters()
  }
}

const applyFilters = () => {
  const params = {
    filter: filterType.value,
    search: searchQuery.value || undefined,
    status: statusFilter.value || undefined
  }
  
  if (filterType.value === 'custom' && fromDate.value && toDate.value) {
    params.from = fromDate.value
    params.to = toDate.value
  }
  
  router.get(route('attendance.index', props.company.id), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

const formatDateTime = (dateTime) => {
  if (!dateTime) return '-'
  try {
    const date = new Date(dateTime)
    // Convert UTC to Asia/Riyadh timezone
    const formatter = new Intl.DateTimeFormat('en-US', {
      timeZone: 'Asia/Riyadh',
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hour12: false
    })
    
    const parts = formatter.formatToParts(date)
    const year = parts.find(p => p.type === 'year')?.value
    const month = parts.find(p => p.type === 'month')?.value
    const day = parts.find(p => p.type === 'day')?.value
    const hour = parts.find(p => p.type === 'hour')?.value
    const minute = parts.find(p => p.type === 'minute')?.value
    const second = parts.find(p => p.type === 'second')?.value
    
    // Format: HH:MM:SS (date in separate line or smaller)
    return `${hour}:${minute}:${second}`
  } catch (e) {
    return '-'
  }
}

const formatDateOnly = (dateTime) => {
  if (!dateTime) return '-'
  try {
    const date = new Date(dateTime)
    const formatter = new Intl.DateTimeFormat('en-US', {
      timeZone: 'Asia/Riyadh',
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
    })
    
    const parts = formatter.formatToParts(date)
    const year = parts.find(p => p.type === 'year')?.value
    const month = parts.find(p => p.type === 'month')?.value
    const day = parts.find(p => p.type === 'day')?.value
    
    return `${year}-${month}-${day}`
  } catch (e) {
    return '-'
  }
}

const getVerifyModeName = (mode) => {
  if (mode === null || mode === undefined) return null
  
  const modes = {
    0: t('attendance.verify_mode_password'),
    1: t('attendance.verify_mode_fingerprint'),
    2: t('attendance.verify_mode_rfid'),
    3: t('attendance.verify_mode_face'),
    4: t('attendance.verify_mode_fingerprint_password'),
    15: t('attendance.verify_mode_palm'),
  }
  
  return modes[mode] || t('attendance.verify_mode_unknown', { mode })
}

const getEmployeeName = (record) => {
  if (record.first_name && record.last_name) {
    return `${record.first_name} ${record.last_name}`
  }
  return t('attendance.unknown_employee', { pin: record.device_pin })
}

const getStatusVariant = (status) => {
  // For attendance status (Arabic)
  if (status === 'في الموعد') {
    return 'success'
  }
  if (status === 'متأخر') {
    return 'destructive'
  }
  if (status === 'غائب') {
    return 'warning'
  }
  if (status === 'إجازة') {
    return 'secondary'
  }
  if (status === 'غير محدد') {
    return 'outline'
  }
  
  // For import status (English)
  switch (status) {
    case 'completed':
      return 'success'
    case 'processing':
      return 'warning'
    case 'failed':
      return 'destructive'
    default:
      return 'secondary'
  }
}

const formatLateMinutes = (lateMinutes, status) => {
  if (lateMinutes === null || lateMinutes === undefined) {
    return '-'
  }
  if (status === 'غير محدد' || status === 'غائب') {
    return '-'
  }
  return lateMinutes.toString()
}

const translatePaginationLabel = (label) => {
  if (!label) return ''
  // Replace English pagination labels with translated ones
  return label
    .replace(/&laquo; Previous/g, `&laquo; ${t('common.previous')}`)
    .replace(/Next &raquo;/g, `${t('common.next')} &raquo;`)
    .replace(/&laquo;/g, '&laquo;')
    .replace(/&raquo;/g, '&raquo;')
}

const getSyncStatusVariant = (status) => {
  switch (status) {
    case 'completed':
      return 'success'
    case 'processing':
      return 'warning'
    case 'failed':
      return 'destructive'
    default:
      return 'secondary'
  }
}

const getStatusText = (status) => {
  return t(`common.status_${status}`, status)
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString()
}

const formatCurrency = (amount) => {
  if (amount == null || Number.isNaN(amount)) return '-'
  return Number(amount).toFixed(2) + ' SAR'
}

const formatAttendanceDate = (date) => {
  if (!date) return '-'
  try {
    // Handle both string and date object
    let dateObj
    if (typeof date === 'string') {
      dateObj = new Date(date)
    } else if (date instanceof Date) {
      dateObj = date
    } else {
      // Handle Carbon date format from Laravel (YYYY-MM-DD)
      dateObj = new Date(date)
    }
    
    // Check if date is valid
    if (isNaN(dateObj.getTime())) {
      // If invalid, try to parse as YYYY-MM-DD string
      if (typeof date === 'string' && /^\d{4}-\d{2}-\d{2}/.test(date)) {
        const [year, month, day] = date.split('-')
        return `${year}-${month}-${day}`
      }
      return '-'
    }
    
    const year = dateObj.getFullYear()
    const month = String(dateObj.getMonth() + 1).padStart(2, '0')
    const day = String(dateObj.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
  } catch (e) {
    return '-'
  }
}

const getPresentLabel = () => {
  switch (filterType.value) {
    case 'today':
      return t('attendance.present_today')
    case 'week':
      return t('attendance.present_week') || t('attendance.present_today')
    case 'month':
      return t('attendance.present_month') || t('attendance.present_today')
    case 'custom':
      return t('attendance.present_period') || t('attendance.present_today')
    default:
      return t('attendance.present_today')
  }
}

const getPenaltyVariant = (actionType) => {
  if (!actionType) return 'secondary'
  
  switch (actionType) {
    case 'warning':
      return 'secondary'
    case 'deduction_percentage':
      return 'warning'
    case 'deduction_days':
      return 'destructive'
    case 'termination':
      return 'destructive'
    default:
      return 'secondary'
  }
}

const hasFailedSyncs = (importItem) => {
  return importItem.sync_batches.some(batch => batch.status === 'failed')
}

const retrySync = (importId) => {
  router.post(route('attendance.retry', [props.company.id, importId]), {}, {
    preserveScroll: true,
    onSuccess: () => {
      // Handle success
    }
  })
}

// Penalty approval functions
const rejectModalOpen = ref(false)
const selectedPenalty = ref(null)
const rejectionReason = ref('')
const rejectionAttachment = ref(null)

const openRejectModal = (penalty) => {
  selectedPenalty.value = penalty
  rejectionReason.value = ''
  rejectionAttachment.value = null
  rejectModalOpen.value = true
}

const closeRejectModal = () => {
  rejectModalOpen.value = false
  selectedPenalty.value = null
  rejectionReason.value = ''
  rejectionAttachment.value = null
}

const approvePenalty = (penaltyId) => {
  router.post(route('attendance-penalties.approve', penaltyId), {}, {
    preserveScroll: true,
    onSuccess: () => {
      // Success handled by Inertia
    },
  })
}

const rejectPenalty = () => {
  if (!selectedPenalty.value) return

  const formData = new FormData()
  if (rejectionReason.value) {
    formData.append('rejection_reason', rejectionReason.value)
  }
  if (rejectionAttachment.value) {
    formData.append('rejection_attachment', rejectionAttachment.value)
  }

  router.post(route('attendance-penalties.reject', selectedPenalty.value.id), formData, {
    preserveScroll: true,
    onSuccess: () => {
      closeRejectModal()
    },
  })
}

const getAttachmentUrl = (path) => {
  return `/storage/${path}`
}
</script>

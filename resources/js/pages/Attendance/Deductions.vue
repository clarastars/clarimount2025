<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { computed, ref, watch } from 'vue'
import type { BreadcrumbItem } from '@/types'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Badge } from '@/components/ui/badge'
import PayrollMonthFilter from '@/components/attendance/PayrollMonthFilter.vue'
import { Banknote, Plus, Pencil, Trash2, X } from 'lucide-vue-next'
import axios from 'axios'
import { formatEmployeeSelectLabel } from '@/lib/utils'

const { t } = useI18n()

interface Company {
  id: number
  name_en: string
  name_ar?: string
}

interface EmployeeOption {
  id: number
  first_name: string
  father_name?: string | null
  last_name: string
  employee_id?: string
  company_id: number
  basic_salary?: number | string | null
  allowances?: number | string | null
  basic_hourly_wage?: number | null
}

interface ApprovedPenalty {
  id: number
  type: 'penalty'
  employee_id: number
  employee_name: string
  employee_code?: string
  date: string
  action_text: string
  reason_text: string
  late_minutes_deduction_amount: number | null
  approved_at: string | null
  approver_name: string | null
}

type AmountInputMode =
  | 'manual'
  | 'basic_days'
  | 'basic_daily_percent'
  | 'gross_days'
  | 'gross_daily_percent'
  | 'basic_hours'

interface ManualDeduction {
  id: number
  type: 'manual'
  deduction_type: 'penalties' | 'absence' | 'traffic_violation' | 'attestations'
  employee_id: number
  employee_name: string
  employee_code?: string
  date: string
  amount: number
  amount_input_mode?: AmountInputMode
  amount_input_days?: number | null
  amount_input_hours?: number | null
  amount_input_percent?: number | null
  reason: string
  created_at: string
  creator_name: string | null
}

type DeductionRow = ApprovedPenalty | ManualDeduction

const props = defineProps<{
  company: Company
  companies: Company[]
  employees: EmployeeOption[]
  month: string
  monthPeriodStart: string
  monthPeriodEnd: string
  employeeId: number | null
  approvedPenalties: ApprovedPenalty[]
  manualDeductions: ManualDeduction[]
  canManageAttendanceAdjustments?: boolean
}>()

const canManageAttendanceAdjustments = computed(
  () => props.canManageAttendanceAdjustments ?? false,
)

const createModalOpen = ref(false)
const createForm = useForm({
  company_id: props.company.id,
  employee_id: '' as number | '',
  amount_input_mode: 'manual' as AmountInputMode,
  amount: '',
  amount_input_days: '',
  amount_input_hours: '',
  amount_input_percent: '',
  deduction_date: new Date().toISOString().slice(0, 10),
  deduction_type: 'penalties',
  reason: '',
})
const employeesForCreate = ref<EmployeeOption[]>(props.employees)

watch(
  () => props.company.id,
  (id) => {
    createForm.company_id = id
    createForm.employee_id = ''
    employeesForCreate.value = props.employees
  }
)
watch(
  () => props.employees,
  (list) => {
    if (props.company.id === createForm.company_id) {
      employeesForCreate.value = list
    }
  },
  { deep: true }
)

async function onCompanyChangeInModal(companyId: number) {
  createForm.company_id = companyId
  createForm.employee_id = ''
  try {
    const { data } = await axios.get(route('api.employees.search'), {
      params: { company_id: companyId, q: '' },
    })
    employeesForCreate.value = data || []
  } catch {
    employeesForCreate.value = []
  }
}

function openCreateModal() {
  createForm.reset()
  createForm.company_id = props.company.id
  createForm.deduction_date = new Date().toISOString().slice(0, 10)
  createForm.deduction_type = 'penalties'
  createForm.amount_input_mode = 'manual'
  createForm.amount = ''
  createForm.amount_input_days = ''
  createForm.amount_input_hours = ''
  createForm.amount_input_percent = ''
  employeesForCreate.value = props.employees
  createModalOpen.value = true
}

function submitCreate() {
  createForm.post(route('attendance.deductions.store'), {
    preserveScroll: true,
    onSuccess: () => {
      createModalOpen.value = false
      createForm.reset()
    },
  })
}

const editModalOpen = ref(false)
const selectedDeduction = ref<ManualDeduction | null>(null)
const editForm = useForm({
  amount_input_mode: 'manual' as AmountInputMode,
  amount: '',
  amount_input_days: '',
  amount_input_hours: '',
  amount_input_percent: '',
  deduction_date: '',
  deduction_type: 'penalties' as 'penalties' | 'absence' | 'traffic_violation' | 'attestations',
  reason: '',
})

function toNum(v: string): number | null {
  const n = parseFloat(String(v).replace(',', '.'))
  return Number.isFinite(n) ? n : null
}

function basicDailyWageSar(emp: EmployeeOption | undefined): number | null {
  if (!emp) return null
  const b = emp.basic_salary
  if (b === null || b === undefined || b === '') return null
  const n = Number(b)
  if (!Number.isFinite(n) || n <= 0) return null
  return Math.round((n / 30) * 1e6) / 1e6
}

/** Gross daily = (basic + allowances) / 30, same as salary run. */
function grossDailyWageSar(emp: EmployeeOption | undefined): number | null {
  if (!emp) return null
  const b = Number(emp.basic_salary ?? 0)
  const a = Number(emp.allowances ?? 0)
  if (!Number.isFinite(b) || !Number.isFinite(a)) return null
  const gross = b + a
  if (gross <= 0) return null
  return Math.round((gross / 30) * 1e6) / 1e6
}

function basicHourlyWageSar(emp: EmployeeOption | undefined): number | null {
  if (!emp) return null
  const hourly = emp.basic_hourly_wage
  if (hourly === null || hourly === undefined) return null
  const n = Number(hourly)
  if (!Number.isFinite(n) || n <= 0) return null
  return Math.round(n * 1e6) / 1e6
}

function resolvePreviewAmount(
  mode: AmountInputMode,
  basicDaily: number | null,
  grossDaily: number | null,
  basicHourly: number | null,
  manual: string,
  daysStr: string,
  hoursStr: string,
  pctStr: string
): number | null {
  if (mode === 'manual') {
    const a = toNum(manual)
    return a !== null && a > 0 ? Math.round(a * 100) / 100 : null
  }
  if (mode === 'basic_days') {
    if (basicDaily == null) return null
    const d = toNum(daysStr)
    if (d === null || d <= 0) return null
    return Math.round(d * basicDaily * 100) / 100
  }
  if (mode === 'basic_daily_percent') {
    if (basicDaily == null) return null
    const p = toNum(pctStr)
    if (p === null || p <= 0) return null
    return Math.round((p / 100) * basicDaily * 100) / 100
  }
  if (mode === 'gross_days') {
    if (grossDaily == null) return null
    const d = toNum(daysStr)
    if (d === null || d <= 0) return null
    return Math.round(d * grossDaily * 100) / 100
  }
  if (mode === 'gross_daily_percent') {
    if (grossDaily == null) return null
    const p = toNum(pctStr)
    if (p === null || p <= 0) return null
    return Math.round((p / 100) * grossDaily * 100) / 100
  }
  if (mode === 'basic_hours') {
    if (basicHourly == null) return null
    const h = toNum(hoursStr)
    if (h === null || h <= 0) return null
    return Math.round(h * basicHourly * 100) / 100
  }
  return null
}

const createSelectedEmployee = computed((): EmployeeOption | undefined => {
  const id = createForm.employee_id
  if (id === '' || id == null) return undefined
  return employeesForCreate.value.find((e) => e.id === id)
})

const createAmountPreview = computed(() => {
  const emp = createSelectedEmployee.value
  return resolvePreviewAmount(
    createForm.amount_input_mode,
    basicDailyWageSar(emp),
    grossDailyWageSar(emp),
    basicHourlyWageSar(emp),
    createForm.amount,
    createForm.amount_input_days,
    createForm.amount_input_hours,
    createForm.amount_input_percent
  )
})

const editSelectedEmployee = computed((): EmployeeOption | undefined => {
  const id = selectedDeduction.value?.employee_id
  if (id == null) return undefined
  return props.employees.find((e) => e.id === id)
})

const editAmountPreview = computed(() => {
  const emp = editSelectedEmployee.value
  return resolvePreviewAmount(
    editForm.amount_input_mode,
    basicDailyWageSar(emp),
    grossDailyWageSar(emp),
    basicHourlyWageSar(emp),
    editForm.amount,
    editForm.amount_input_days,
    editForm.amount_input_hours,
    editForm.amount_input_percent
  )
})

const needsBasicForCreate = computed(
  () =>
    createForm.amount_input_mode === 'basic_days' || createForm.amount_input_mode === 'basic_daily_percent'
)
const hasBasicForCreate = computed(() => basicDailyWageSar(createSelectedEmployee.value) != null)
const needsGrossForCreate = computed(
  () =>
    createForm.amount_input_mode === 'gross_days' || createForm.amount_input_mode === 'gross_daily_percent'
)
const hasGrossForCreate = computed(() => grossDailyWageSar(createSelectedEmployee.value) != null)
const needsBasicForEdit = computed(
  () =>
    editForm.amount_input_mode === 'basic_days' || editForm.amount_input_mode === 'basic_daily_percent'
)
const hasBasicForEdit = computed(() => basicDailyWageSar(editSelectedEmployee.value) != null)
const needsGrossForEdit = computed(
  () => editForm.amount_input_mode === 'gross_days' || editForm.amount_input_mode === 'gross_daily_percent'
)
const hasGrossForEdit = computed(() => grossDailyWageSar(editSelectedEmployee.value) != null)
const needsHourlyForCreate = computed(() => createForm.amount_input_mode === 'basic_hours')
const hasHourlyForCreate = computed(() => basicHourlyWageSar(createSelectedEmployee.value) != null)
const needsHourlyForEdit = computed(() => editForm.amount_input_mode === 'basic_hours')
const hasHourlyForEdit = computed(() => basicHourlyWageSar(editSelectedEmployee.value) != null)

function openEditModal(row: ManualDeduction) {
  selectedDeduction.value = row
  editForm.reset()
  const mode = (row.amount_input_mode ?? 'manual') as AmountInputMode
  editForm.amount_input_mode = mode
  if (mode === 'manual') {
    editForm.amount = String(row.amount)
    editForm.amount_input_days = ''
    editForm.amount_input_hours = ''
    editForm.amount_input_percent = ''
  } else if (mode === 'basic_hours') {
    editForm.amount = ''
    editForm.amount_input_days = ''
    editForm.amount_input_hours = row.amount_input_hours != null ? String(row.amount_input_hours) : ''
    editForm.amount_input_percent = ''
  } else {
    editForm.amount = ''
    editForm.amount_input_days = row.amount_input_days != null ? String(row.amount_input_days) : ''
    editForm.amount_input_hours = ''
    editForm.amount_input_percent = row.amount_input_percent != null ? String(row.amount_input_percent) : ''
  }
  editForm.deduction_date = row.date
  editForm.deduction_type = row.deduction_type
  editForm.reason = row.reason
  editModalOpen.value = true
}

function closeEditModal() {
  editModalOpen.value = false
  selectedDeduction.value = null
}

function submitEdit() {
  if (!selectedDeduction.value) return
  editForm.put(route('attendance.deductions.update', selectedDeduction.value.id), {
    preserveScroll: true,
    onSuccess: () => {
      closeEditModal()
    },
  })
}

function confirmDelete(row: ManualDeduction) {
  if (!confirm(t('attendance.delete_deduction_confirm'))) return
  router.delete(route('attendance.deductions.destroy', row.id), { preserveScroll: true })
}

const mergedList = computed((): DeductionRow[] => {
  const list: DeductionRow[] = [
    ...props.approvedPenalties,
    ...props.manualDeductions,
  ]
  list.sort((a, b) => (b.date < a.date ? -1 : b.date > a.date ? 1 : 0))
  return list
})

const breadcrumbs = computed((): BreadcrumbItem[] => [
  { title: t('nav.dashboard'), href: '/dashboard' },
  { title: t('companies.title'), href: '/companies' },
  {
    title: props.company?.name_ar || props.company?.name_en || t('companies.title'),
    href: `/companies/${props.company?.id}`,
  },
  { title: t('attendance.title'), href: `/companies/${props.company?.id}/attendance` },
  {
    title: t('attendance.deductions_title'),
    href: `/companies/${props.company?.id}/attendance/deductions`,
  },
])

function applyFilters(params: { month?: string; employee_id?: number | '' }) {
  const q: Record<string, string | number | undefined> = {}
  if (params.month) q.month = params.month
  if (params.employee_id !== undefined && params.employee_id !== '') {
    q.employee_id = params.employee_id
  }
  router.get(route('attendance.deductions', props.company.id), q, {
    preserveState: true,
    preserveScroll: true,
  })
}

function goToCompany(companyId: number) {
  router.get(route('attendance.deductions', companyId), {
    month: props.month,
    employee_id: employeeFilter.value || undefined,
  })
}

const employeeFilter = ref<number | ''>(props.employeeId ?? '')

watch(
  () => props.employeeId,
  (id) => {
    employeeFilter.value = id ?? ''
  }
)

function onPayrollMonthChange(ym: string) {
  applyFilters({ month: ym, employee_id: employeeFilter.value })
}

watch(employeeFilter, (v) => applyFilters({ month: props.month, employee_id: v }))

function formatDate(dateStr: string) {
  if (!dateStr) return '-'
  try {
    return new Date(dateStr + 'Z').toLocaleDateString(undefined, {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
    })
  } catch {
    return dateStr
  }
}

function formatCurrency(amount: number) {
  if (amount == null || Number.isNaN(amount)) return '-'
  return Number(amount).toFixed(2) + ' SAR'
}

function isManual(row: DeductionRow): row is ManualDeduction {
  return row.type === 'manual'
}

function isPenalty(row: DeductionRow): row is ApprovedPenalty {
  return row.type === 'penalty'
}

const penaltyRejectModalOpen = ref(false)
const penaltyToReject = ref<ApprovedPenalty | null>(null)
const penaltyRejectionReason = ref('')
const penaltyRejectionAttachment = ref<File | null>(null)

function openPenaltyRejectModal(row: ApprovedPenalty) {
  penaltyToReject.value = row
  penaltyRejectionReason.value = ''
  penaltyRejectionAttachment.value = null
  penaltyRejectModalOpen.value = true
}

function closePenaltyRejectModal() {
  penaltyRejectModalOpen.value = false
  penaltyToReject.value = null
  penaltyRejectionReason.value = ''
  penaltyRejectionAttachment.value = null
}

function onPenaltyRejectModalOpenChange(open: boolean) {
  if (!open) {
    closePenaltyRejectModal()
  }
}

function onPenaltyRejectionFileChange(e: Event) {
  const target = e.target as HTMLInputElement
  penaltyRejectionAttachment.value = target.files?.[0] ?? null
}

function submitPenaltyReject() {
  if (!penaltyToReject.value) return

  const formData = new FormData()
  if (penaltyRejectionReason.value) {
    formData.append('rejection_reason', penaltyRejectionReason.value)
  }
  if (penaltyRejectionAttachment.value) {
    formData.append('rejection_attachment', penaltyRejectionAttachment.value)
  }

  router.post(route('attendance-penalties.reject', penaltyToReject.value.id), formData, {
    preserveScroll: true,
    onSuccess: () => {
      closePenaltyRejectModal()
    },
  })
}

function deductionTypeLabel(type: string) {
  const map: Record<string, string> = {
    penalties: t('attendance.deduction_type_penalties'),
    absence: t('attendance.deduction_type_absence'),
    traffic_violation: t('attendance.deduction_type_traffic_violation'),
    attestations: t('attendance.deduction_type_attestations'),
  }

  return map[type] ?? t('attendance.deduction_type_manual')
}
</script>

<template>
  <Head :title="t('attendance.deductions_title')" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="py-12 w-full">
      <div class="w-full px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
          <div>
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
              {{ t('attendance.deductions_title') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
              {{ t('attendance.deductions_description') }}
            </p>
          </div>
          <Button v-if="canManageAttendanceAdjustments" class="gap-2 cursor-pointer bg-blue-600 hover:bg-blue-700" @click="openCreateModal">
            <Plus class="w-4 h-4" />
            {{ t('attendance.create_deduction') }}
          </Button>
        </div>

        <!-- Filters -->
        <Card class="mb-6">
          <CardHeader>
            <CardTitle>{{ t('common.filters') }}</CardTitle>
          </CardHeader>
          <CardContent>
            <div class="flex flex-col gap-6">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <Label for="filter-company">{{ t('attendance.filter_company') }}</Label>
                  <select
                    id="filter-company"
                    :value="company.id"
                    class="mt-1 flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    @change="goToCompany(Number(($event.target as HTMLSelectElement).value))"
                  >
                    <option v-for="c in companies" :key="c.id" :value="c.id">
                      {{ c.name_ar || c.name_en }}
                    </option>
                  </select>
                </div>
                <div>
                  <Label for="filter-employee">{{ t('attendance.filter_employee') }}</Label>
                  <select
                    id="filter-employee"
                    v-model="employeeFilter"
                    class="mt-1 flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                  >
                    <option value="">{{ t('attendance.all_employees') }}</option>
                    <option v-for="emp in employees" :key="emp.id" :value="emp.id">
                      {{ formatEmployeeSelectLabel(emp) }}
                    </option>
                  </select>
                </div>
              </div>
              <PayrollMonthFilter
                :month="month"
                :period-start="monthPeriodStart"
                :period-end="monthPeriodEnd"
                @update:month="onPayrollMonthChange"
              />
            </div>
          </CardContent>
        </Card>

        <!-- Table -->
        <Card>
          <CardHeader>
            <CardTitle class="flex items-center gap-2">
              <Banknote class="w-5 h-5" />
              {{ t('attendance.deductions_title') }}
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div v-if="mergedList.length === 0" class="text-center py-12 text-muted-foreground">
              <p class="font-medium">{{ t('attendance.no_deductions') }}</p>
              <p class="text-sm mt-1">{{ t('attendance.no_deductions_description') }}</p>
            </div>
            <div v-else class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
              <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                  <tr>
                    <th class="px-4 py-3 text-start text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                      {{ t('attendance.deduction_type') }}
                    </th>
                    <th class="px-4 py-3 text-start text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                      {{ t('attendance.employee_name') }}
                    </th>
                    <th class="px-4 py-3 text-start text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                      {{ t('attendance.date') }}
                    </th>
                    <th class="px-4 py-3 text-start text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                      {{ t('attendance.penalty_action') }} / {{ t('attendance.deduction_amount') }}
                    </th>
                    <th class="px-4 py-3 text-start text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                      {{ t('attendance.penalty_reason') }}
                    </th>
                    <th class="px-4 py-3 text-start text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                      {{ t('attendance.approved_by_label') }} / {{ t('attendance.created_by_label') }}
                    </th>
                    <th class="px-4 py-3 text-start text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider w-28">
                      {{ t('common.actions') }}
                    </th>
                  </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                  <tr v-for="row in mergedList" :key="row.type + '-' + row.id" class="hover:bg-gray-50 dark:hover:bg-gray-800">
                    <td class="px-4 py-3 text-sm">
                      <Badge v-if="row.type === 'penalty'" variant="secondary">
                        {{ t('attendance.deduction_type_penalty') }}
                      </Badge>
                      <Badge v-else variant="default">
                        {{ deductionTypeLabel(row.deduction_type) }}
                      </Badge>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                      {{ row.employee_name }}
                      <span v-if="row.employee_code" class="text-muted-foreground"> ({{ row.employee_code }})</span>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                      {{ formatDate(row.date) }}
                    </td>
                    <td class="px-4 py-3 text-sm">
                      <template v-if="isManual(row)">
                        <span class="font-medium">{{ formatCurrency(row.amount) }}</span>
                      </template>
                      <template v-else>
                        <div class="flex flex-col gap-0.5">
                          <span>{{ row.action_text }}</span>
                          <span v-if="row.late_minutes_deduction_amount != null && row.late_minutes_deduction_amount > 0" class="text-xs text-amber-600 dark:text-amber-400">
                            {{ t('attendance.late_minutes_deduction') }}: {{ formatCurrency(row.late_minutes_deduction_amount) }}
                          </span>
                        </div>
                      </template>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 max-w-xs truncate" :title="isManual(row) ? row.reason : row.reason_text">
                      <template v-if="isManual(row)">{{ row.reason?.trim() ? row.reason : '—' }}</template>
                      <template v-else>{{ row.reason_text }}</template>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                      <template v-if="isManual(row)">
                        {{ row.creator_name || '-' }}
                      </template>
                      <template v-else>
                        {{ row.approver_name || '-' }}
                      </template>
                    </td>
                    <td class="px-4 py-3 text-sm">
                      <template v-if="canManageAttendanceAdjustments && isManual(row)">
                        <div class="flex gap-1">
                          <Button variant="ghost" size="sm" class="h-8 w-8 p-0" @click="openEditModal(row)" :title="t('attendance.edit_deduction')">
                            <Pencil class="h-4 w-4" />
                          </Button>
                          <Button variant="ghost" size="sm" class="h-8 w-8 p-0 text-red-600 hover:text-red-700" @click="confirmDelete(row)" :title="t('attendance.delete_deduction')">
                            <Trash2 class="h-4 w-4" />
                          </Button>
                        </div>
                      </template>
                      <template v-else-if="canManageAttendanceAdjustments && isPenalty(row)">
                        <Button
                          variant="ghost"
                          size="sm"
                          class="h-8 gap-1 px-2 text-red-600 hover:text-red-700"
                          :title="t('attendance.reject')"
                          @click="openPenaltyRejectModal(row)"
                        >
                          <X class="h-4 w-4" />
                          <span class="text-xs">{{ t('attendance.reject') }}</span>
                        </Button>
                      </template>
                      <span v-else class="text-muted-foreground">-</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>

    <!-- Create deduction modal -->
    <Dialog :open="createModalOpen" @update:open="(v: boolean) => (createModalOpen = v)">
      <DialogContent class="max-w-lg max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{{ t('attendance.create_deduction') }}</DialogTitle>
          <DialogDescription>
            {{ t('attendance.deductions_description') }}
          </DialogDescription>
        </DialogHeader>
        <form @submit.prevent="submitCreate" class="space-y-4">
          <div>
            <Label for="create-company">{{ t('attendance.filter_company') }}</Label>
            <select
              id="create-company"
              :value="createForm.company_id"
              class="mt-1 flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
              @change="onCompanyChangeInModal(Number(($event.target as HTMLSelectElement).value))"
            >
              <option v-for="c in companies" :key="c.id" :value="c.id">
                {{ c.name_ar || c.name_en }}
              </option>
            </select>
          </div>
          <div>
            <Label for="create-employee">{{ t('attendance.filter_employee') }}</Label>
            <select
              id="create-employee"
              v-model="createForm.employee_id"
              class="mt-1 flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
              required
            >
              <option value="">{{ t('attendance.filter_employee') }}</option>
              <option v-for="emp in employeesForCreate" :key="emp.id" :value="emp.id">
                {{ formatEmployeeSelectLabel(emp) }}
              </option>
            </select>
            <p v-if="createForm.errors.employee_id" class="text-sm text-red-500 mt-1">
              {{ createForm.errors.employee_id }}
            </p>
          </div>
          <div>
            <Label for="create-amount-mode">{{ t('attendance.deduction_amount_mode') }}</Label>
            <select
              id="create-amount-mode"
              v-model="createForm.amount_input_mode"
              class="mt-1 flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
            >
              <option value="manual">{{ t('attendance.deduction_mode_manual') }}</option>
              <option value="basic_days">{{ t('attendance.deduction_mode_basic_days') }}</option>
              <option value="basic_daily_percent">
                {{ t('attendance.deduction_mode_basic_daily_percent') }}
              </option>
              <option value="gross_days">{{ t('attendance.deduction_mode_gross_days') }}</option>
              <option value="gross_daily_percent">
                {{ t('attendance.deduction_mode_gross_daily_percent') }}
              </option>
              <option value="basic_hours">{{ t('attendance.deduction_mode_basic_hours') }}</option>
            </select>
          </div>
          <div
            v-if="needsBasicForCreate && createForm.employee_id && !hasBasicForCreate"
            class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
          >
            {{ t('attendance.deduction_basic_salary_unavailable') }}
          </div>
          <div
            v-if="needsGrossForCreate && createForm.employee_id && !hasGrossForCreate"
            class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
          >
            {{ t('attendance.deduction_gross_salary_unavailable') }}
          </div>
          <div
            v-if="needsHourlyForCreate && createForm.employee_id && !hasHourlyForCreate"
            class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
          >
            {{ t('attendance.deduction_hourly_rate_unavailable') }}
          </div>
          <div v-if="createForm.amount_input_mode === 'manual'">
            <Label for="create-amount">{{ t('attendance.deduction_amount') }}</Label>
            <Input
              id="create-amount"
              v-model="createForm.amount"
              type="number"
              step="0.01"
              min="0.01"
              class="mt-1"
              :required="createForm.amount_input_mode === 'manual'"
            />
            <p v-if="createForm.errors.amount" class="text-sm text-red-500 mt-1">
              {{ createForm.errors.amount }}
            </p>
          </div>
          <div
            v-else-if="
              createForm.amount_input_mode === 'basic_days' || createForm.amount_input_mode === 'gross_days'
            "
          >
            <Label for="create-input-days">{{
              createForm.amount_input_mode === 'gross_days'
                ? t('attendance.deduction_input_gross_days')
                : t('attendance.deduction_input_days')
            }}</Label>
            <Input
              id="create-input-days"
              v-model="createForm.amount_input_days"
              type="number"
              step="any"
              min="0.01"
              class="mt-1"
              :required="
                createForm.amount_input_mode === 'basic_days' ||
                createForm.amount_input_mode === 'gross_days'
              "
            />
            <p v-if="createForm.errors.amount_input_days" class="text-sm text-red-500 mt-1">
              {{ createForm.errors.amount_input_days }}
            </p>
            <p v-if="createForm.errors.amount" class="text-sm text-red-500 mt-1">
              {{ createForm.errors.amount }}
            </p>
          </div>
          <div
            v-else-if="
              createForm.amount_input_mode === 'basic_daily_percent' ||
              createForm.amount_input_mode === 'gross_daily_percent'
            "
          >
            <Label for="create-input-pct">{{
              createForm.amount_input_mode === 'gross_daily_percent'
                ? t('attendance.deduction_input_gross_percent')
                : t('attendance.deduction_input_percent')
            }}</Label>
            <Input
              id="create-input-pct"
              v-model="createForm.amount_input_percent"
              type="number"
              step="any"
              min="0.01"
              max="100"
              class="mt-1"
              :required="
                createForm.amount_input_mode === 'basic_daily_percent' ||
                createForm.amount_input_mode === 'gross_daily_percent'
              "
            />
            <p v-if="createForm.errors.amount_input_percent" class="text-sm text-red-500 mt-1">
              {{ createForm.errors.amount_input_percent }}
            </p>
            <p v-if="createForm.errors.amount" class="text-sm text-red-500 mt-1">
              {{ createForm.errors.amount }}
            </p>
          </div>
          <div v-else-if="createForm.amount_input_mode === 'basic_hours'">
            <Label for="create-input-hours">{{ t('attendance.deduction_input_hours') }}</Label>
            <Input
              id="create-input-hours"
              v-model="createForm.amount_input_hours"
              type="number"
              step="any"
              min="0.01"
              class="mt-1"
              :required="createForm.amount_input_mode === 'basic_hours'"
            />
            <p v-if="createForm.errors.amount_input_hours" class="text-sm text-red-500 mt-1">
              {{ createForm.errors.amount_input_hours }}
            </p>
            <p v-if="createForm.errors.amount" class="text-sm text-red-500 mt-1">
              {{ createForm.errors.amount }}
            </p>
          </div>
          <div
            v-if="createAmountPreview != null && createForm.employee_id"
            class="text-sm text-muted-foreground"
          >
            <span class="font-medium text-foreground">{{ t('attendance.deduction_computed_amount') }}:</span>
            {{ formatCurrency(createAmountPreview) }}
          </div>
          <div>
            <Label for="create-date">{{ t('attendance.deduction_date') }}</Label>
            <Input
              id="create-date"
              v-model="createForm.deduction_date"
              type="date"
              class="mt-1"
              required
            />
            <p v-if="createForm.errors.deduction_date" class="text-sm text-red-500 mt-1">
              {{ createForm.errors.deduction_date }}
            </p>
          </div>
          <div>
            <Label for="create-deduction-type">{{ t('attendance.deduction_type_category') }}</Label>
            <select
              id="create-deduction-type"
              v-model="createForm.deduction_type"
              class="mt-1 flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
              required
            >
              <option value="penalties">{{ t('attendance.deduction_type_penalties') }}</option>
              <option value="absence">{{ t('attendance.deduction_type_absence') }}</option>
              <option value="traffic_violation">{{ t('attendance.deduction_type_traffic_violation') }}</option>
              <option value="attestations">{{ t('attendance.deduction_type_attestations') }}</option>
            </select>
            <p v-if="createForm.errors.deduction_type" class="text-sm text-red-500 mt-1">
              {{ createForm.errors.deduction_type }}
            </p>
          </div>
          <div>
            <Label for="create-reason">
              {{ t('attendance.deduction_reason') }}
              <span class="text-muted-foreground font-normal text-xs">({{ t('common.optional') }})</span>
            </Label>
            <textarea
              id="create-reason"
              v-model="createForm.reason"
              rows="3"
              class="mt-1 flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
              :placeholder="t('attendance.deduction_reason_placeholder')"
            />
            <p v-if="createForm.errors.reason" class="text-sm text-red-500 mt-1">
              {{ createForm.errors.reason }}
            </p>
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" @click="createModalOpen = false">
              {{ t('common.cancel') }}
            </Button>
            <Button type="submit" :disabled="createForm.processing">
              {{ t('common.save') }}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>

    <!-- Edit deduction modal -->
    <Dialog :open="editModalOpen" @update:open="(v: boolean) => !v && closeEditModal()">
      <DialogContent class="max-w-lg max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{{ t('attendance.edit_deduction') }}</DialogTitle>
          <DialogDescription v-if="selectedDeduction">
            {{ selectedDeduction.employee_name }}
          </DialogDescription>
        </DialogHeader>
        <form v-if="selectedDeduction" @submit.prevent="submitEdit" class="space-y-4">
          <div>
            <Label class="text-muted-foreground">{{ t('attendance.filter_employee') }}</Label>
            <p class="mt-1 text-sm font-medium">{{ selectedDeduction.employee_name }}</p>
          </div>
          <div>
            <Label for="edit-amount-mode">{{ t('attendance.deduction_amount_mode') }}</Label>
            <select
              id="edit-amount-mode"
              v-model="editForm.amount_input_mode"
              class="mt-1 flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
            >
              <option value="manual">{{ t('attendance.deduction_mode_manual') }}</option>
              <option value="basic_days">{{ t('attendance.deduction_mode_basic_days') }}</option>
              <option value="basic_daily_percent">
                {{ t('attendance.deduction_mode_basic_daily_percent') }}
              </option>
              <option value="gross_days">{{ t('attendance.deduction_mode_gross_days') }}</option>
              <option value="gross_daily_percent">
                {{ t('attendance.deduction_mode_gross_daily_percent') }}
              </option>
              <option value="basic_hours">{{ t('attendance.deduction_mode_basic_hours') }}</option>
            </select>
          </div>
          <div
            v-if="needsBasicForEdit && !hasBasicForEdit"
            class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
          >
            {{ t('attendance.deduction_basic_salary_unavailable') }}
          </div>
          <div
            v-if="needsGrossForEdit && !hasGrossForEdit"
            class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
          >
            {{ t('attendance.deduction_gross_salary_unavailable') }}
          </div>
          <div
            v-if="needsHourlyForEdit && !hasHourlyForEdit"
            class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
          >
            {{ t('attendance.deduction_hourly_rate_unavailable') }}
          </div>
          <div v-if="editForm.amount_input_mode === 'manual'">
            <Label for="edit-amount">{{ t('attendance.deduction_amount') }}</Label>
            <Input
              id="edit-amount"
              v-model="editForm.amount"
              type="number"
              step="0.01"
              min="0.01"
              class="mt-1"
            />
            <p v-if="editForm.errors.amount" class="text-sm text-red-500 mt-1">{{ editForm.errors.amount }}</p>
          </div>
          <div
            v-else-if="
              editForm.amount_input_mode === 'basic_days' || editForm.amount_input_mode === 'gross_days'
            "
          >
            <Label for="edit-input-days">{{
              editForm.amount_input_mode === 'gross_days'
                ? t('attendance.deduction_input_gross_days')
                : t('attendance.deduction_input_days')
            }}</Label>
            <Input
              id="edit-input-days"
              v-model="editForm.amount_input_days"
              type="number"
              step="any"
              min="0.01"
              class="mt-1"
            />
            <p v-if="editForm.errors.amount_input_days" class="text-sm text-red-500 mt-1">
              {{ editForm.errors.amount_input_days }}
            </p>
            <p v-if="editForm.errors.amount" class="text-sm text-red-500 mt-1">{{ editForm.errors.amount }}</p>
          </div>
          <div
            v-else-if="
              editForm.amount_input_mode === 'basic_daily_percent' ||
              editForm.amount_input_mode === 'gross_daily_percent'
            "
          >
            <Label for="edit-input-pct">{{
              editForm.amount_input_mode === 'gross_daily_percent'
                ? t('attendance.deduction_input_gross_percent')
                : t('attendance.deduction_input_percent')
            }}</Label>
            <Input
              id="edit-input-pct"
              v-model="editForm.amount_input_percent"
              type="number"
              step="any"
              min="0.01"
              max="100"
              class="mt-1"
            />
            <p v-if="editForm.errors.amount_input_percent" class="text-sm text-red-500 mt-1">
              {{ editForm.errors.amount_input_percent }}
            </p>
            <p v-if="editForm.errors.amount" class="text-sm text-red-500 mt-1">{{ editForm.errors.amount }}</p>
          </div>
          <div v-else-if="editForm.amount_input_mode === 'basic_hours'">
            <Label for="edit-input-hours">{{ t('attendance.deduction_input_hours') }}</Label>
            <Input
              id="edit-input-hours"
              v-model="editForm.amount_input_hours"
              type="number"
              step="any"
              min="0.01"
              class="mt-1"
            />
            <p v-if="editForm.errors.amount_input_hours" class="text-sm text-red-500 mt-1">
              {{ editForm.errors.amount_input_hours }}
            </p>
            <p v-if="editForm.errors.amount" class="text-sm text-red-500 mt-1">{{ editForm.errors.amount }}</p>
          </div>
          <div
            v-if="editAmountPreview != null && selectedDeduction"
            class="text-sm text-muted-foreground"
          >
            <span class="font-medium text-foreground">{{ t('attendance.deduction_computed_amount') }}:</span>
            {{ formatCurrency(editAmountPreview) }}
          </div>
          <div>
            <Label for="edit-date">{{ t('attendance.deduction_date') }}</Label>
            <Input id="edit-date" v-model="editForm.deduction_date" type="date" class="mt-1" required />
            <p v-if="editForm.errors.deduction_date" class="text-sm text-red-500 mt-1">{{ editForm.errors.deduction_date }}</p>
          </div>
          <div>
            <Label for="edit-deduction-type">{{ t('attendance.deduction_type_category') }}</Label>
            <select
              id="edit-deduction-type"
              v-model="editForm.deduction_type"
              class="mt-1 flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
              required
            >
              <option value="penalties">{{ t('attendance.deduction_type_penalties') }}</option>
              <option value="absence">{{ t('attendance.deduction_type_absence') }}</option>
              <option value="traffic_violation">{{ t('attendance.deduction_type_traffic_violation') }}</option>
              <option value="attestations">{{ t('attendance.deduction_type_attestations') }}</option>
            </select>
            <p v-if="editForm.errors.deduction_type" class="text-sm text-red-500 mt-1">{{ editForm.errors.deduction_type }}</p>
          </div>
          <div>
            <Label for="edit-reason">
              {{ t('attendance.deduction_reason') }}
              <span class="text-muted-foreground font-normal text-xs">({{ t('common.optional') }})</span>
            </Label>
            <textarea
              id="edit-reason"
              v-model="editForm.reason"
              rows="3"
              class="mt-1 flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
              :placeholder="t('attendance.deduction_reason_placeholder')"
            />
            <p v-if="editForm.errors.reason" class="text-sm text-red-500 mt-1">{{ editForm.errors.reason }}</p>
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" @click="closeEditModal">
              {{ t('common.cancel') }}
            </Button>
            <Button type="submit" :disabled="editForm.processing">
              {{ t('common.save') }}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>

    <!-- Reject approved attendance penalty (same backend as attendance page) -->
    <Dialog :open="penaltyRejectModalOpen" @update:open="onPenaltyRejectModalOpenChange">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{{ t('attendance.reject_penalty') }}</DialogTitle>
          <DialogDescription>
            {{ t('attendance.reject_penalty_description') }}
          </DialogDescription>
          <p class="text-sm text-muted-foreground pt-2">
            {{ t('attendance.reject_penalty_from_deductions_hint') }}
          </p>
        </DialogHeader>
        <div class="space-y-4">
          <div>
            <Label for="deductions-rejection-reason">{{ t('attendance.rejection_reason') }} ({{ t('common.optional') }})</Label>
            <textarea
              id="deductions-rejection-reason"
              v-model="penaltyRejectionReason"
              rows="4"
              class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 mt-1"
              :placeholder="t('attendance.rejection_reason_placeholder')"
            />
          </div>
          <div>
            <Label for="deductions-rejection-attachment">{{ t('attendance.rejection_attachment') }} ({{ t('common.optional') }})</Label>
            <Input
              id="deductions-rejection-attachment"
              type="file"
              accept=".pdf,.jpg,.jpeg,.png"
              class="mt-1"
              @change="onPenaltyRejectionFileChange"
            />
            <p class="text-xs text-muted-foreground mt-1">{{ t('attendance.rejection_attachment_hint') }}</p>
          </div>
        </div>
        <DialogFooter>
          <Button type="button" variant="outline" @click="closePenaltyRejectModal">
            {{ t('common.cancel') }}
          </Button>
          <Button type="button" variant="destructive" @click="submitPenaltyReject">
            {{ t('attendance.reject') }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </AppLayout>
</template>

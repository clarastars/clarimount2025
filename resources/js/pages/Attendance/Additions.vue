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
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Badge } from '@/components/ui/badge'
import PayrollMonthFilter from '@/components/attendance/PayrollMonthFilter.vue'
import { Banknote, Plus, Pencil, Trash2 } from 'lucide-vue-next'
import axios from 'axios'

const { t } = useI18n()

interface Company {
  id: number
  name_en: string
  name_ar?: string
}

interface EmployeeOption {
  id: number
  first_name: string
  last_name: string
  employee_id?: string
  company_id: number
  basic_salary?: number | string | null
  allowances?: number | string | null
}

type AmountInputMode = 'manual' | 'basic_days' | 'basic_daily_percent' | 'gross_days' | 'gross_daily_percent'
type AdditionType = 'monthly_entitlement' | 'overtime'

interface ManualAddition {
  id: number
  employee_id: number
  employee_name: string
  employee_code?: string
  date: string
  amount: number
  amount_input_mode?: AmountInputMode
  amount_input_days?: number | null
  amount_input_percent?: number | null
  addition_type: AdditionType
  reason: string
  created_at: string
  creator_name: string | null
}

const props = defineProps<{
  company: Company
  companies: Company[]
  employees: EmployeeOption[]
  month: string
  monthPeriodStart: string
  monthPeriodEnd: string
  employeeId: number | null
  manualAdditions: ManualAddition[]
}>()

const createModalOpen = ref(false)
const createForm = useForm({
  company_id: props.company.id,
  employee_id: '' as number | '',
  amount_input_mode: 'manual' as AmountInputMode,
  amount: '',
  amount_input_days: '',
  amount_input_percent: '',
  addition_date: new Date().toISOString().slice(0, 10),
  addition_type: 'monthly_entitlement' as AdditionType,
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
    if (props.company.id === createForm.company_id) employeesForCreate.value = list
  },
  { deep: true }
)

async function onCompanyChangeInModal(companyId: number) {
  createForm.company_id = companyId
  createForm.employee_id = ''
  try {
    const { data } = await axios.get(route('api.employees.search'), { params: { company_id: companyId, q: '' } })
    employeesForCreate.value = data || []
  } catch {
    employeesForCreate.value = []
  }
}

function openCreateModal() {
  createForm.reset()
  createForm.company_id = props.company.id
  createForm.addition_date = new Date().toISOString().slice(0, 10)
  createForm.amount_input_mode = 'manual'
  createForm.addition_type = 'monthly_entitlement'
  createModalOpen.value = true
  employeesForCreate.value = props.employees
}

function submitCreate() {
  createForm.post(route('attendance.additions.store'), {
    preserveScroll: true,
    onSuccess: () => {
      createModalOpen.value = false
      createForm.reset()
    },
  })
}

const editModalOpen = ref(false)
const selectedAddition = ref<ManualAddition | null>(null)
const editForm = useForm({
  amount_input_mode: 'manual' as AmountInputMode,
  amount: '',
  amount_input_days: '',
  amount_input_percent: '',
  addition_date: '',
  addition_type: 'monthly_entitlement' as AdditionType,
  reason: '',
})

function toNum(v: string): number | null {
  const n = parseFloat(String(v).replace(',', '.'))
  return Number.isFinite(n) ? n : null
}

function basicDailyWageSar(emp: EmployeeOption | undefined): number | null {
  if (!emp) return null
  const b = Number(emp.basic_salary ?? 0)
  if (!Number.isFinite(b) || b <= 0) return null
  return Math.round((b / 30) * 1e6) / 1e6
}

function grossDailyWageSar(emp: EmployeeOption | undefined): number | null {
  if (!emp) return null
  const b = Number(emp.basic_salary ?? 0)
  const a = Number(emp.allowances ?? 0)
  if (!Number.isFinite(b) || !Number.isFinite(a)) return null
  const gross = b + a
  if (gross <= 0) return null
  return Math.round((gross / 30) * 1e6) / 1e6
}

function resolvePreviewAmount(
  mode: AmountInputMode,
  basicDaily: number | null,
  grossDaily: number | null,
  manual: string,
  daysStr: string,
  pctStr: string
): number | null {
  if (mode === 'manual') {
    const a = toNum(manual)
    return a !== null && a > 0 ? Math.round(a * 100) / 100 : null
  }
  if (mode === 'basic_days') {
    if (basicDaily == null) return null
    const d = toNum(daysStr)
    return d !== null && d > 0 ? Math.round(d * basicDaily * 100) / 100 : null
  }
  if (mode === 'basic_daily_percent') {
    if (basicDaily == null) return null
    const p = toNum(pctStr)
    return p !== null && p > 0 ? Math.round((p / 100) * basicDaily * 100) / 100 : null
  }
  if (mode === 'gross_days') {
    if (grossDaily == null) return null
    const d = toNum(daysStr)
    return d !== null && d > 0 ? Math.round(d * grossDaily * 100) / 100 : null
  }
  if (mode === 'gross_daily_percent') {
    if (grossDaily == null) return null
    const p = toNum(pctStr)
    return p !== null && p > 0 ? Math.round((p / 100) * grossDaily * 100) / 100 : null
  }
  return null
}

const createSelectedEmployee = computed(() => {
  const id = createForm.employee_id
  if (id === '' || id == null) return undefined
  return employeesForCreate.value.find((e) => e.id === id)
})
const createAmountPreview = computed(() =>
  resolvePreviewAmount(
    createForm.amount_input_mode,
    basicDailyWageSar(createSelectedEmployee.value),
    grossDailyWageSar(createSelectedEmployee.value),
    createForm.amount,
    createForm.amount_input_days,
    createForm.amount_input_percent
  )
)
const needsBasicForCreate = computed(() => createForm.amount_input_mode === 'basic_days' || createForm.amount_input_mode === 'basic_daily_percent')
const hasBasicForCreate = computed(() => basicDailyWageSar(createSelectedEmployee.value) != null)
const needsGrossForCreate = computed(() => createForm.amount_input_mode === 'gross_days' || createForm.amount_input_mode === 'gross_daily_percent')
const hasGrossForCreate = computed(() => grossDailyWageSar(createSelectedEmployee.value) != null)

const editSelectedEmployee = computed(() => {
  const id = selectedAddition.value?.employee_id
  if (id == null) return undefined
  return props.employees.find((e) => e.id === id)
})
const editAmountPreview = computed(() =>
  resolvePreviewAmount(
    editForm.amount_input_mode,
    basicDailyWageSar(editSelectedEmployee.value),
    grossDailyWageSar(editSelectedEmployee.value),
    editForm.amount,
    editForm.amount_input_days,
    editForm.amount_input_percent
  )
)
const needsBasicForEdit = computed(() => editForm.amount_input_mode === 'basic_days' || editForm.amount_input_mode === 'basic_daily_percent')
const hasBasicForEdit = computed(() => basicDailyWageSar(editSelectedEmployee.value) != null)
const needsGrossForEdit = computed(() => editForm.amount_input_mode === 'gross_days' || editForm.amount_input_mode === 'gross_daily_percent')
const hasGrossForEdit = computed(() => grossDailyWageSar(editSelectedEmployee.value) != null)

function openEditModal(row: ManualAddition) {
  selectedAddition.value = row
  editForm.reset()
  const mode = (row.amount_input_mode ?? 'manual') as AmountInputMode
  editForm.amount_input_mode = mode
  if (mode === 'manual') {
    editForm.amount = String(row.amount)
  } else {
    editForm.amount_input_days = row.amount_input_days != null ? String(row.amount_input_days) : ''
    editForm.amount_input_percent = row.amount_input_percent != null ? String(row.amount_input_percent) : ''
  }
  editForm.addition_date = row.date
  editForm.addition_type = row.addition_type
  editForm.reason = row.reason
  editModalOpen.value = true
}

function closeEditModal() {
  editModalOpen.value = false
  selectedAddition.value = null
}

function submitEdit() {
  if (!selectedAddition.value) return
  editForm.put(route('attendance.additions.update', selectedAddition.value.id), {
    preserveScroll: true,
    onSuccess: () => closeEditModal(),
  })
}

function confirmDelete(row: ManualAddition) {
  if (!confirm(t('attendance.delete_addition_confirm'))) return
  router.delete(route('attendance.additions.destroy', row.id), { preserveScroll: true })
}

const employeeFilter = ref<number | ''>(props.employeeId ?? '')

watch(
  () => props.employeeId,
  (id) => {
    employeeFilter.value = id ?? ''
  }
)

function applyFilters(params: { month?: string; employee_id?: number | '' }) {
  const q: Record<string, string | number | undefined> = {}
  if (params.month) q.month = params.month
  if (params.employee_id !== undefined && params.employee_id !== '') q.employee_id = params.employee_id
  router.get(route('attendance.additions', props.company.id), q, { preserveState: true, preserveScroll: true })
}

function onPayrollMonthChange(ym: string) {
  applyFilters({ month: ym, employee_id: employeeFilter.value })
}

watch(employeeFilter, (v) => applyFilters({ month: props.month, employee_id: v }))

function goToCompany(companyId: number) {
  router.get(route('attendance.additions', companyId), {
    month: props.month,
    employee_id: employeeFilter.value || undefined,
  })
}

function onFilterCompanyChange(event: Event) {
  const value = Number((event.target as HTMLSelectElement).value)
  goToCompany(value)
}

function onCreateCompanySelectChange(event: Event) {
  const value = Number((event.target as HTMLSelectElement).value)
  onCompanyChangeInModal(value)
}

function formatDate(dateStr: string) {
  if (!dateStr) return '-'
  try {
    return new Date(dateStr + 'Z').toLocaleDateString(undefined, { year: 'numeric', month: '2-digit', day: '2-digit' })
  } catch {
    return dateStr
  }
}
function formatCurrency(amount: number) {
  if (amount == null || Number.isNaN(amount)) return '-'
  return Number(amount).toFixed(2) + ' SAR'
}
function additionTypeLabel(type: string) {
  const map: Record<string, string> = {
    monthly_entitlement: t('attendance.addition_type_monthly_entitlement'),
    overtime: t('attendance.addition_type_overtime'),
  }
  return map[type] ?? type
}

const breadcrumbs = computed((): BreadcrumbItem[] => [
  { title: t('nav.dashboard'), href: '/dashboard' },
  { title: t('companies.title'), href: '/companies' },
  { title: props.company?.name_ar || props.company?.name_en || t('companies.title'), href: `/companies/${props.company?.id}` },
  { title: t('attendance.title'), href: `/companies/${props.company?.id}/attendance` },
  { title: t('attendance.additions_title'), href: `/companies/${props.company?.id}/attendance/additions` },
])
</script>

<template>
  <Head :title="t('attendance.additions_title')" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="py-12 w-full">
      <div class="w-full px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
          <div>
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">{{ t('attendance.additions_title') }}</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ t('attendance.additions_description') }}</p>
          </div>
          <Button class="gap-2 cursor-pointer bg-blue-600 hover:bg-blue-700" @click="openCreateModal">
            <Plus class="w-4 h-4" />{{ t('attendance.create_addition') }}
          </Button>
        </div>

        <Card class="mb-6">
          <CardHeader><CardTitle>{{ t('common.filters') }}</CardTitle></CardHeader>
          <CardContent>
            <div class="flex flex-col gap-6">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <Label for="filter-company">{{ t('attendance.filter_company') }}</Label>
                  <select id="filter-company" :value="company.id" class="mt-1 flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm" @change="onFilterCompanyChange">
                    <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name_ar || c.name_en }}</option>
                  </select>
                </div>
                <div>
                  <Label for="filter-employee">{{ t('attendance.filter_employee') }}</Label>
                  <select id="filter-employee" v-model="employeeFilter" class="mt-1 flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                    <option value="">{{ t('attendance.all_employees') }}</option>
                    <option v-for="emp in employees" :key="emp.id" :value="emp.id">{{ emp.first_name }} {{ emp.last_name }}<template v-if="emp.employee_id"> ({{ emp.employee_id }})</template></option>
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

        <Card>
          <CardHeader><CardTitle class="flex items-center gap-2"><Banknote class="w-5 h-5" />{{ t('attendance.additions_title') }}</CardTitle></CardHeader>
          <CardContent>
            <div v-if="manualAdditions.length === 0" class="text-center py-12 text-muted-foreground">
              <p class="font-medium">{{ t('attendance.no_additions') }}</p>
              <p class="text-sm mt-1">{{ t('attendance.no_additions_description') }}</p>
            </div>
            <div v-else class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
              <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                  <tr>
                    <th class="px-4 py-3 text-start text-xs font-semibold uppercase">{{ t('attendance.addition_type') }}</th>
                    <th class="px-4 py-3 text-start text-xs font-semibold uppercase">{{ t('attendance.employee_name') }}</th>
                    <th class="px-4 py-3 text-start text-xs font-semibold uppercase">{{ t('attendance.date') }}</th>
                    <th class="px-4 py-3 text-start text-xs font-semibold uppercase">{{ t('attendance.addition_amount') }}</th>
                    <th class="px-4 py-3 text-start text-xs font-semibold uppercase">{{ t('attendance.addition_reason') }}</th>
                    <th class="px-4 py-3 text-start text-xs font-semibold uppercase">{{ t('attendance.created_by_label') }}</th>
                    <th class="px-4 py-3 text-start text-xs font-semibold uppercase w-28">{{ t('common.actions') }}</th>
                  </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                  <tr v-for="row in manualAdditions" :key="row.id" class="hover:bg-gray-50 dark:hover:bg-gray-800">
                    <td class="px-4 py-3 text-sm"><Badge variant="default">{{ additionTypeLabel(row.addition_type) }}</Badge></td>
                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ row.employee_name }}<span v-if="row.employee_code" class="text-muted-foreground"> ({{ row.employee_code }})</span></td>
                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ formatDate(row.date) }}</td>
                    <td class="px-4 py-3 text-sm"><span class="font-medium text-green-700 dark:text-green-400">{{ formatCurrency(row.amount) }}</span></td>
                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 max-w-xs truncate" :title="row.reason">{{ row.reason?.trim() ? row.reason : '—' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ row.creator_name || '-' }}</td>
                    <td class="px-4 py-3 text-sm">
                      <div class="flex gap-1">
                        <Button variant="ghost" size="sm" class="h-8 w-8 p-0" @click="openEditModal(row)" :title="t('attendance.edit_addition')"><Pencil class="h-4 w-4" /></Button>
                        <Button variant="ghost" size="sm" class="h-8 w-8 p-0 text-red-600 hover:text-red-700" @click="confirmDelete(row)" :title="t('attendance.delete_addition')"><Trash2 class="h-4 w-4" /></Button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>

    <Dialog :open="createModalOpen" @update:open="(v: boolean) => (createModalOpen = v)">
      <DialogContent class="max-w-lg max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{{ t('attendance.create_addition') }}</DialogTitle>
          <DialogDescription>{{ t('attendance.additions_description') }}</DialogDescription>
        </DialogHeader>
        <form @submit.prevent="submitCreate" class="space-y-4">
          <div><Label for="create-company">{{ t('attendance.filter_company') }}</Label><select id="create-company" :value="createForm.company_id" class="mt-1 flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm" @change="onCreateCompanySelectChange"><option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name_ar || c.name_en }}</option></select></div>
          <div><Label for="create-employee">{{ t('attendance.filter_employee') }}</Label><select id="create-employee" v-model="createForm.employee_id" class="mt-1 flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm" required><option value="">{{ t('attendance.filter_employee') }}</option><option v-for="emp in employeesForCreate" :key="emp.id" :value="emp.id">{{ emp.first_name }} {{ emp.last_name }}<template v-if="emp.employee_id"> ({{ emp.employee_id }})</template></option></select><p v-if="createForm.errors.employee_id" class="text-sm text-red-500 mt-1">{{ createForm.errors.employee_id }}</p></div>
          <div><Label for="create-amount-mode">{{ t('attendance.deduction_amount_mode') }}</Label><select id="create-amount-mode" v-model="createForm.amount_input_mode" class="mt-1 flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"><option value="manual">{{ t('attendance.deduction_mode_manual') }}</option><option value="basic_days">{{ t('attendance.deduction_mode_basic_days') }}</option><option value="basic_daily_percent">{{ t('attendance.deduction_mode_basic_daily_percent') }}</option><option value="gross_days">{{ t('attendance.deduction_mode_gross_days') }}</option><option value="gross_daily_percent">{{ t('attendance.deduction_mode_gross_daily_percent') }}</option></select></div>
          <div v-if="needsBasicForCreate && createForm.employee_id && !hasBasicForCreate" class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">{{ t('attendance.addition_basic_salary_unavailable') }}</div>
          <div v-if="needsGrossForCreate && createForm.employee_id && !hasGrossForCreate" class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">{{ t('attendance.addition_gross_salary_unavailable') }}</div>
          <div v-if="createForm.amount_input_mode === 'manual'"><Label for="create-amount">{{ t('attendance.addition_amount') }}</Label><Input id="create-amount" v-model="createForm.amount" type="number" step="0.01" min="0.01" class="mt-1" /><p v-if="createForm.errors.amount" class="text-sm text-red-500 mt-1">{{ createForm.errors.amount }}</p></div>
          <div v-else-if="createForm.amount_input_mode === 'basic_days' || createForm.amount_input_mode === 'gross_days'"><Label for="create-input-days">{{ createForm.amount_input_mode === 'gross_days' ? t('attendance.deduction_input_gross_days') : t('attendance.deduction_input_days') }}</Label><Input id="create-input-days" v-model="createForm.amount_input_days" type="number" step="any" min="0.01" class="mt-1" /><p v-if="createForm.errors.amount_input_days" class="text-sm text-red-500 mt-1">{{ createForm.errors.amount_input_days }}</p><p v-if="createForm.errors.amount" class="text-sm text-red-500 mt-1">{{ createForm.errors.amount }}</p></div>
          <div v-else><Label for="create-input-pct">{{ createForm.amount_input_mode === 'gross_daily_percent' ? t('attendance.deduction_input_gross_percent') : t('attendance.deduction_input_percent') }}</Label><Input id="create-input-pct" v-model="createForm.amount_input_percent" type="number" step="any" min="0.01" max="100" class="mt-1" /><p v-if="createForm.errors.amount_input_percent" class="text-sm text-red-500 mt-1">{{ createForm.errors.amount_input_percent }}</p><p v-if="createForm.errors.amount" class="text-sm text-red-500 mt-1">{{ createForm.errors.amount }}</p></div>
          <div v-if="createAmountPreview != null && createForm.employee_id" class="text-sm text-muted-foreground"><span class="font-medium text-foreground">{{ t('attendance.addition_computed_amount') }}:</span> {{ formatCurrency(createAmountPreview) }}</div>
          <div><Label for="create-date">{{ t('attendance.addition_date') }}</Label><Input id="create-date" v-model="createForm.addition_date" type="date" class="mt-1" required /><p v-if="createForm.errors.addition_date" class="text-sm text-red-500 mt-1">{{ createForm.errors.addition_date }}</p></div>
          <div><Label for="create-addition-type">{{ t('attendance.addition_type') }}</Label><select id="create-addition-type" v-model="createForm.addition_type" class="mt-1 flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm" required><option value="monthly_entitlement">{{ t('attendance.addition_type_monthly_entitlement') }}</option><option value="overtime">{{ t('attendance.addition_type_overtime') }}</option></select><p v-if="createForm.errors.addition_type" class="text-sm text-red-500 mt-1">{{ createForm.errors.addition_type }}</p></div>
          <div><Label for="create-reason">{{ t('attendance.addition_reason') }} <span class="text-muted-foreground font-normal text-xs">({{ t('common.optional') }})</span></Label><textarea id="create-reason" v-model="createForm.reason" rows="3" class="mt-1 flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm" :placeholder="t('attendance.addition_reason_placeholder')" /><p v-if="createForm.errors.reason" class="text-sm text-red-500 mt-1">{{ createForm.errors.reason }}</p></div>
          <DialogFooter><Button type="button" variant="outline" @click="createModalOpen = false">{{ t('common.cancel') }}</Button><Button type="submit" :disabled="createForm.processing">{{ t('common.save') }}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>

    <Dialog :open="editModalOpen" @update:open="(v: boolean) => !v && closeEditModal()">
      <DialogContent class="max-w-lg max-h-[90vh] overflow-y-auto">
        <DialogHeader><DialogTitle>{{ t('attendance.edit_addition') }}</DialogTitle><DialogDescription v-if="selectedAddition">{{ selectedAddition.employee_name }}</DialogDescription></DialogHeader>
        <form v-if="selectedAddition" @submit.prevent="submitEdit" class="space-y-4">
          <div><Label class="text-muted-foreground">{{ t('attendance.filter_employee') }}</Label><p class="mt-1 text-sm font-medium">{{ selectedAddition.employee_name }}</p></div>
          <div><Label for="edit-amount-mode">{{ t('attendance.deduction_amount_mode') }}</Label><select id="edit-amount-mode" v-model="editForm.amount_input_mode" class="mt-1 flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"><option value="manual">{{ t('attendance.deduction_mode_manual') }}</option><option value="basic_days">{{ t('attendance.deduction_mode_basic_days') }}</option><option value="basic_daily_percent">{{ t('attendance.deduction_mode_basic_daily_percent') }}</option><option value="gross_days">{{ t('attendance.deduction_mode_gross_days') }}</option><option value="gross_daily_percent">{{ t('attendance.deduction_mode_gross_daily_percent') }}</option></select></div>
          <div v-if="needsBasicForEdit && !hasBasicForEdit" class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">{{ t('attendance.addition_basic_salary_unavailable') }}</div>
          <div v-if="needsGrossForEdit && !hasGrossForEdit" class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">{{ t('attendance.addition_gross_salary_unavailable') }}</div>
          <div v-if="editForm.amount_input_mode === 'manual'"><Label for="edit-amount">{{ t('attendance.addition_amount') }}</Label><Input id="edit-amount" v-model="editForm.amount" type="number" step="0.01" min="0.01" class="mt-1" /><p v-if="editForm.errors.amount" class="text-sm text-red-500 mt-1">{{ editForm.errors.amount }}</p></div>
          <div v-else-if="editForm.amount_input_mode === 'basic_days' || editForm.amount_input_mode === 'gross_days'"><Label for="edit-input-days">{{ editForm.amount_input_mode === 'gross_days' ? t('attendance.deduction_input_gross_days') : t('attendance.deduction_input_days') }}</Label><Input id="edit-input-days" v-model="editForm.amount_input_days" type="number" step="any" min="0.01" class="mt-1" /><p v-if="editForm.errors.amount_input_days" class="text-sm text-red-500 mt-1">{{ editForm.errors.amount_input_days }}</p><p v-if="editForm.errors.amount" class="text-sm text-red-500 mt-1">{{ editForm.errors.amount }}</p></div>
          <div v-else><Label for="edit-input-pct">{{ editForm.amount_input_mode === 'gross_daily_percent' ? t('attendance.deduction_input_gross_percent') : t('attendance.deduction_input_percent') }}</Label><Input id="edit-input-pct" v-model="editForm.amount_input_percent" type="number" step="any" min="0.01" max="100" class="mt-1" /><p v-if="editForm.errors.amount_input_percent" class="text-sm text-red-500 mt-1">{{ editForm.errors.amount_input_percent }}</p><p v-if="editForm.errors.amount" class="text-sm text-red-500 mt-1">{{ editForm.errors.amount }}</p></div>
          <div v-if="editAmountPreview != null" class="text-sm text-muted-foreground"><span class="font-medium text-foreground">{{ t('attendance.addition_computed_amount') }}:</span> {{ formatCurrency(editAmountPreview) }}</div>
          <div><Label for="edit-date">{{ t('attendance.addition_date') }}</Label><Input id="edit-date" v-model="editForm.addition_date" type="date" class="mt-1" required /><p v-if="editForm.errors.addition_date" class="text-sm text-red-500 mt-1">{{ editForm.errors.addition_date }}</p></div>
          <div><Label for="edit-addition-type">{{ t('attendance.addition_type') }}</Label><select id="edit-addition-type" v-model="editForm.addition_type" class="mt-1 flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm" required><option value="monthly_entitlement">{{ t('attendance.addition_type_monthly_entitlement') }}</option><option value="overtime">{{ t('attendance.addition_type_overtime') }}</option></select><p v-if="editForm.errors.addition_type" class="text-sm text-red-500 mt-1">{{ editForm.errors.addition_type }}</p></div>
          <div><Label for="edit-reason">{{ t('attendance.addition_reason') }} <span class="text-muted-foreground font-normal text-xs">({{ t('common.optional') }})</span></Label><textarea id="edit-reason" v-model="editForm.reason" rows="3" class="mt-1 flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm" :placeholder="t('attendance.addition_reason_placeholder')" /><p v-if="editForm.errors.reason" class="text-sm text-red-500 mt-1">{{ editForm.errors.reason }}</p></div>
          <DialogFooter><Button type="button" variant="outline" @click="closeEditModal">{{ t('common.cancel') }}</Button><Button type="submit" :disabled="editForm.processing">{{ t('common.save') }}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  </AppLayout>
</template>


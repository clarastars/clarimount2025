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

interface ManualDeduction {
  id: number
  type: 'manual'
  employee_id: number
  employee_name: string
  employee_code?: string
  date: string
  amount: number
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
  employeeId: number | null
  approvedPenalties: ApprovedPenalty[]
  manualDeductions: ManualDeduction[]
}>()

const createModalOpen = ref(false)
const createForm = useForm({
  company_id: props.company.id,
  employee_id: '' as number | '',
  amount: '',
  deduction_date: new Date().toISOString().slice(0, 10),
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
  amount: '',
  deduction_date: '',
  reason: '',
})

function openEditModal(row: ManualDeduction) {
  selectedDeduction.value = row
  editForm.reset()
  editForm.amount = String(row.amount)
  editForm.deduction_date = row.date
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
    month: monthValue.value,
    employee_id: employeeFilter.value || undefined,
  })
}

const monthValue = ref(props.month)
const employeeFilter = ref<number | ''>(props.employeeId ?? '')

watch(monthValue, (v) => applyFilters({ month: v, employee_id: employeeFilter.value }))
watch(employeeFilter, (v) => applyFilters({ month: monthValue.value, employee_id: v }))

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
          <Button class="gap-2 cursor-pointer bg-blue-600 hover:bg-blue-700" @click="openCreateModal">
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
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
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
                <Label for="filter-month">{{ t('attendance.filter_month') }}</Label>
                <Input
                  id="filter-month"
                  v-model="monthValue"
                  type="month"
                  class="mt-1"
                />
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
                    {{ emp.first_name }} {{ emp.last_name }}
                    <template v-if="emp.employee_id"> ({{ emp.employee_id }})</template>
                  </option>
                </select>
              </div>
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
                        {{ t('attendance.deduction_type_manual') }}
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
                        <span class="font-medium">{{ row.amount }}</span>
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
                      {{ isManual(row) ? row.reason : row.reason_text }}
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
                      <template v-if="isManual(row)">
                        <div class="flex gap-1">
                          <Button variant="ghost" size="sm" class="h-8 w-8 p-0" @click="openEditModal(row)" :title="t('attendance.edit_deduction')">
                            <Pencil class="h-4 w-4" />
                          </Button>
                          <Button variant="ghost" size="sm" class="h-8 w-8 p-0 text-red-600 hover:text-red-700" @click="confirmDelete(row)" :title="t('attendance.delete_deduction')">
                            <Trash2 class="h-4 w-4" />
                          </Button>
                        </div>
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
      <DialogContent class="max-w-md">
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
                {{ emp.first_name }} {{ emp.last_name }}
                <template v-if="emp.employee_id"> ({{ emp.employee_id }})</template>
              </option>
            </select>
            <p v-if="createForm.errors.employee_id" class="text-sm text-red-500 mt-1">
              {{ createForm.errors.employee_id }}
            </p>
          </div>
          <div>
            <Label for="create-amount">{{ t('attendance.deduction_amount') }}</Label>
            <Input
              id="create-amount"
              v-model="createForm.amount"
              type="number"
              step="0.01"
              min="0.01"
              class="mt-1"
              required
            />
            <p v-if="createForm.errors.amount" class="text-sm text-red-500 mt-1">
              {{ createForm.errors.amount }}
            </p>
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
            <Label for="create-reason">{{ t('attendance.deduction_reason') }}</Label>
            <textarea
              id="create-reason"
              v-model="createForm.reason"
              rows="3"
              class="mt-1 flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
              :placeholder="t('attendance.deduction_reason_placeholder')"
              required
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
      <DialogContent class="max-w-md">
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
            <Label for="edit-amount">{{ t('attendance.deduction_amount') }}</Label>
            <Input
              id="edit-amount"
              v-model="editForm.amount"
              type="number"
              step="0.01"
              min="0.01"
              class="mt-1"
              required
            />
            <p v-if="editForm.errors.amount" class="text-sm text-red-500 mt-1">{{ editForm.errors.amount }}</p>
          </div>
          <div>
            <Label for="edit-date">{{ t('attendance.deduction_date') }}</Label>
            <Input id="edit-date" v-model="editForm.deduction_date" type="date" class="mt-1" required />
            <p v-if="editForm.errors.deduction_date" class="text-sm text-red-500 mt-1">{{ editForm.errors.deduction_date }}</p>
          </div>
          <div>
            <Label for="edit-reason">{{ t('attendance.deduction_reason') }}</Label>
            <textarea
              id="edit-reason"
              v-model="editForm.reason"
              rows="3"
              class="mt-1 flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
              :placeholder="t('attendance.deduction_reason_placeholder')"
              required
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
  </AppLayout>
</template>

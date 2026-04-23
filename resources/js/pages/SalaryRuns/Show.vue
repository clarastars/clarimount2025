<template>
  <Head :title="t('salary_runs.salary_run_details')" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <Heading :title="t('salary_runs.salary_run_details')" />
          <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
            {{ getMonthName(salaryRun.month) }} {{ salaryRun.year }}
          </p>
        </div>
        <div class="flex gap-2 flex-wrap">
          <Badge :variant="salaryRun.status === 'finalized' ? 'default' : 'secondary'">
            {{ salaryRun.status === 'finalized' ? t('salary_runs.status_finalized') : t('salary_runs.status_draft') }}
          </Badge>
          <Button
            as="a"
            :href="route('salary-runs.export-excel', [company.id, salaryRun.id])"
            target="_blank"
            rel="noopener noreferrer"
            class="gap-2 bg-green-600 hover:bg-green-700 text-white border-0"
          >
            <Icon name="FileSpreadsheet" class="h-4 w-4" />
            {{ t('salary_runs.export_excel') }}
          </Button>
          <Button
            v-if="salaryRun.status === 'draft'"
            @click="finalizeSalaryRun"
            :disabled="finalizing"
            class="bg-green-600 hover:bg-green-700 text-white font-semibold"
          >
            <Icon name="Check" class="mr-2 rtl:mr-0 rtl:ml-2 h-4 w-4" />
            {{ t('salary_runs.finalize') }}
          </Button>
        </div>
      </div>

      <!-- Approvals (4 steps) -->
      <Card>
        <CardHeader>
          <CardTitle class="text-base">{{ t('salary_runs.approvals_section') }}</CardTitle>
        </CardHeader>
        <CardContent>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div
              v-for="(approval, key) in approvalList"
              :key="key"
              class="rounded-lg border p-4 flex flex-col justify-between"
              :class="approval.approved_at ? 'border-green-200 bg-green-50/50 dark:bg-green-950/20 dark:border-green-800' : 'border-gray-200 dark:border-gray-700'"
            >
              <div class="font-medium text-sm text-gray-900 dark:text-gray-100 mb-2">
                {{ approval.label }}
              </div>
              <div v-if="approval.approved_at" class="text-sm space-y-1">
                <div class="text-gray-600 dark:text-gray-400">
                  <span class="text-muted-foreground">{{ t('salary_runs.approval_date_label') }}:</span>
                  {{ formatApprovalDate(approval.approved_at) }}
                </div>
                <div class="text-gray-600 dark:text-gray-400">
                  <span class="text-muted-foreground">{{ t('salary_runs.approval_time_label') }}:</span>
                  {{ formatApprovalTime(approval.approved_at) }}
                </div>
                <div class="font-medium text-gray-900 dark:text-gray-100 pt-0.5">
                  <span class="text-muted-foreground">{{ t('salary_runs.approval_by_label') }}:</span>
                  {{ approval.approver_name || '-' }}
                </div>
              </div>
              <div v-else class="space-y-2">
                <p class="text-sm text-amber-600 dark:text-amber-400">{{ t('salary_runs.approval_pending') }}</p>
                <Button
                  v-if="approval.can_approve"
                  size="sm"
                  class="w-full"
                  :disabled="approvingStep === approval.key"
                  @click="openApprovalConfirm(approval.key)"
                >
                  {{ approvingStep === approval.key ? '...' : t('salary_runs.approval_approve') }}
                </Button>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Summary Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <Card>
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium">{{ t('salary_runs.employees_count') }}</CardTitle>
            <Icon name="Users" class="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">{{ salaryRun.items?.length || 0 }}</div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium">{{ t('salary_runs.gross_salary') }}</CardTitle>
            <Icon name="CreditCard" class="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">
              {{ formatCurrency(totalGross) }}
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium">{{ t('salary_runs.penalties_total') }}</CardTitle>
            <Icon name="AlertTriangle" class="h-4 w-4 text-orange-600" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold text-orange-600">
              {{ formatCurrency(totalPenalties) }}
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium">{{ t('salary_runs.social_insurance_deduction') }}</CardTitle>
            <Icon name="Shield" class="h-4 w-4 text-rose-600" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold text-rose-600">
              {{ formatCurrency(totalSocialInsuranceDeductions) }}
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium">{{ t('debts.total_debt_deductions') }}</CardTitle>
            <Icon name="CreditCard" class="h-4 w-4 text-purple-600" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold text-purple-600">
              {{ formatCurrency(totalDebtDeductions) }}
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium">{{ t('salary_runs.net_salary') }}</CardTitle>
            <Icon name="CheckCircle" class="h-4 w-4 text-green-600" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold text-green-600">
              {{ formatCurrency(totalNet) }}
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Salary Run Items Table -->
      <Card>
        <CardContent class="pt-6">
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead class="bg-gray-50 dark:bg-gray-800">
                <tr class="text-left rtl:text-right">
                  <th class="px-6 py-4 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    {{ t('salary_runs.employee') }}
                  </th>
                  <th class="px-6 py-4 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    {{ t('salary_runs.basic_salary') }}
                  </th>
                  <th class="px-6 py-4 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    {{ t('salary_runs.allowances') }}
                  </th>
                  <th class="px-6 py-4 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    {{ t('salary_runs.gross_salary') }}
                  </th>
                  <th class="px-6 py-4 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    {{ t('salary_runs.penalties_total') }}
                  </th>
                  <th class="px-6 py-4 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    {{ t('salary_runs.social_insurance_deduction') }}
                  </th>
                  <th class="px-6 py-4 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    {{ t('debts.total_debts') }}
                  </th>
                  <th class="px-6 py-4 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    {{ t('debts.debt_deductions') }}
                  </th>
                  <th class="px-6 py-4 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    {{ t('salary_runs.net_salary') }}
                  </th>
                  <th class="px-6 py-4 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider text-right rtl:text-left">
                    {{ t('common.actions') }}
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-for="item in salaryRun.items" :key="item.id" class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                      {{ getEmployeeFullName(item.employee) }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                      {{ item.employee?.employee_id }}
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900 dark:text-gray-100">
                      {{ formatCurrency(item.basic_salary) }}
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900 dark:text-gray-100">
                      {{ formatCurrency(item.allowances) }}
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                      {{ formatCurrency(item.gross_salary) }}
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-orange-600 dark:text-orange-400">
                      {{ formatCurrency(item.penalties_total) }}
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-rose-600 dark:text-rose-400">
                      {{ formatCurrency(item.social_insurance_deduction_total || 0) }}
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-blue-600 dark:text-blue-400">
                      {{ formatCurrency(getTotalDebtsAmount(item)) }}
                    </div>
                    <div v-if="item.employee?.debts && item.employee.debts.length > 0" class="text-xs text-gray-500 mt-1">
                      {{ item.employee.debts.length }} {{ t('debts.debt') }}
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-purple-600 dark:text-purple-400">
                      {{ formatCurrency(getDebtDeductionsTotal(item)) }}
                    </div>
                    <Button
                      v-if="salaryRun.status === 'draft' && item.employee?.debts && item.employee.debts.length > 0"
                      variant="ghost"
                      size="sm"
                      @click="openDebtDeductionsModal(item)"
                      class="mt-1"
                    >
                      <Icon name="SquarePen" class="h-3 w-3" />
                    </Button>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-bold text-green-600 dark:text-green-400">
                      {{ formatCurrency(item.net_salary) }}
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right rtl:text-left">
                    <div class="flex gap-2 justify-end">
                      <Button
                        v-if="item.breakdown && item.breakdown.length > 0"
                        variant="ghost"
                        size="sm"
                        @click="openBreakdownModal(item)"
                      >
                        <Icon name="Eye" class="h-4 w-4" />
                        {{ t('salary_runs.breakdown') }}
                      </Button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </CardContent>
      </Card>

      <!-- Breakdown Modal -->
      <Dialog :open="breakdownModalOpen" @update:open="closeBreakdownModal">
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{{ t('salary_runs.breakdown') }}</DialogTitle>
            <DialogDescription>
              {{ getEmployeeFullName(selectedItem?.employee) }}
            </DialogDescription>
          </DialogHeader>
          <div v-if="selectedItem && selectedItem.breakdown" class="space-y-2">
            <div
              v-for="(penalty, index) in selectedItem.breakdown"
              :key="index"
              class="flex justify-between items-start gap-2 p-3 border rounded-lg"
            >
              <Button
                v-if="salaryRun.status === 'draft' && getBreakdownLineMeta(penalty)"
                type="button"
                variant="ghost"
                size="sm"
                class="h-8 w-8 shrink-0 p-0 text-muted-foreground hover:text-destructive"
                :title="t('salary_runs.remove_breakdown_line')"
                :disabled="removingBreakdownKey === breakdownLineKey(selectedItem.id, penalty)"
                @click="removeBreakdownLine(penalty)"
              >
                <Icon name="X" class="h-4 w-4" />
              </Button>
              <div class="min-w-0 flex-1">
                <div class="font-medium">{{ penalty.date }}</div>
                <div v-if="penalty.source === 'penalty'" class="text-xs mb-0.5">
                  <span
                    v-if="resolvePenaltyCategory(penalty) === 'absence'"
                    class="inline-flex rounded bg-red-100 px-2 py-0.5 font-medium text-red-700 dark:bg-red-900/30 dark:text-red-300"
                  >
                    {{ t('salary_runs.penalty_type_absence') }}
                  </span>
                  <span
                    v-else-if="resolvePenaltyCategory(penalty) === 'late'"
                    class="inline-flex rounded bg-amber-100 px-2 py-0.5 font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-300"
                  >
                    {{ t('salary_runs.penalty_type_late') }}
                  </span>
                  <span
                    v-else
                    class="inline-flex rounded bg-slate-100 px-2 py-0.5 font-medium text-slate-700 dark:bg-slate-900/30 dark:text-slate-300"
                  >
                    {{ t('salary_runs.penalty_type_other') }}
                  </span>
                </div>
                <div v-if="penalty.source === 'manual_deduction'" class="text-xs text-blue-600 dark:text-blue-400 mb-0.5">
                  {{ manualDeductionTypeLabel(penalty) }}
                </div>
                <div v-if="penalty.source === 'unpaid_leave'" class="text-xs text-purple-600 dark:text-purple-400 mb-0.5">
                  {{ t('salary_runs.unpaid_leave_label') }}
                </div>
                <div class="text-sm text-gray-500 break-words">{{ penalty.action_text }}</div>
                <div v-if="penalty.source === 'penalty' && penalty.late_minutes_deduction_amount != null && Number(penalty.late_minutes_deduction_amount) > 0" class="text-xs text-amber-600 dark:text-amber-400 mt-1">
                  {{ t('attendance.late_minutes_deduction') }}: {{ formatCurrency(Number(penalty.late_minutes_deduction_amount)) }}
                </div>
              </div>
              <div class="text-sm font-medium text-orange-600 dark:text-orange-400 shrink-0">
                {{ formatCurrency(penalty.amount) }}
              </div>
            </div>
            <div v-if="!selectedItem || !selectedItem.breakdown || selectedItem.breakdown.length === 0" class="text-center py-4 text-gray-500">
              {{ t('salary_runs.no_penalties') }}
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" @click="closeBreakdownModal">
              {{ t('common.close') }}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <!-- Debt Deductions Modal -->
      <Dialog :open="debtDeductionsModalOpen" @update:open="closeDebtDeductionsModal">
        <DialogContent class="max-w-2xl">
          <DialogHeader>
            <DialogTitle>{{ t('debts.update_debt_deductions') }}</DialogTitle>
            <DialogDescription>
              {{ getEmployeeFullName(selectedItemForDebts?.employee) }}
            </DialogDescription>
          </DialogHeader>
          <form @submit.prevent="submitDebtDeductions" class="space-y-4">
            <div v-if="selectedItemForDebts && selectedItemForDebts.employee?.debts" class="space-y-4">
              <div
                v-for="debt in selectedItemForDebts.employee.debts"
                :key="debt.id"
                class="p-4 border rounded-lg bg-gray-50"
              >
                <div class="space-y-3">
                  <div class="flex justify-between items-start">
                    <div class="flex-1">
                      <div class="font-semibold text-lg mb-1">{{ formatCurrency(debt.amount) }}</div>
                      <div v-if="debt.debt_type" class="text-sm text-gray-600 font-medium mb-2">{{ debt.debt_type }}</div>
                      <div class="text-xs text-gray-500">
                        <span class="font-medium">{{ t('debts.remaining_debt') }}:</span>
                        <span class="text-blue-600 font-semibold ml-1">{{ formatCurrency(getRemainingDebtAmount(debt.id)) }}</span>
                      </div>
                    </div>
                  </div>
                  <div class="space-y-2">
                    <Label :for="`debt_${debt.id}`" class="text-sm font-medium">
                      {{ t('debts.debt_deduction_amount') }}
                    </Label>
                    <Input
                      :id="`debt_${debt.id}`"
                      v-model.number="debtDeductions[debt.id]"
                      type="number"
                      step="0.01"
                      min="0"
                      :max="getRemainingDebtAmount(debt.id)"
                      :placeholder="`${t('debts.select_debt_amount')} (${t('debts.max')}: ${formatCurrency(getRemainingDebtAmount(debt.id))})`"
                      class="w-full"
                    />
                    <div v-if="debtDeductions[debt.id] > getRemainingDebtAmount(debt.id)" class="text-xs text-red-600">
                      {{ t('debts.deduction_exceeds_debt') }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div v-else class="text-center py-4 text-gray-500">
              {{ t('debts.no_debts') }}
            </div>
            <DialogFooter>
              <Button type="button" variant="outline" @click="closeDebtDeductionsModal">
                {{ t('common.cancel') }}
              </Button>
              <Button type="submit" :disabled="updatingDebtDeductions">
                <Icon v-if="updatingDebtDeductions" name="Loader" class="h-4 w-4 mr-2 animate-spin" />
                {{ t('common.save') }}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <!-- Approval confirmation dialog -->
      <Dialog :open="!!approvalConfirmStep" @update:open="(open) => !open && (approvalConfirmStep = null)">
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{{ t('salary_runs.approval_confirm_title') }}</DialogTitle>
            <DialogDescription>
              {{ t('salary_runs.approval_confirm_message') }}
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="outline" @click="approvalConfirmStep = null">
              {{ t('common.cancel') }}
            </Button>
            <Button @click="confirmApprovalSubmit">
              {{ t('salary_runs.approval_approve') }}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import Icon from '@/components/Icon.vue';
import Heading from '@/components/Heading.vue';
import { useI18n } from 'vue-i18n';
import { computed, ref } from 'vue';
import type { Company, BreadcrumbItem } from '@/types';

const { t, locale } = useI18n();

interface ApprovalState {
  approved_at: string | null;
  approver_name: string | null;
  can_approve: boolean;
}

interface Props {
  company: Company;
  salaryRun: {
    id: number;
    year: number;
    month: number;
    status: string;
    items: Array<{
      id: number;
      employee: {
        id: number;
        first_name: string;
        father_name?: string | null;
        last_name: string;
        employee_id: string;
        debts?: Array<{
          id: number;
          amount: number;
          debt_type: string | null;
        }>;
      };
      basic_salary: number;
      allowances: number;
      gross_salary: number;
      penalties_total: number;
      social_insurance_deduction_total: number;
      net_salary: number;
      debt_deductions?: Array<{
        debt_id: number;
        debt_type: string | null;
        amount: number;
        original_amount: number;
      }>;
      breakdown: Array<{
        date: string;
        violation_type?: string;
        penalty_category?: 'late' | 'absence' | 'other';
        action_type: string;
        action_value: number | null;
        action_text: string;
        amount: number;
        penalty_amount?: number;
        late_minutes_deduction_amount?: number;
        deduction_type?: 'penalties' | 'absence' | 'traffic_violation' | 'attestations';
        attendance_penalty_id?: number;
        employee_deduction_id?: number;
        leave_id?: number;
        source?: 'penalty' | 'manual_deduction' | 'unpaid_leave';
      }>;
    }>;
  };
  approvals?: {
    hr: ApprovalState;
    director: ApprovalState;
    accountant: ApprovalState;
    ceo: ApprovalState;
  };
}

const props = defineProps<Props>();

function getEmployeeFullName(employee?: { first_name?: string | null; father_name?: string | null; last_name?: string | null } | null): string {
  if (!employee) return '';
  return [employee.first_name, employee.father_name, employee.last_name]
    .map((part) => (part ?? '').trim())
    .filter((part) => part.length > 0)
    .join(' ');
}

const defaultApproval = { approved_at: null, approver_name: null, can_approve: false };
const approvalList = computed(() => {
  const a = props.approvals ?? { hr: defaultApproval, director: defaultApproval, accountant: defaultApproval, ceo: defaultApproval };
  return [
    { key: 'hr', label: t('salary_runs.approval_hr'), ...a.hr },
    { key: 'director', label: t('salary_runs.approval_director'), ...a.director },
    { key: 'accountant', label: t('salary_runs.approval_accountant'), ...a.accountant },
    { key: 'ceo', label: t('salary_runs.approval_ceo'), ...a.ceo },
  ].map((item) => ({ ...item, approved_at: item.approved_at ?? null, approver_name: item.approver_name ?? null, can_approve: item.can_approve ?? false }));
});

const approvingStep = ref<string | null>(null);
const approvalConfirmStep = ref<string | null>(null);

function openApprovalConfirm(stepKey: string) {
  approvalConfirmStep.value = stepKey;
}

function confirmApprovalSubmit() {
  if (approvalConfirmStep.value) {
    submitApproval(approvalConfirmStep.value);
    approvalConfirmStep.value = null;
  }
}

function formatApprovalDate(iso: string) {
  try {
    const d = new Date(iso);
    return d.toLocaleDateString(locale.value === 'ar' ? 'ar-SA' : 'en-GB', { day: '2-digit', month: '2-digit', year: 'numeric', weekday: undefined });
  } catch {
    return iso;
  }
}

function formatApprovalTime(iso: string) {
  try {
    const d = new Date(iso);
    return d.toLocaleTimeString(locale.value === 'ar' ? 'ar-SA' : 'en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
  } catch {
    return '';
  }
}

function submitApproval(stepKey: string) {
  const routeName = `salary-runs.approve-${stepKey}` as 'salary-runs.approve-hr' | 'salary-runs.approve-director' | 'salary-runs.approve-accountant' | 'salary-runs.approve-ceo';
  approvingStep.value = stepKey;
  router.post(route(routeName, [props.company.id, props.salaryRun.id]), {}, {
    preserveScroll: true,
    onFinish: () => {
      approvingStep.value = null;
    },
  });
}

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
    title: props.company.name_ar || props.company.name_en || t('companies.title'),
    href: `/companies/${props.company.id}`,
  },
  {
    title: t('salary_runs.title'),
    href: `/companies/${props.company.id}/salary-runs`,
  },
  {
    title: `${getMonthName(props.salaryRun.month)} ${props.salaryRun.year}`,
    href: `/companies/${props.company.id}/salary-runs/${props.salaryRun.year}/${props.salaryRun.month}`,
  },
]);

const totalGross = computed(() => {
  return props.salaryRun.items?.reduce((sum, item) => sum + parseFloat(item.gross_salary || 0), 0) || 0;
});

const totalPenalties = computed(() => {
  return props.salaryRun.items?.reduce((sum, item) => sum + parseFloat(item.penalties_total || 0), 0) || 0;
});

const totalDebtDeductions = computed(() => {
  return props.salaryRun.items?.reduce((sum, item) => sum + getDebtDeductionsTotal(item), 0) || 0;
});

const totalSocialInsuranceDeductions = computed(() => {
  return props.salaryRun.items?.reduce((sum, item) => sum + parseFloat(item.social_insurance_deduction_total || 0), 0) || 0;
});

const totalNet = computed(() => {
  return props.salaryRun.items?.reduce((sum, item) => sum + parseFloat(item.net_salary || 0), 0) || 0;
});

const breakdownModalOpen = ref(false);
const selectedItem = ref<any>(null);
const removingBreakdownKey = ref<string | null>(null);

function getBreakdownLineMeta(line: Record<string, unknown>): { line_type: string; line_id: number } | null {
  const ap = line.attendance_penalty_id;
  if (ap != null && Number(ap) > 0) {
    return { line_type: 'attendance_penalty', line_id: Number(ap) };
  }
  const ed = line.employee_deduction_id;
  if (ed != null && Number(ed) > 0) {
    return { line_type: 'employee_deduction', line_id: Number(ed) };
  }
  const lv = line.leave_id;
  if (lv != null && Number(lv) > 0) {
    return { line_type: 'unpaid_leave', line_id: Number(lv) };
  }
  return null;
}

function breakdownLineKey(itemId: number, line: Record<string, unknown>): string {
  const m = getBreakdownLineMeta(line);
  return m ? `${itemId}-${m.line_type}-${m.line_id}` : `${itemId}-legacy`;
}

function resolvePenaltyCategory(line: Record<string, unknown>): 'late' | 'absence' | 'other' {
  const category = String(line.penalty_category || '');
  if (category === 'late' || category === 'absence' || category === 'other') {
    return category;
  }

  const violationType = String(line.violation_type || '');
  if (violationType === 'absent_without_excuse') {
    return 'absence';
  }
  if (violationType.startsWith('late_')) {
    return 'late';
  }

  return 'other';
}

function manualDeductionTypeLabel(line: Record<string, unknown>): string {
  const type = String(line.deduction_type || '')
  const map: Record<string, string> = {
    penalties: t('attendance.deduction_type_penalties'),
    absence: t('attendance.deduction_type_absence'),
    traffic_violation: t('attendance.deduction_type_traffic_violation'),
    attestations: t('attendance.deduction_type_attestations'),
  }

  return map[type] ?? t('attendance.deduction_type_manual')
}

function removeBreakdownLine(line: Record<string, unknown>) {
  if (props.salaryRun.status !== 'draft') {
    return;
  }
  const meta = getBreakdownLineMeta(line);
  if (!meta || !selectedItem.value) {
    return;
  }
  if (!confirm(t('salary_runs.remove_breakdown_line_confirm'))) {
    return;
  }
  removingBreakdownKey.value = breakdownLineKey(selectedItem.value.id, line);
  router.post(
    route('salary-runs.remove-breakdown-line', [props.company.id, props.salaryRun.id]),
    {
      salary_run_item_id: selectedItem.value.id,
      line_type: meta.line_type,
      line_id: meta.line_id,
    },
    {
      preserveScroll: true,
      onFinish: () => {
        removingBreakdownKey.value = null;
      },
      onSuccess: (page) => {
        const id = selectedItem.value?.id;
        const items = (page.props as { salaryRun?: { items?: Array<{ id: number; breakdown?: unknown[] }> } }).salaryRun?.items;
        const fresh = items?.find((i) => i.id === id);
        if (fresh) {
          selectedItem.value = { ...fresh };
        }
        if (!fresh?.breakdown?.length) {
          closeBreakdownModal();
        }
      },
    },
  );
}
const debtDeductionsModalOpen = ref(false);
const selectedItemForDebts = ref<any>(null);
const debtDeductions = ref<Record<number, number>>({});
const updatingDebtDeductions = ref(false);
const finalizing = ref(false);

const openBreakdownModal = (item: any) => {
  selectedItem.value = item;
  breakdownModalOpen.value = true;
};

const closeBreakdownModal = () => {
  breakdownModalOpen.value = false;
  selectedItem.value = null;
};

const finalizeSalaryRun = () => {
  if (!confirm(t('salary_runs.finalize_confirmation'))) {
    return;
  }

  finalizing.value = true;
  router.post(route('salary-runs.finalize', [props.company.id, props.salaryRun.id]), {}, {
    onFinish: () => {
      finalizing.value = false;
    },
  });
};

const getMonthName = (month: number) => {
  const months = [
    'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
    'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'
  ];
  const monthsEn = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
  ];
  return locale.value === 'ar' ? months[month - 1] : monthsEn[month - 1] || month.toString();
};

const formatCurrency = (amount: number | string) => {
  const num = typeof amount === 'string' ? parseFloat(amount) : amount;
  return num.toFixed(2) + ' SAR';
};

const getTotalDebtsAmount = (item: any) => {
  if (!item.employee?.debts || !Array.isArray(item.employee.debts)) {
    return 0;
  }
  return item.employee.debts.reduce((sum: number, debt: any) => sum + parseFloat(debt.amount || 0), 0);
};

const getDebtDeductionsTotal = (item: any) => {
  if (!item.debt_deductions || !Array.isArray(item.debt_deductions)) {
    return 0;
  }
  return item.debt_deductions.reduce((sum: number, deduction: any) => sum + parseFloat(deduction.amount || 0), 0);
};

const openDebtDeductionsModal = (item: any) => {
  selectedItemForDebts.value = item;
  debtDeductions.value = {};
  
  // Initialize with existing deductions
  if (item.debt_deductions && Array.isArray(item.debt_deductions)) {
    item.debt_deductions.forEach((deduction: any) => {
      debtDeductions.value[deduction.debt_id] = deduction.amount;
    });
  }
  
  debtDeductionsModalOpen.value = true;
};

const closeDebtDeductionsModal = () => {
  debtDeductionsModalOpen.value = false;
  selectedItemForDebts.value = null;
  debtDeductions.value = {};
};

const getRemainingDebtAmount = (debtId: number) => {
  if (!selectedItemForDebts.value?.employee?.debts) {
    return 0;
  }
  const debt = selectedItemForDebts.value.employee.debts.find((d: any) => d.id === debtId);
  if (!debt) {
    return 0;
  }
  // Get the original debt amount
  const originalAmount = parseFloat(debt.amount);
  
  // Check if there's an existing deduction for this debt
  const existingDeduction = selectedItemForDebts.value.debt_deductions?.find(
    (d: any) => d.debt_id === debtId
  );
  
  if (existingDeduction) {
    // Return the remaining amount after existing deduction
    return Math.max(0, originalAmount - parseFloat(existingDeduction.amount || 0));
  }
  
  return originalAmount;
};

const submitDebtDeductions = () => {
  if (!selectedItemForDebts.value) {
    return;
  }

  updatingDebtDeductions.value = true;
  
  const deductions = Object.entries(debtDeductions.value)
    .filter(([_, amount]) => amount > 0)
    .map(([debtId, amount]) => ({
      debt_id: parseInt(debtId),
      amount: amount,
    }));

  router.post(
    route('salary-runs.update-debt-deductions', [props.company.id, props.salaryRun.id]),
    {
      employee_id: selectedItemForDebts.value.employee.id,
      debt_deductions: deductions,
    },
    {
      preserveScroll: true,
      onFinish: () => {
        updatingDebtDeductions.value = false;
        closeDebtDeductionsModal();
      },
    }
  );
};
</script>

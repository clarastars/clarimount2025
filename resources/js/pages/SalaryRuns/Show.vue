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
        <div class="flex gap-2">
          <Badge :variant="salaryRun.status === 'finalized' ? 'default' : 'secondary'">
            {{ salaryRun.status === 'finalized' ? t('salary_runs.status_finalized') : t('salary_runs.status_draft') }}
          </Badge>
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
                      {{ item.employee?.first_name }} {{ item.employee?.last_name }}
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
              {{ selectedItem?.employee?.first_name }} {{ selectedItem?.employee?.last_name }}
            </DialogDescription>
          </DialogHeader>
          <div v-if="selectedItem && selectedItem.breakdown" class="space-y-2">
            <div
              v-for="(penalty, index) in selectedItem.breakdown"
              :key="index"
              class="flex justify-between items-center p-3 border rounded-lg"
            >
              <div>
                <div class="font-medium">{{ penalty.date }}</div>
                <div class="text-sm text-gray-500">{{ penalty.action_text }}</div>
              </div>
              <div class="text-sm font-medium text-orange-600">
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
              {{ selectedItemForDebts?.employee?.first_name }} {{ selectedItemForDebts?.employee?.last_name }}
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
      net_salary: number;
      debt_deductions?: Array<{
        debt_id: number;
        debt_type: string | null;
        amount: number;
        original_amount: number;
      }>;
      breakdown: Array<{
        date: string;
        action_type: string;
        action_value: number;
        action_text: string;
        amount: number;
      }>;
    }>;
  };
}

const props = defineProps<Props>();

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

const totalNet = computed(() => {
  return props.salaryRun.items?.reduce((sum, item) => sum + parseFloat(item.net_salary || 0), 0) || 0;
});

const breakdownModalOpen = ref(false);
const selectedItem = ref<any>(null);
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

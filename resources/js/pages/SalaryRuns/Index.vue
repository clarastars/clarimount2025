<template>
  <Head :title="t('salary_runs.title')" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <Heading :title="t('salary_runs.title')" />
          <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
            {{ t('salary_runs.create_salary_run') }}
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button v-if="canCreateSalaryRuns" @click="openCreateModal" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold">
            <Icon name="Plus" class="mr-2 rtl:mr-0 rtl:ml-2 h-4 w-4" />
            {{ t('salary_runs.create_salary_run') }}
          </Button>
          <Button v-if="canCreateSalaryRuns" variant="outline" @click="openSupplementaryModal">
            <Icon name="UserPlus" class="mr-2 rtl:mr-0 rtl:ml-2 h-4 w-4" />
            {{ t('salary_runs.create_supplementary_salary_run') }}
          </Button>
        </div>
      </div>

      <!-- Salary Runs Table -->
      <Card v-if="salaryRuns.data && salaryRuns.data.length > 0">
        <CardContent class="pt-6">
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead class="bg-gray-50 dark:bg-gray-800">
                <tr class="text-left rtl:text-right">
                  <th class="px-6 py-4 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    {{ t('salary_runs.year') }}
                  </th>
                  <th class="px-6 py-4 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    {{ t('salary_runs.month') }}
                  </th>
                  <th class="px-6 py-4 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    {{ t('salary_runs.status') }}
                  </th>
                  <th class="px-6 py-4 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    {{ t('salary_runs.run_type') }}
                  </th>
                  <th class="px-6 py-4 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    {{ t('salary_runs.employees_count') }}
                  </th>
                  <th class="px-6 py-4 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    {{ t('common.created_at') }}
                  </th>
                  <th class="px-6 py-4 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider text-right rtl:text-left">
                    {{ t('common.actions') }}
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-for="salaryRun in salaryRuns.data" :key="salaryRun.id" class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                      {{ salaryRun.year }}
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900 dark:text-gray-100">
                      {{ getMonthName(salaryRun.month) }}
                    </div>
                    <div v-if="salaryRun.label" class="text-xs text-muted-foreground mt-1">
                      {{ salaryRun.label }}
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <Badge :variant="salaryRun.status === 'finalized' ? 'default' : 'secondary'">
                      {{ salaryRun.status === 'finalized' ? t('salary_runs.status_finalized') : t('salary_runs.status_draft') }}
                    </Badge>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <Badge variant="outline">
                      {{ salaryRun.run_type === 'supplementary' ? t('salary_runs.run_type_supplementary') : t('salary_runs.run_type_regular') }}
                    </Badge>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900 dark:text-gray-100">
                      {{ salaryRun.items_count || 0 }}
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                      {{ formatDate(salaryRun.created_at) }}
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right rtl:text-left">
                    <div class="flex items-center justify-end gap-1">
                      <Button variant="ghost" size="sm" asChild>
                        <Link :href="route('salary-runs.show', [company.id, salaryRun.id])">
                          <Icon name="Eye" class="h-4 w-4 mr-2 rtl:mr-0 rtl:ml-2" />
                          {{ t('salary_runs.view_details') }}
                        </Link>
                      </Button>
                      <Button
                        v-if="canDeleteSalaryRuns && salaryRun.status !== 'finalized'"
                        variant="ghost"
                        size="sm"
                        class="text-red-600 hover:text-red-700"
                        @click="deleteSalaryRun(salaryRun)"
                      >
                        <Icon name="Trash2" class="h-4 w-4 mr-2 rtl:mr-0 rtl:ml-2" />
                        {{ t('common.delete') }}
                      </Button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div v-if="salaryRuns.links" class="mt-6 flex items-center justify-between">
            <div class="flex-1 flex justify-between sm:hidden">
              <Button v-if="salaryRuns.prev_page_url" @click="router.visit(salaryRuns.prev_page_url)" variant="outline">
                {{ t('common.previous') }}
              </Button>
              <Button v-if="salaryRuns.next_page_url" @click="router.visit(salaryRuns.next_page_url)" variant="outline">
                {{ t('common.next') }}
              </Button>
            </div>
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
              <div>
                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                  <template v-for="link in salaryRuns.links" :key="link.label">
                    <Button
                      v-if="link.url"
                      @click="router.visit(link.url)"
                      :variant="link.active ? 'default' : 'outline'"
                      size="sm"
                      class="mr-1"
                    >
                      <span v-html="link.label"></span>
                    </Button>
                    <Button v-else variant="outline" size="sm" disabled class="mr-1">
                      <span v-html="link.label"></span>
                    </Button>
                  </template>
                </nav>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Empty State -->
      <div v-else class="text-center py-12">
        <Icon name="FileText" class="mx-auto h-12 w-12 text-gray-400 mb-4" />
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">
          {{ t('salary_runs.no_salary_runs') }}
        </h3>
        <p class="text-gray-600 dark:text-gray-400 mb-6">
          {{ t('salary_runs.create_first_salary_run') }}
        </p>
        <Button v-if="canCreateSalaryRuns" @click="openCreateModal" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold">
          <Icon name="Plus" class="mr-2 rtl:mr-0 rtl:ml-2 h-4 w-4" />
          {{ t('salary_runs.create_salary_run') }}
        </Button>
      </div>

      <!-- Create Salary Run Modal -->
      <Dialog v-if="canCreateSalaryRuns" :open="createModalOpen" @update:open="closeCreateModal">
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{{ t('salary_runs.create_salary_run') }}</DialogTitle>
            <DialogDescription>
              {{ t('salary_runs.create_salary_run') }}
            </DialogDescription>
          </DialogHeader>
          <div class="space-y-4">
            <div>
              <Label for="year">{{ t('salary_runs.year') }}</Label>
              <Input
                id="year"
                v-model="form.year"
                type="number"
                min="2020"
                max="2100"
                :placeholder="new Date().getFullYear().toString()"
              />
            </div>
            <div>
              <Label for="month">{{ t('salary_runs.month') }}</Label>
              <select
                id="month"
                v-model="form.month"
                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
              >
                <option
                  v-for="m in 12"
                  :key="m"
                  :value="m"
                  :disabled="isMonthOccupied(Number(form.year), m)"
                >
                  {{ getMonthName(m) }}{{ isMonthOccupied(Number(form.year), m) ? ` — ${t('salary_runs.month_already_has_run')}` : '' }}
                </option>
              </select>
              <p v-if="form.errors.month" class="mt-1 text-sm text-destructive">
                {{ form.errors.month }}
              </p>
            </div>
            <p v-if="form.errors.year" class="text-sm text-destructive">
              {{ form.errors.year }}
            </p>
          </div>
          <DialogFooter>
            <Button variant="outline" @click="closeCreateModal">
              {{ t('common.cancel') }}
            </Button>
            <Button @click="createSalaryRun" :disabled="creating">
              {{ creating ? t('common.creating') : t('common.create') }}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog v-if="canCreateSalaryRuns" :open="supplementaryModalOpen" @update:open="closeSupplementaryModal">
        <DialogContent class="max-w-3xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>{{ t('salary_runs.create_supplementary_salary_run') }}</DialogTitle>
          </DialogHeader>
          <div class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <Label for="supp-year">{{ t('salary_runs.year') }}</Label>
                <Input id="supp-year" v-model="supplementaryForm.year" type="number" min="2020" max="2100" />
              </div>
              <div>
                <Label for="supp-month">{{ t('salary_runs.month') }}</Label>
                <select
                  id="supp-month"
                  v-model="supplementaryForm.month"
                  class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                  @change="syncSupplementaryDefaultDates"
                >
                  <option v-for="m in 12" :key="m" :value="m">{{ getMonthName(m) }}</option>
                </select>
              </div>
            </div>
            <div>
              <Label for="supp-label">{{ t('salary_runs.supplementary_label') }}</Label>
              <Input
                id="supp-label"
                v-model="supplementaryForm.label"
                :placeholder="t('salary_runs.supplementary_label_placeholder')"
              />
            </div>

            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <Label>{{ t('salary_runs.supplementary_employees') }}</Label>
                <Button type="button" variant="outline" size="sm" @click="addSupplementaryEntry">
                  <Icon name="Plus" class="h-4 w-4 mr-1" />
                  {{ t('salary_runs.supplementary_add_employee') }}
                </Button>
              </div>

              <div
                v-for="(entry, index) in supplementaryForm.entries"
                :key="entry.key"
                class="rounded-lg border p-4 space-y-3"
              >
                <div class="flex items-start justify-between gap-2">
                  <p class="text-sm font-medium">{{ t('employees.employee') }} #{{ index + 1 }}</p>
                  <Button
                    v-if="supplementaryForm.entries.length > 1"
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="text-red-600"
                    @click="removeSupplementaryEntry(index)"
                  >
                    <Icon name="Trash2" class="h-4 w-4" />
                  </Button>
                </div>
                <div>
                  <Label>{{ t('employees.employee') }}</Label>
                  <Input
                    v-model="entry.search"
                    :placeholder="t('salary_runs.supplementary_search_employee')"
                    @input="searchEmployeesForEntry(index)"
                  />
                  <div v-if="entry.results.length" class="mt-2 rounded-md border max-h-40 overflow-y-auto">
                    <button
                      v-for="employee in entry.results"
                      :key="employee.id"
                      type="button"
                      class="w-full text-start px-3 py-2 text-sm hover:bg-muted"
                      @click="selectEmployeeForEntry(index, employee)"
                    >
                      {{ employee.display_name }}
                    </button>
                  </div>
                  <p v-if="entry.employee_label" class="mt-2 text-sm text-green-700 dark:text-green-400">
                    {{ entry.employee_label }}
                  </p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <Label>{{ t('salary_runs.supplementary_period_from') }}</Label>
                    <Input v-model="entry.period_start" type="date" />
                  </div>
                  <div>
                    <Label>{{ t('salary_runs.supplementary_period_to') }}</Label>
                    <Input v-model="entry.period_end" type="date" />
                  </div>
                </div>
              </div>
            </div>

            <p v-if="supplementaryForm.errors.entries" class="text-sm text-destructive">
              {{ supplementaryForm.errors.entries }}
            </p>
          </div>
          <DialogFooter>
            <Button variant="outline" @click="closeSupplementaryModal">{{ t('common.cancel') }}</Button>
            <Button @click="createSupplementarySalaryRun" :disabled="creatingSupplementary">
              {{ creatingSupplementary ? t('common.creating') : t('common.create') }}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Icon from '@/components/Icon.vue';
import Heading from '@/components/Heading.vue';
import { useI18n } from 'vue-i18n';
import { computed, ref, watch } from 'vue';
import type { Company, BreadcrumbItem } from '@/types';

const { t, locale } = useI18n();

interface Props {
  company: Company;
  canCreateSalaryRuns?: boolean;
  canDeleteSalaryRuns?: boolean;
  occupiedPeriods?: Array<{
    year: number;
    month: number;
    status: string;
  }>;
  salaryRuns: {
    data: any[];
    links: any[];
    meta: any;
  };
}

const props = defineProps<Props>();
const canCreateSalaryRuns = computed(() => props.canCreateSalaryRuns === true);
const canDeleteSalaryRuns = computed(() => props.canDeleteSalaryRuns === true);
const occupiedPeriods = computed(() => props.occupiedPeriods ?? []);

const isMonthOccupied = (year: number, month: number): boolean =>
  occupiedPeriods.value.some((period) => period.year === year && period.month === month);

const firstAvailableMonth = (year: number): number => {
  for (let month = 1; month <= 12; month++) {
    if (!isMonthOccupied(year, month)) {
      return month;
    }
  }

  return 1;
};

const defaultCreatePeriod = (): { year: number; month: number } => {
  if (occupiedPeriods.value.length === 0) {
    const now = new Date();

    return {
      year: now.getFullYear(),
      month: now.getMonth() + 1,
    };
  }

  let latest = occupiedPeriods.value[0];
  for (const period of occupiedPeriods.value) {
    if (
      period.year > latest.year
      || (period.year === latest.year && period.month > latest.month)
    ) {
      latest = period;
    }
  }

  let year = latest.year;
  let month = latest.month + 1;

  if (month > 12) {
    year += 1;
    month = 1;
  }

  // Skip any occupied gaps until the next free month.
  while (isMonthOccupied(year, month)) {
    month += 1;
    if (month > 12) {
      year += 1;
      month = 1;
    }
  }

  return { year, month };
};

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
]);

const createModalOpen = ref(false);
const creating = ref(false);
const supplementaryModalOpen = ref(false);
const creatingSupplementary = ref(false);
let supplementaryEntryCounter = 0;

interface SupplementaryEntryRow {
  key: number;
  employee_id: number | null;
  employee_label: string;
  search: string;
  results: Array<{ id: number; display_name: string }>;
  period_start: string;
  period_end: string;
}

const buildDefaultPeriodDates = (year: number, month: number): { start: string; end: string } => {
  const start = `${year}-${String(month).padStart(2, '0')}-01`;
  const lastDay = new Date(year, month, 0).getDate();
  const end = `${year}-${String(month).padStart(2, '0')}-${String(lastDay).padStart(2, '0')}`;

  return { start, end };
};

const createEmptySupplementaryEntry = (year: number, month: number): SupplementaryEntryRow => {
  const period = buildDefaultPeriodDates(year, month);

  return {
    key: ++supplementaryEntryCounter,
    employee_id: null,
    employee_label: '',
    search: '',
    results: [],
    period_start: period.start,
    period_end: period.end,
  };
};

const supplementaryForm = useForm<{
  year: number;
  month: number;
  label: string;
  entries: SupplementaryEntryRow[];
}>({
  year: new Date().getFullYear(),
  month: new Date().getMonth() + 1,
  label: '',
  entries: [],
});

const form = useForm({
  year: new Date().getFullYear(),
  month: new Date().getMonth() + 1,
});

const onYearChanged = () => {
  const year = Number(form.year);
  if (!Number.isFinite(year)) {
    return;
  }

  if (isMonthOccupied(year, Number(form.month))) {
    form.month = firstAvailableMonth(year);
  }
};

watch(() => form.year, () => {
  onYearChanged();
});

const openCreateModal = () => {
  form.clearErrors();
  const period = defaultCreatePeriod();
  form.year = period.year;
  form.month = period.month;
  createModalOpen.value = true;
};

const closeCreateModal = () => {
  createModalOpen.value = false;
  form.reset();
  form.clearErrors();
};

const createSalaryRun = () => {
  creating.value = true;
  form.post(route('salary-runs.store', props.company.id), {
    onSuccess: () => {
      closeCreateModal();
    },
    onFinish: () => {
      creating.value = false;
    },
  });
};

const openSupplementaryModal = () => {
  supplementaryForm.clearErrors();
  const now = new Date();
  supplementaryForm.year = now.getFullYear();
  supplementaryForm.month = now.getMonth() + 1;
  supplementaryForm.label = '';
  supplementaryForm.entries = [createEmptySupplementaryEntry(supplementaryForm.year, supplementaryForm.month)];
  supplementaryModalOpen.value = true;
};

const closeSupplementaryModal = () => {
  supplementaryModalOpen.value = false;
  supplementaryForm.reset();
  supplementaryForm.clearErrors();
};

const syncSupplementaryDefaultDates = () => {
  const year = Number(supplementaryForm.year);
  const month = Number(supplementaryForm.month);
  if (!Number.isFinite(year) || !Number.isFinite(month)) {
    return;
  }

  supplementaryForm.entries = supplementaryForm.entries.map((entry) => {
    const period = buildDefaultPeriodDates(year, month);

    return {
      ...entry,
      period_start: period.start,
      period_end: period.end,
    };
  });
};

const addSupplementaryEntry = () => {
  supplementaryForm.entries = [
    ...supplementaryForm.entries,
    createEmptySupplementaryEntry(Number(supplementaryForm.year), Number(supplementaryForm.month)),
  ];
};

const removeSupplementaryEntry = (index: number) => {
  supplementaryForm.entries = supplementaryForm.entries.filter((_, rowIndex) => rowIndex !== index);
};

let searchTimeouts: Record<number, ReturnType<typeof setTimeout>> = {};

const searchEmployeesForEntry = (index: number) => {
  const entry = supplementaryForm.entries[index];
  if (!entry) {
    return;
  }

  if (searchTimeouts[index]) {
    clearTimeout(searchTimeouts[index]);
  }

  const query = entry.search.trim();
  if (query.length < 2) {
    entry.results = [];
    return;
  }

  searchTimeouts[index] = setTimeout(async () => {
    try {
      const response = await fetch(
        `${route('api.employees.search')}?q=${encodeURIComponent(query)}&company_id=${props.company.id}`,
        { headers: { Accept: 'application/json' } },
      );
      const data = await response.json();
      entry.results = Array.isArray(data) ? data : [];
    } catch {
      entry.results = [];
    }
  }, 300);
};

const selectEmployeeForEntry = (
  index: number,
  employee: { id: number; display_name: string },
) => {
  const entry = supplementaryForm.entries[index];
  if (!entry) {
    return;
  }

  entry.employee_id = employee.id;
  entry.employee_label = employee.display_name;
  entry.search = employee.display_name;
  entry.results = [];
};

const createSupplementarySalaryRun = () => {
  creatingSupplementary.value = true;

  supplementaryForm.transform((data) => ({
    year: Number(data.year),
    month: Number(data.month),
    label: data.label || null,
    entries: data.entries
      .filter((entry) => entry.employee_id)
      .map((entry) => ({
        employee_id: entry.employee_id,
        period_start: entry.period_start,
        period_end: entry.period_end,
      })),
  })).post(route('salary-runs.store-supplementary', props.company.id), {
    onSuccess: () => {
      closeSupplementaryModal();
    },
    onFinish: () => {
      creatingSupplementary.value = false;
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

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString();
};

const deleteSalaryRun = (salaryRun: { id: number; year: number; month: number; status?: string }) => {
  if (salaryRun.status === 'finalized') {
    return;
  }

  if (!confirm(t('salary_runs.delete_confirmation', { month: getMonthName(salaryRun.month), year: salaryRun.year }))) {
    return;
  }

  router.delete(route('salary-runs.destroy', [props.company.id, salaryRun.id]), {
    preserveScroll: true,
  });
};
</script>

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
        <Button v-if="canManageSalaryRuns" @click="openCreateModal" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold">
          <Icon name="Plus" class="mr-2 rtl:mr-0 rtl:ml-2 h-4 w-4" />
          {{ t('salary_runs.create_salary_run') }}
        </Button>
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
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <Badge :variant="salaryRun.status === 'finalized' ? 'default' : 'secondary'">
                      {{ salaryRun.status === 'finalized' ? t('salary_runs.status_finalized') : t('salary_runs.status_draft') }}
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
                        <Link :href="route('salary-runs.show', [company.id, salaryRun.year, salaryRun.month])">
                          <Icon name="Eye" class="h-4 w-4 mr-2 rtl:mr-0 rtl:ml-2" />
                          {{ t('salary_runs.view_details') }}
                        </Link>
                      </Button>
                      <Button
                        v-if="canManageSalaryRuns"
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
        <Button v-if="canManageSalaryRuns" @click="openCreateModal" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold">
          <Icon name="Plus" class="mr-2 rtl:mr-0 rtl:ml-2 h-4 w-4" />
          {{ t('salary_runs.create_salary_run') }}
        </Button>
      </div>

      <!-- Create Salary Run Modal -->
      <Dialog v-if="canManageSalaryRuns" :open="createModalOpen" @update:open="closeCreateModal">
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
                <option v-for="m in 12" :key="m" :value="m">{{ getMonthName(m) }}</option>
              </select>
            </div>
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
import { computed, ref } from 'vue';
import type { Company, BreadcrumbItem } from '@/types';

const { t, locale } = useI18n();

interface Props {
  company: Company;
  canManageSalaryRuns?: boolean;
  salaryRuns: {
    data: any[];
    links: any[];
    meta: any;
  };
}

const props = defineProps<Props>();
const canManageSalaryRuns = computed(() => props.canManageSalaryRuns === true);

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

const form = useForm({
  year: new Date().getFullYear(),
  month: new Date().getMonth() + 1,
});

const openCreateModal = () => {
  form.year = new Date().getFullYear();
  form.month = new Date().getMonth() + 1;
  createModalOpen.value = true;
};

const closeCreateModal = () => {
  createModalOpen.value = false;
  form.reset();
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

const deleteSalaryRun = (salaryRun: { id: number; year: number; month: number }) => {
  if (!confirm(t('salary_runs.delete_confirmation', { month: getMonthName(salaryRun.month), year: salaryRun.year }))) {
    return;
  }

  router.delete(route('salary-runs.destroy', [props.company.id, salaryRun.id]), {
    preserveScroll: true,
  });
};
</script>

<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import Icon from '@/components/Icon.vue';
import Heading from '@/components/Heading.vue';
import { useI18n } from 'vue-i18n';
import { computed, ref, watch } from 'vue';
import type { Employee, Company, BreadcrumbItem } from '@/types';

const { t } = useI18n();

interface Props {
    employees: {
        data: Employee[];
        links: any[];
        meta: any;
    };
    companies?: Company[];
    stats?: {
        total: number;
        active: number;
        inactive: number;
        terminated: number;
        totalTickets: number;
        needingAttention: number;
        activePercentage: number;
    };
    filters?: {
        search?: string;
        status?: string;
        department?: string;
        company_id?: string;
    };
}

const props = defineProps<Props>();

const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || '');
const departmentFilter = ref(props.filters?.department || '');
const companyFilter = ref(props.filters?.company_id || '');
const selectedEmployees = ref<number[]>([]);
const selectAll = ref(false);

const breadcrumbs = computed((): BreadcrumbItem[] => [
    {
        title: t('nav.dashboard'),
        href: '/dashboard',
    },
    {
        title: t('employees.title'),
        href: '/employees',
    },
]);

// Summary statistics - use stats from backend if available, otherwise calculate from current page
const stats = computed(() => {
    if (props.stats) {
        return props.stats;
    }
    
    // Fallback: calculate from current page data (for backward compatibility)
    const total = props.employees.data.length;
    const active = props.employees.data.filter(emp => emp.employment_status === 'active').length;
    const inactive = props.employees.data.filter(emp => emp.employment_status === 'inactive').length;
    const terminated = props.employees.data.filter(emp => emp.employment_status === 'terminated').length;
    const totalTickets = props.employees.data.reduce((sum, emp) => sum + (emp.reported_tickets_count || 0), 0);
    
    // Calculate employees needing attention (documents expiring within 30 days)
    const needingAttention = props.employees.data.filter(emp => {
        const today = new Date();
        const thirtyDaysFromNow = new Date(today.getTime() + (30 * 24 * 60 * 60 * 1000));
        
        // Check various document expiry dates
        const expiryDates = [
            emp.residence_expiry_date,
            emp.contract_end_date,
            emp.exit_reentry_visa_expiry,
            emp.passport_expiry_date,
            emp.insurance_expiry_date
        ].filter(Boolean); // Remove null/undefined dates
        
        return expiryDates.some(date => {
            if (!date) return false;
            const expiryDate = new Date(date);
            return expiryDate <= thirtyDaysFromNow && expiryDate >= today;
        });
    });
    
    return {
        total,
        active,
        inactive,
        terminated,
        totalTickets,
        needingAttention: needingAttention.length,
        needingAttentionEmployees: needingAttention,
        activePercentage: total > 0 ? Math.round((active / total) * 100) : 0
    };
});

const getStatusVariant = (status: string) => {
    switch (status) {
        case 'active':
            return 'default';
        case 'inactive':
            return 'secondary';
        case 'terminated':
            return 'destructive';
        default:
            return 'secondary';
    }
};

const getStatusIcon = (status: string) => {
    switch (status) {
        case 'active':
            return 'CheckCircle';
        case 'inactive':
            return 'Clock';
        case 'terminated':
            return 'XCircle';
        default:
            return 'Circle';
    }
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString();
};

const companies = computed(() => props.companies || []);

const uniqueDepartments = computed(() => {
    const departments = props.employees.data
        .map(emp => emp.department)
        .filter(Boolean)
        .filter((dept, index, arr) => arr.indexOf(dept) === index);
    return departments.sort();
});

// Bulk selection logic
const toggleSelectAll = () => {
    if (selectAll.value) {
        selectedEmployees.value = props.employees.data.map(emp => emp.id);
    } else {
        selectedEmployees.value = [];
    }
};

const toggleEmployeeSelection = (employeeId: number) => {
    const index = selectedEmployees.value.indexOf(employeeId);
    if (index > -1) {
        selectedEmployees.value.splice(index, 1);
    } else {
        selectedEmployees.value.push(employeeId);
    }
    
    // Update select all checkbox
    selectAll.value = selectedEmployees.value.length === props.employees.data.length;
};

// Bulk actions
const handleBulkAction = (action: string) => {
    if (selectedEmployees.value.length === 0) return;
    
    switch (action) {
        case 'activate':
            bulkUpdateStatus('active');
            break;
        case 'deactivate':
            bulkUpdateStatus('inactive');
            break;
        case 'terminate':
            bulkUpdateStatus('terminated');
            break;
    }
};

const bulkUpdateStatus = (status: string) => {
    // Here you would typically make an API call to update the status
    console.log(`Updating ${selectedEmployees.value.length} employees to status: ${status}`);
};

const deleteEmployee = (employee: Employee) => {
    if (!confirm(t('employees.confirm_delete'))) return;
    const params = new URLSearchParams();
    if (search.value) params.set('search', search.value);
    if (statusFilter.value) params.set('status', statusFilter.value);
    if (departmentFilter.value) params.set('department', departmentFilter.value);
    if (companyFilter.value) params.set('company_id', companyFilter.value);
    const query = params.toString();
    const url = query
        ? `${route('employees.destroy', employee.id)}?${query}`
        : route('employees.destroy', employee.id);
    router.delete(url, { preserveScroll: false });
};

// Debounced search
let searchTimeout: number;
watch([search, statusFilter, departmentFilter, companyFilter], () => {
    clearTimeout(searchTimeout);
    searchTimeout = window.setTimeout(() => {
        const params: Record<string, string | number | undefined> = {};
        
        if (search.value) params.search = search.value;
        if (statusFilter.value) params.status = statusFilter.value;
        if (departmentFilter.value) params.department = departmentFilter.value;
        if (companyFilter.value) params.company_id = companyFilter.value;
        
        router.get('/employees', params, {
            preserveState: false,
            replace: true,
        });
    }, 300);
});

const clearFilters = () => {
    search.value = '';
    statusFilter.value = '';
    departmentFilter.value = '';
    companyFilter.value = '';
};
</script>

<template>
    <Head :title="t('employees.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <Heading :title="t('employees.my_employees')" />
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        {{ t('employees.manage_workforce') }}
                    </p>
                </div>
                <div class="flex flex-col sm:flex-row gap-2">
                    <Button variant="outline" asChild class="bg-blue-600 hover:bg-blue-700 text-white font-semibold border-blue-600">
                        <Link :href="route('employees.import')">
                            <Icon name="Upload" class="mr-2 rtl:mr-0 rtl:ml-2 h-4 w-4" />
                            {{ t('employees.import_csv') }}
                        </Link>
                    </Button>
                    <Button asChild class="bg-blue-600 hover:bg-blue-700 text-white font-semibold">
                        <Link :href="route('employees.create')">
                            <Icon name="Plus" class="mr-2 rtl:mr-0 rtl:ml-2 h-4 w-4" />
                            {{ t('employees.create_employee') }}
                        </Link>
                    </Button>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">{{ t('employees.total_employees') }}</CardTitle>
                        <Icon name="Users" class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.total }}</div>
                        <p class="text-xs text-muted-foreground">
                            {{ stats.activePercentage }}% {{ t('employees.active') }}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">{{ t('employees.active_employees') }}</CardTitle>
                        <Icon name="CheckCircle" class="h-4 w-4 text-green-600" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-green-600">{{ stats.active }}</div>
                        <p class="text-xs text-muted-foreground">
                            {{ t('employees.currently_employed') }}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">{{ t('employees.needing_attention') }}</CardTitle>
                        <Icon name="AlertTriangle" class="h-4 w-4 text-orange-600" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-orange-600">{{ stats.needingAttention }}</div>
                        <p class="text-xs text-muted-foreground">
                            {{ t('employees.documents_expiring') }}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">{{ t('employees.open_tickets') }}</CardTitle>
                        <Icon name="Ticket" class="h-4 w-4 text-orange-600" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-orange-600">{{ stats.totalTickets }}</div>
                        <p class="text-xs text-muted-foreground">
                            {{ t('employees.support_requests') }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Filters -->
            <Card>
                <CardContent class="pt-6">
                    <div class="flex flex-col lg:flex-row gap-4">
                        <div class="flex-1 relative">
                            <div class="absolute inset-y-0 left-0 rtl:left-auto rtl:right-0 pl-3 rtl:pl-0 rtl:pr-3 flex items-center pointer-events-none">
                                <Icon name="Search" class="h-4 w-4 text-gray-400" />
                            </div>
                            <Input
                                v-model="search"
                                :placeholder="t('employees.search_placeholder')"
                                class="w-full pl-10 rtl:pl-3 rtl:pr-10"
                            />
                        </div>
                        
                        <div class="flex flex-col sm:flex-row gap-2">
                            <select 
                                v-model="companyFilter"
                                class="flex h-10 w-full sm:w-48 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <option value="">{{ t('employees.all_companies') }}</option>
                                <option v-for="company in companies" :key="company.id" :value="company.id">
                                    {{ company.name_en || company.name_ar }}
                                </option>
                            </select>

                            <select 
                                v-model="statusFilter"
                                class="flex h-10 w-full sm:w-48 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <option value="">{{ t('employees.all_statuses') }}</option>
                                <option value="active">{{ t('employees.status_active') }}</option>
                                <option value="inactive">{{ t('employees.status_inactive') }}</option>
                                <option value="terminated">{{ t('employees.status_terminated') }}</option>
                            </select>

                            <select 
                                v-model="departmentFilter"
                                class="flex h-10 w-full sm:w-48 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <option value="">{{ t('employees.all_departments') }}</option>
                                <option v-for="dept in uniqueDepartments" :key="dept" :value="dept">
                                    {{ dept }}
                                </option>
                            </select>

                            <Button variant="ghost" @click="clearFilters" v-if="search || statusFilter || departmentFilter || companyFilter">
                                <Icon name="X" class="mr-2 rtl:mr-0 rtl:ml-2 h-4 w-4" />
                                {{ t('common.clear') }}
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Bulk Actions -->
            <div v-if="selectedEmployees.length > 0" class="flex items-center justify-between p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                <div class="flex items-center gap-2">
                    <Icon name="CheckCircle" class="h-5 w-5 text-blue-600" />
                    <span class="text-sm font-medium">
                        {{ t('employees.selected_count', { count: selectedEmployees.length }) }}
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="outline" size="sm">
                                <Icon name="MoreVertical" class="h-4 w-4 mr-2 rtl:mr-0 rtl:ml-2" />
                                {{ t('employees.bulk_actions') }}
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem @click="handleBulkAction('activate')">
                                <Icon name="CheckCircle" class="mr-2 rtl:mr-0 rtl:ml-2 h-4 w-4" />
                                {{ t('employees.activate') }}
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="handleBulkAction('deactivate')">
                                <Icon name="Clock" class="mr-2 rtl:mr-0 rtl:ml-2 h-4 w-4" />
                                {{ t('employees.deactivate') }}
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="handleBulkAction('terminate')" class="text-red-600">
                                <Icon name="XCircle" class="mr-2 rtl:mr-0 rtl:ml-2 h-4 w-4" />
                                {{ t('employees.terminate') }}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                    <Button variant="ghost" size="sm" @click="selectedEmployees = []">
                        <Icon name="X" class="h-4 w-4" />
                    </Button>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="employees.data.length === 0" class="text-center py-12">
                <Icon name="Users" class="mx-auto h-12 w-12 text-gray-400 mb-4" />
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">
                    {{ t('employees.no_employees') }}
                </h3>
                <p class="text-gray-600 dark:text-gray-400 mb-6">
                    {{ search || statusFilter || departmentFilter ? t('employees.no_employees_found') : t('employees.create_first_employee') }}
                </p>
                <Button asChild v-if="!search && !statusFilter && !departmentFilter" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold">
                    <Link :href="route('employees.create')">
                        <Icon name="Plus" class="mr-2 rtl:mr-0 rtl:ml-2 h-4 w-4" />
                        {{ t('employees.create_employee') }}
                    </Link>
                </Button>
            </div>

            <!-- Employees Table -->
            <Card v-else>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr class="text-center">
                                <th class="px-6 py-4 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    <div class="flex justify-center">
                                        <Checkbox 
                                            :checked="selectAll"
                                            @update:checked="toggleSelectAll"
                                        />
                                    </div>
                                </th>
                                <th class="px-6 py-4 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    {{ t('employees.employee') }}
                                </th>
                                <th class="px-6 py-4 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    {{ t('employees.job_information') }}
                                </th>
                                <th class="px-6 py-4 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    {{ t('employees.contact') }}
                                </th>
                                <th class="px-6 py-4 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    {{ t('employees.status') }}
                                </th>
                                <th class="px-6 py-4 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    {{ t('employees.metrics') }}
                                </th>
                                <th class="px-6 py-4 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    {{ t('common.actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="employee in employees.data" :key="employee.id" 
                                class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                :class="{ 'bg-blue-50 dark:bg-blue-900/20': selectedEmployees.includes(employee.id) }"
                            >
                                <td class="px-6 py-4 text-center">
                                    <div class="flex justify-center">
                                        <Checkbox 
                                            :checked="selectedEmployees.includes(employee.id)"
                                            @update:checked="() => toggleEmployeeSelection(employee.id)"
                                        />
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="flex items-center justify-center">
                                        <div class="flex-shrink-0 h-12 w-12">
                                            <div class="h-12 w-12 rounded-full bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center">
                                                <span class="text-sm font-semibold text-white">
                                                    {{ (employee.first_name?.[0] || '') + (employee.last_name?.[0] || '') }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="ml-4 rtl:ml-0 rtl:mr-4 text-left rtl:text-right">
                                            <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                {{ employee.first_name }} {{ employee.last_name }}
                                            </div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400 font-mono">
                                                {{ employee.employee_id }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ employee.job_title || '-' }}
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ employee.department || '-' }}
                                    </div>
                                    <div class="text-xs text-gray-400 dark:text-gray-500">
                                        {{ employee.company?.name_en || 'No Company' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="text-sm text-gray-900 dark:text-gray-100">
                                        {{ employee.email }}
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ employee.phone || '-' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="flex items-center justify-center">
                                        <Icon :name="getStatusIcon(employee.employment_status)" 
                                              :class="`h-4 w-4 mr-2 rtl:mr-0 rtl:ml-2 ${
                                                employee.employment_status === 'active' ? 'text-green-600' : 
                                                employee.employment_status === 'inactive' ? 'text-yellow-600' : 
                                                employee.employment_status === 'terminated' ? 'text-red-600' : 
                                                'text-gray-500'
                                              }`"
                                        />
                                        <Badge :variant="getStatusVariant(employee.employment_status)">
                                            {{ t(`employees.status_${employee.employment_status}`) }}
                                        </Badge>
                                    </div>
                                    <div v-if="employee.hire_date" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        {{ t('employees.since') }} {{ formatDate(employee.hire_date) }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="flex flex-col gap-1 items-center">
                                        <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                                            <Icon name="Package" class="h-4 w-4 mr-2 rtl:mr-0 rtl:ml-2 text-blue-600" />
                                            <span>{{ employee.assets_count || 0 }} {{ t('employees.assets') }}</span>
                                        </div>
                                        <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                                            <Icon name="Ticket" class="h-4 w-4 mr-2 rtl:mr-0 rtl:ml-2 text-orange-600" />
                                            <span>{{ employee.reported_tickets_count || 0 }} {{ t('employees.tickets') }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="flex justify-center gap-1">
                                        <Button variant="ghost" size="sm" asChild>
                                            <Link :href="route('employees.show', employee.id)">
                                                <Icon name="Eye" class="h-4 w-4" />
                                                <span class="sr-only">{{ t('common.view') }}</span>
                                            </Link>
                                        </Button>
                                        <Button variant="ghost" size="sm" asChild>
                                            <Link :href="route('employees.edit', employee.id)">
                                                <Icon name="Pencil" class="h-4 w-4" />
                                                <span class="sr-only">{{ t('common.edit') }}</span>
                                            </Link>
                                        </Button>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button variant="ghost" size="sm">
                                                    <Icon name="MoreVertical" class="h-4 w-4" />
                                                    <span class="sr-only">{{ t('common.more_actions') }}</span>
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem asChild>
                                                    <Link :href="route('employees.show', employee.id)">
                                                        <Icon name="Eye" class="mr-2 rtl:mr-0 rtl:ml-2 h-4 w-4" />
                                                        {{ t('common.view') }}
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem asChild>
                                                    <Link :href="route('employees.edit', employee.id)">
                                                        <Icon name="Pencil" class="mr-2 rtl:mr-0 rtl:ml-2 h-4 w-4" />
                                                        {{ t('common.edit') }}
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem>
                                                    <Icon name="Package" class="mr-2 rtl:mr-0 rtl:ml-2 h-4 w-4" />
                                                    {{ t('employees.assign_assets') }}
                                                </DropdownMenuItem>
                                                <DropdownMenuItem>
                                                    <Icon name="Mail" class="mr-2 rtl:mr-0 rtl:ml-2 h-4 w-4" />
                                                    {{ t('employees.send_email') }}
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem
                                                    class="text-red-600 focus:text-red-600 focus:bg-red-50 dark:focus:bg-red-950/30"
                                                    @click="deleteEmployee(employee)"
                                                >
                                                    <Icon name="Trash2" class="mr-2 rtl:mr-0 rtl:ml-2 h-4 w-4" />
                                                    {{ t('common.delete') }}
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>
        </div>
    </AppLayout>
</template> 
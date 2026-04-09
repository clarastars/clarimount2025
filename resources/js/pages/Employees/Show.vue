<template>
    <AppLayout>
        <div class="max-w-7xl mx-auto px-4 py-8">
            <div class="space-y-8">
                <div class="space-y-3">
                    <Breadcrumbs :breadcrumbs="breadcrumbs" />
                    <Card class="border-border/60 shadow-sm">
                        <CardContent class="pt-6">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                <div class="space-y-1">
                                    <Heading :title="employee.full_name" />
                                    <div class="flex items-center gap-2">
                                        <Badge :class="getStatusBadgeClass(employee.employment_status)">
                                            {{ t(`employees.status_${employee.employment_status}`) }}
                                        </Badge>
                                        <span class="text-sm font-mono text-muted-foreground">{{ displayValue(employee.employee_id) }}</span>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <Button variant="outline" asChild>
                                        <Link :href="route('employees.edit', employee.id)">
                                            <Icon name="SquarePen" class="mr-2 h-4 w-4" />
                                            {{ t('employees.edit') }}
                                        </Link>
                                    </Button>
                                    <Button variant="secondary" asChild>
                                        <Link :href="route('employees.custody.show', employee.id)">
                                            <Icon name="Package" class="mr-2 h-4 w-4" />
                                            {{ t('custody.update_custody') }}
                                        </Link>
                                    </Button>
                                    <Button variant="default" asChild>
                                        <Link :href="route('employees.leaves.create', employee.id)">
                                            <Icon name="CalendarPlus" class="mr-2 h-4 w-4" />
                                            {{ t('leaves.create_leave') }}
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Employee Details -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- General Information -->
                        <Card class="border-border/60 shadow-sm">
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <Icon name="User" class="h-5 w-5 text-blue-600" />
                                    {{ t('employees.general_information') }}
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.employee_id') }}</Label>
                                    <p class="text-sm font-mono">{{ displayValue(employee.employee_id) }}</p>
                                </div>
                                
                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.full_name') }}</Label>
                                    <p class="text-sm">{{ displayValue(employee.first_name) }} {{ displayValue(employee.father_name, '') }} {{ displayValue(employee.last_name) }}</p>
                                </div>
                                
                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.nationality') }}</Label>
                                    <p class="text-sm">{{ displayValue(employee.nationality?.name) }}</p>
                                </div>
                                
                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.residence_country') }}</Label>
                                    <p class="text-sm">{{ displayValue(employee.residence_country?.name) }}</p>
                                </div>
                                
                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.birth_date') }}</Label>
                                    <p class="text-sm">{{ displayDate(employee.birth_date) }}</p>
                                </div>
                                
                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.email') }}</Label>
                                    <p class="text-sm">{{ displayValue(employee.email) }}</p>
                                </div>
                                
                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.personal_email') }}</Label>
                                    <p class="text-sm">{{ displayValue(employee.personal_email) }}</p>
                                </div>
                                
                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.work_email') }}</Label>
                                    <p class="text-sm">{{ displayValue(employee.work_email) }}</p>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Work Details -->
                        <Card class="border-border/60 shadow-sm">
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <Icon name="Briefcase" class="h-5 w-5 text-green-600" />
                                    {{ t('employees.work_details') }}
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.job_title') }}</Label>
                                    <p class="text-sm">{{ displayValue(employee.job_title) }}</p>
                                </div>
                                
                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.department') }}</Label>
                                    <p class="text-sm">{{ displayValue(employee.department) }}</p>
                                </div>
                                
                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.employment_date') }}</Label>
                                    <p class="text-sm">{{ displayDate(employee.employment_date) }}</p>
                                </div>
                                
                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.probation_end_date') }}</Label>
                                    <p class="text-sm">{{ displayDate(employee.probation_end_date) }}</p>
                                </div>
                                
                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.work_phone') }}</Label>
                                    <p class="text-sm">{{ displayValue(employee.work_phone) }}</p>
                                </div>
                                
                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.phone') }}</Label>
                                    <p class="text-sm">{{ displayValue(employee.phone) }}</p>
                                </div>
                                
                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.mobile') }}</Label>
                                    <p class="text-sm">{{ displayValue(employee.mobile) }}</p>
                                </div>
                                
                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.fingerprint_device_id') }}</Label>
                                    <p class="text-sm font-mono">{{ displayValue(employee.fingerprint_device_id) }}</p>
                                </div>
                                
                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.shift') }}</Label>
                                    <p class="text-sm">{{ displayValue(employee.shift?.name) }}</p>
                                </div>
                                
                                <div class="md:col-span-2">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.work_address') }}</Label>
                                    <p class="text-sm">{{ displayValue(employee.work_address) }}</p>
                                </div>

                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.basic_salary') }}</Label>
                                    <p class="text-sm font-medium">{{ displayCurrency(employee.basic_salary) }}</p>
                                </div>
                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.allowances') }}</Label>
                                    <p class="text-sm font-medium">{{ displayCurrency(employee.allowances) }}</p>
                                </div>
                                <template v-if="hasAllowanceBreakdown">
                                    <div class="md:col-span-2 w-full mt-2 pt-4 border-t border-gray-200 dark:border-gray-700">
                                        <Label class="text-sm font-medium text-muted-foreground mb-2 block">{{ t('employees.allowances_breakdown') }}</Label>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <div v-if="employee.allowance_housing != null && parseFloat(employee.allowance_housing) > 0" class="flex justify-between text-sm py-1">
                                                <span class="text-muted-foreground">{{ t('employees.allowance_housing') }}</span>
                                                <span class="font-medium">{{ formatCurrency(parseFloat(employee.allowance_housing)) }}</span>
                                            </div>
                                            <div v-if="employee.allowance_transportation != null && parseFloat(employee.allowance_transportation) > 0" class="flex justify-between text-sm py-1">
                                                <span class="text-muted-foreground">{{ t('employees.allowance_transportation') }}</span>
                                                <span class="font-medium">{{ formatCurrency(parseFloat(employee.allowance_transportation)) }}</span>
                                            </div>
                                            <div v-if="employee.allowance_other != null && parseFloat(employee.allowance_other) > 0" class="flex justify-between text-sm py-1">
                                                <span class="text-muted-foreground">{{ t('employees.allowance_other') }}</span>
                                                <span class="font-medium">{{ formatCurrency(parseFloat(employee.allowance_other)) }}</span>
                                            </div>
                                            <div v-if="employee.allowance_food != null && parseFloat(employee.allowance_food) > 0" class="flex justify-between text-sm py-1">
                                                <span class="text-muted-foreground">{{ t('employees.allowance_food') }}</span>
                                                <span class="font-medium">{{ formatCurrency(parseFloat(employee.allowance_food)) }}</span>
                                            </div>
                                            <div v-if="employee.allowance_personal_car != null && parseFloat(employee.allowance_personal_car) > 0" class="flex justify-between text-sm py-1">
                                                <span class="text-muted-foreground">{{ t('employees.allowance_personal_car') }}</span>
                                                <span class="font-medium">{{ formatCurrency(parseFloat(employee.allowance_personal_car)) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </CardContent>
                        </Card>

                        <!-- Annual Leave Balance -->
                        <Card class="border-border/60 shadow-sm">
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <Icon name="Calendar" class="h-5 w-5 text-amber-600" />
                                    {{ t('leaves.annual_leave_section') }}
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('leaves.annual_leave_balance') }}</Label>
                                        <p class="text-sm font-medium">{{ displayValue(employee.annual_leave_balance) }} {{ t('leaves.days') }}</p>
                                    </div>
                                    <div>
                                        <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('leaves.remaining_balance') }}</Label>
                                        <p class="text-sm font-medium">{{ displayValue(employee.remaining_annual_leave_balance) }} {{ t('leaves.days') }}</p>
                                    </div>
                                </div>
                                <!-- Leave History -->
                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2 block">{{ t('leaves.leave_history') }}</Label>
                                    <div v-if="employee.leaves && employee.leaves.length > 0" class="space-y-2">
                                        <div
                                            v-for="leave in employee.leaves"
                                            :key="leave.id"
                                            class="flex items-center justify-between p-3 border rounded-lg bg-muted/30"
                                        >
                                            <div class="flex items-center gap-2">
                                                <Icon name="Calendar" class="h-4 w-4 text-amber-600" />
                                                <span class="text-sm font-medium">{{ t(`leaves.type_${leave.leave_type}`) }}</span>
                                            </div>
                                            <div class="text-sm text-muted-foreground">
                                                {{ new Date(leave.start_date).toLocaleDateString() }} — {{ new Date(leave.end_date).toLocaleDateString() }}
                                                <span class="text-xs">({{ leave.days }} {{ t('leaves.days') }})</span>
                                            </div>
                                        </div>
                                    </div>
                                    <p v-else class="text-sm text-muted-foreground py-2">{{ t('leaves.no_leaves_yet') }}</p>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Legal Information -->
                        <Card class="border-border/60 shadow-sm">
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <Icon name="FileText" class="h-5 w-5 text-orange-600" />
                                    {{ t('employees.legal_information') }}
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.id_number') }}</Label>
                                    <p class="text-sm font-mono">{{ displayValue(employee.id_number) }}</p>
                                </div>
                                
                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.residence_expiry_date') }}</Label>
                                    <p class="text-sm">{{ displayDate(employee.residence_expiry_date) }}</p>
                                </div>
                                
                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.contract_end_date') }}</Label>
                                    <p class="text-sm">{{ displayDate(employee.contract_end_date) }}</p>
                                </div>
                                
                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.exit_reentry_visa_expiry') }}</Label>
                                    <p class="text-sm">{{ displayDate(employee.exit_reentry_visa_expiry) }}</p>
                                </div>
                                
                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.passport_number') }}</Label>
                                    <p class="text-sm font-mono">{{ displayValue(employee.passport_number) }}</p>
                                </div>
                                
                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.passport_expiry_date') }}</Label>
                                    <p class="text-sm">{{ displayDate(employee.passport_expiry_date) }}</p>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Insurance -->
                        <Card class="border-border/60 shadow-sm">
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <Icon name="Shield" class="h-5 w-5 text-purple-600" />
                                    {{ t('employees.insurance') }}
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.insurance_policy') }}</Label>
                                    <p class="text-sm">{{ displayValue(employee.insurance_policy) }}</p>
                                </div>
                                
                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.insurance_expiry_date') }}</Label>
                                    <p class="text-sm">{{ displayDate(employee.insurance_expiry_date) }}</p>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Employment Status -->
                        <Card class="border-border/60 shadow-sm">
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <Icon name="Calendar" class="h-5 w-5 text-indigo-600" />
                                    {{ t('employees.employment_status') }}
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.employment_status') }}</Label>
                                    <Badge :class="getStatusBadgeClass(employee.employment_status)" class="text-xs">
                                        {{ t(`employees.status_${employee.employment_status}`) }}
                                    </Badge>
                                </div>
                                
                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.hire_date') }}</Label>
                                    <p class="text-sm">{{ displayDate(employee.hire_date) }}</p>
                                </div>
                                
                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.termination_date') }}</Label>
                                    <p class="text-sm">{{ displayDate(employee.termination_date) }}</p>
                                </div>
                                
                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.departure_date') }}</Label>
                                    <p class="text-sm">{{ displayDate(employee.departure_date) }}</p>
                                </div>
                                
                                <div class="md:col-span-2">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.departure_reason') }}</Label>
                                    <p class="text-sm">{{ displayValue(employee.departure_reason) }}</p>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Managers / Workflow -->
                        <Card class="border-border/60 shadow-sm">
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <Icon name="Users" class="h-5 w-5 text-cyan-600" />
                                    {{ t('employees.managers_workflow') }}
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.manager') }}</Label>
                                    <p class="text-sm">{{ displayValue(employee.manager) }}</p>
                                </div>
                                
                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.direct_manager') }}</Label>
                                    <p class="text-sm">{{ displayValue(employee.direct_manager) }}</p>
                                </div>
                                
                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.additional_approver_2') }}</Label>
                                    <p class="text-sm">{{ displayValue(employee.additional_approver_2) }}</p>
                                </div>
                                
                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.additional_approver_3') }}</Label>
                                    <p class="text-sm">{{ displayValue(employee.additional_approver_3) }}</p>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Emergency Contact -->
                        <Card class="border-border/60 shadow-sm">
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <Icon name="Phone" class="h-5 w-5 text-red-600" />
                                    {{ t('employees.emergency_contact') }}
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="grid grid-cols-1 md:grid-cols-2 gap-4 [&>div]:rounded-lg [&>div]:border [&>div]:p-3 [&>div]:bg-muted/20">
                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.emergency_contact_name') }}</Label>
                                    <p class="text-sm">{{ displayValue(employee.emergency_contact_name) }}</p>
                                </div>
                                
                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.emergency_contact_phone') }}</Label>
                                    <p class="text-sm">{{ displayValue(employee.emergency_contact_phone) }}</p>
                                </div>
                                
                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.emergency_contact_email') }}</Label>
                                    <p class="text-sm">{{ displayValue(employee.emergency_contact_email) }}</p>
                                </div>
                                
                                <div class="md:col-span-2">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.emergency_contact_address') }}</Label>
                                    <p class="text-sm whitespace-pre-wrap">{{ displayValue(employee.emergency_contact_address) }}</p>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Additional Information -->
                        <Card class="border-border/60 shadow-sm">
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <Icon name="FileText" class="h-5 w-5 text-gray-600" />
                                    {{ t('employees.additional_information') }}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.notes') }}</Label>
                                    <p class="text-sm mt-1 whitespace-pre-wrap">{{ displayValue(employee.notes) }}</p>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Debts -->
                        <Card class="border-border/60 shadow-sm">
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <Icon name="CreditCard" class="h-5 w-5 text-purple-600" />
                                    {{ t('debts.title') }} ({{ employee.debts.length }})
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div class="space-y-3">
                                    <div 
                                        v-for="debt in employee.debts" 
                                        :key="debt.id"
                                        class="flex items-center justify-between p-3 border rounded-lg"
                                    >
                                        <div class="flex-1">
                                            <div class="font-medium">{{ formatCurrency(parseFloat(debt.amount)) }}</div>
                                            <div v-if="debt.debt_type" class="text-sm text-gray-500">{{ debt.debt_type }}</div>
                                        </div>
                                    </div>
                                    <p v-if="!employee.debts || employee.debts.length === 0" class="text-sm text-muted-foreground">
                                        -
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Assets -->
                        <Card class="border-border/60 shadow-sm">
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <Icon name="Package" class="h-5 w-5" />
                                    {{ t('employees.assets_count') }} ({{ employee.assets.length }})
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div class="space-y-3">
                                    <div 
                                        v-for="asset in employee.assets" 
                                        :key="asset.id"
                                        class="flex items-center justify-between p-3 border rounded-lg"
                                    >
                                        <div class="flex items-center gap-3">
                                            <div class="h-8 w-8 rounded-full bg-primary/10 flex items-center justify-center">
                                                <Icon name="Package" class="h-4 w-4" />
                                            </div>
                                            <div>
                                                <p class="font-medium">{{ asset.display_name || asset.model_name || asset.asset_tag }}</p>
                                                <p class="text-sm text-muted-foreground">{{ asset.asset_tag }}</p>
                                            </div>
                                        </div>
                                        <Badge :class="asset.status === 'assigned' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'">
                                            {{ getAssetStatusTranslation(asset.status) }}
                                        </Badge>
                                    </div>
                                    <p v-if="!employee.assets || employee.assets.length === 0" class="text-sm text-muted-foreground">
                                        -
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Tickets -->
                        <Card class="border-border/60 shadow-sm">
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <Icon name="Activity" class="h-5 w-5" />
                                    {{ t('employees.tickets_count') }} ({{ employee.reported_tickets.length }})
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div class="space-y-3">
                                    <div 
                                        v-for="ticket in employee.reported_tickets" 
                                        :key="ticket.id"
                                        class="flex items-center justify-between p-3 border rounded-lg"
                                    >
                                        <div class="flex items-center gap-3">
                                            <div class="h-8 w-8 rounded-full bg-primary/10 flex items-center justify-center">
                                                <Icon name="Activity" class="h-4 w-4" />
                                            </div>
                                            <div>
                                                <p class="font-medium">{{ ticket.subject }}</p>
                                                <p class="text-sm text-muted-foreground">{{ ticket.ticket_number }}</p>
                                            </div>
                                        </div>
                                        <Badge :class="getTicketStatusBadgeClass(ticket.status)">
                                            {{ ticket.status }}
                                        </Badge>
                                    </div>
                                    <p v-if="!employee.reported_tickets || employee.reported_tickets.length === 0" class="text-sm text-muted-foreground">
                                        -
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Quick Stats -->
                    <div class="space-y-6">
                        <Card class="border-border/60 shadow-sm">
                            <CardHeader>
                                <CardTitle>{{ t('common.statistics') }}</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <Icon name="Package" class="h-4 w-4 text-muted-foreground" />
                                        <span class="text-sm">{{ t('employees.assets_count') }}</span>
                                    </div>
                                    <span class="font-medium">{{ employee.assets_count || 0 }}</span>
                                </div>
                                
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <Icon name="Activity" class="h-4 w-4 text-muted-foreground" />
                                        <span class="text-sm">{{ t('employees.tickets_count') }}</span>
                                    </div>
                                    <span class="font-medium">{{ employee.reported_tickets_count || 0 }}</span>
                                </div>
                            </CardContent>
                        </Card>
                        
                        <Card>
                            <CardHeader>
                                <CardTitle>{{ t('common.created_at') }}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p class="text-sm text-muted-foreground">
                                    {{ new Date(employee.created_at).toLocaleDateString() }}
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { 
    Card, 
    CardContent, 
    CardHeader, 
    CardTitle 
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import Heading from '@/components/Heading.vue';
import Icon from '@/components/Icon.vue';
import type { Employee } from '@/types';
import type { BreadcrumbItem } from '@/types';

interface Props {
    employee: Employee;
}

const props = defineProps<Props>();
const { t } = useI18n();

const breadcrumbs = computed((): BreadcrumbItem[] => [
    {
        title: t('nav.dashboard'),
        href: '/dashboard',
    },
    {
        title: t('employees.title'),
        href: '/employees',
    },
    {
        title: props.employee.full_name || `${props.employee.first_name} ${props.employee.last_name}`,
        href: `/employees/${props.employee.id}`,
    },
]);

const hasAllowanceBreakdown = computed(() => {
    const e = props.employee as Record<string, unknown>;
    const vals = ['allowance_housing', 'allowance_transportation', 'allowance_other', 'allowance_food', 'allowance_personal_car'];
    return vals.some((k) => e[k] != null && parseFloat(String(e[k])) > 0);
});

const getStatusBadgeClass = (status: string): string => {
    switch (status) {
        case 'active':
            return 'bg-green-100 text-green-800';
        case 'inactive':
            return 'bg-yellow-100 text-yellow-800';
        case 'terminated':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

const getTicketStatusBadgeClass = (status: string): string => {
    switch (status) {
        case 'open':
            return 'bg-blue-100 text-blue-800';
        case 'in_progress':
            return 'bg-yellow-100 text-yellow-800';
        case 'resolved':
            return 'bg-green-100 text-green-800';
        case 'closed':
            return 'bg-gray-100 text-gray-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

const getAssetStatusTranslation = (status: string): string => {
    switch (status) {
        case 'available':
            return t('assets.status_available');
        case 'assigned':
            return t('assets.status_assigned');
        case 'maintenance':
            return t('assets.status_maintenance');
        case 'retired':
            return t('assets.status_retired');
        default:
            return status;
    }
};

const formatCurrency = (amount: number) => {
    return amount.toFixed(2) + ' SAR';
};

const displayValue = (value: unknown, fallback = '-'): string => {
    if (value === null || value === undefined) return fallback;
    const str = String(value).trim();
    return str.length > 0 ? str : fallback;
};

const displayDate = (value: unknown): string => {
    if (!value) return '-';
    const date = new Date(String(value));
    if (Number.isNaN(date.getTime())) return '-';
    return date.toLocaleDateString();
};

const displayCurrency = (value: unknown): string => {
    const n = Number(value);
    if (Number.isNaN(n)) return '-';
    return formatCurrency(n);
};
</script> 
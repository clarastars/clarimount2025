<template>
    <AppLayout>
        <div class="container mx-auto py-8">
            <div class="space-y-6">
                <div class="space-y-2">
                    <Breadcrumbs :breadcrumbs="breadcrumbs" />
                    <div class="flex items-center justify-between">
                        <div class="space-y-1">
                            <Heading :title="employee.full_name" />
                            <div class="flex items-center gap-2">
                                <Badge :class="getStatusBadgeClass(employee.employment_status)">
                                    {{ t(`employees.status_${employee.employment_status}`) }}
                                </Badge>
                                <span class="text-sm font-mono text-muted-foreground">{{ employee.employee_id }}</span>
                            </div>
                        </div>
                        <div class="flex gap-2">
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
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Employee Details -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- General Information -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <Icon name="User" class="h-5 w-5 text-blue-600" />
                                    {{ t('employees.general_information') }}
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div v-if="employee.employee_id">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.employee_id') }}</Label>
                                    <p class="text-sm font-mono">{{ employee.employee_id }}</p>
                                </div>
                                
                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.full_name') }}</Label>
                                    <p class="text-sm">{{ employee.first_name }} {{ employee.father_name ? employee.father_name + ' ' : '' }}{{ employee.last_name }}</p>
                                </div>
                                
                                <div v-if="employee.nationality">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.nationality') }}</Label>
                                    <p class="text-sm">{{ employee.nationality.name }}</p>
                                </div>
                                
                                <div v-if="employee.residence_country">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.residence_country') }}</Label>
                                    <p class="text-sm">{{ employee.residence_country.name }}</p>
                                </div>
                                
                                <div v-if="employee.birth_date">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.birth_date') }}</Label>
                                    <p class="text-sm">{{ new Date(employee.birth_date).toLocaleDateString() }}</p>
                                </div>
                                
                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.email') }}</Label>
                                    <p class="text-sm">{{ employee.email }}</p>
                                </div>
                                
                                <div v-if="employee.personal_email">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.personal_email') }}</Label>
                                    <p class="text-sm">{{ employee.personal_email }}</p>
                                </div>
                                
                                <div v-if="employee.work_email">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.work_email') }}</Label>
                                    <p class="text-sm">{{ employee.work_email }}</p>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Work Details -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <Icon name="Briefcase" class="h-5 w-5 text-green-600" />
                                    {{ t('employees.work_details') }}
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div v-if="employee.job_title">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.job_title') }}</Label>
                                    <p class="text-sm">{{ employee.job_title }}</p>
                                </div>
                                
                                <div v-if="employee.department">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.department') }}</Label>
                                    <p class="text-sm">{{ employee.department }}</p>
                                </div>
                                
                                <div v-if="employee.employment_date">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.employment_date') }}</Label>
                                    <p class="text-sm">{{ new Date(employee.employment_date).toLocaleDateString() }}</p>
                                </div>
                                
                                <div v-if="employee.probation_end_date">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.probation_end_date') }}</Label>
                                    <p class="text-sm">{{ new Date(employee.probation_end_date).toLocaleDateString() }}</p>
                                </div>
                                
                                <div v-if="employee.work_phone">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.work_phone') }}</Label>
                                    <p class="text-sm">{{ employee.work_phone }}</p>
                                </div>
                                
                                <div v-if="employee.phone">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.phone') }}</Label>
                                    <p class="text-sm">{{ employee.phone }}</p>
                                </div>
                                
                                <div v-if="employee.mobile">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.mobile') }}</Label>
                                    <p class="text-sm">{{ employee.mobile }}</p>
                                </div>
                                
                                <div v-if="employee.fingerprint_device_id">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.fingerprint_device_id') }}</Label>
                                    <p class="text-sm font-mono">{{ employee.fingerprint_device_id }}</p>
                                </div>
                                
                                <div v-if="employee.shift">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.shift') }}</Label>
                                    <p class="text-sm">{{ employee.shift.name }}</p>
                                </div>
                                
                                <div v-if="employee.work_address" class="md:col-span-2">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.work_address') }}</Label>
                                    <p class="text-sm">{{ employee.work_address }}</p>
                                </div>

                                <div v-if="employee.basic_salary != null && employee.basic_salary !== ''">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.basic_salary') }}</Label>
                                    <p class="text-sm font-medium">{{ formatCurrency(parseFloat(employee.basic_salary)) }}</p>
                                </div>
                                <div v-if="employee.allowances != null && employee.allowances !== ''">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.allowances') }}</Label>
                                    <p class="text-sm font-medium">{{ formatCurrency(parseFloat(employee.allowances)) }}</p>
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
                        <Card v-if="employee.annual_leave_balance != null && employee.annual_leave_balance >= 0">
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
                                        <p class="text-sm font-medium">{{ employee.annual_leave_balance }} {{ t('leaves.days') }}</p>
                                    </div>
                                    <div>
                                        <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('leaves.remaining_balance') }}</Label>
                                        <p class="text-sm font-medium">{{ employee.remaining_annual_leave_balance }} {{ t('leaves.days') }}</p>
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
                        <Card v-if="employee.id_number || employee.passport_number || employee.residence_expiry_date || employee.contract_end_date">
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <Icon name="FileText" class="h-5 w-5 text-orange-600" />
                                    {{ t('employees.legal_information') }}
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div v-if="employee.id_number">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.id_number') }}</Label>
                                    <p class="text-sm font-mono">{{ employee.id_number }}</p>
                                </div>
                                
                                <div v-if="employee.residence_expiry_date">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.residence_expiry_date') }}</Label>
                                    <p class="text-sm">{{ new Date(employee.residence_expiry_date).toLocaleDateString() }}</p>
                                </div>
                                
                                <div v-if="employee.contract_end_date">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.contract_end_date') }}</Label>
                                    <p class="text-sm">{{ new Date(employee.contract_end_date).toLocaleDateString() }}</p>
                                </div>
                                
                                <div v-if="employee.exit_reentry_visa_expiry">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.exit_reentry_visa_expiry') }}</Label>
                                    <p class="text-sm">{{ new Date(employee.exit_reentry_visa_expiry).toLocaleDateString() }}</p>
                                </div>
                                
                                <div v-if="employee.passport_number">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.passport_number') }}</Label>
                                    <p class="text-sm font-mono">{{ employee.passport_number }}</p>
                                </div>
                                
                                <div v-if="employee.passport_expiry_date">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.passport_expiry_date') }}</Label>
                                    <p class="text-sm">{{ new Date(employee.passport_expiry_date).toLocaleDateString() }}</p>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Insurance -->
                        <Card v-if="employee.insurance_policy || employee.insurance_expiry_date">
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <Icon name="Shield" class="h-5 w-5 text-purple-600" />
                                    {{ t('employees.insurance') }}
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div v-if="employee.insurance_policy">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.insurance_policy') }}</Label>
                                    <p class="text-sm">{{ employee.insurance_policy }}</p>
                                </div>
                                
                                <div v-if="employee.insurance_expiry_date">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.insurance_expiry_date') }}</Label>
                                    <p class="text-sm">{{ new Date(employee.insurance_expiry_date).toLocaleDateString() }}</p>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Employment Status -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <Icon name="Calendar" class="h-5 w-5 text-indigo-600" />
                                    {{ t('employees.employment_status') }}
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.employment_status') }}</Label>
                                    <Badge :class="getStatusBadgeClass(employee.employment_status)" class="text-xs">
                                        {{ t(`employees.status_${employee.employment_status}`) }}
                                    </Badge>
                                </div>
                                
                                <div v-if="employee.hire_date">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.hire_date') }}</Label>
                                    <p class="text-sm">{{ new Date(employee.hire_date).toLocaleDateString() }}</p>
                                </div>
                                
                                <div v-if="employee.termination_date">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.termination_date') }}</Label>
                                    <p class="text-sm">{{ new Date(employee.termination_date).toLocaleDateString() }}</p>
                                </div>
                                
                                <div v-if="employee.departure_date">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.departure_date') }}</Label>
                                    <p class="text-sm">{{ new Date(employee.departure_date).toLocaleDateString() }}</p>
                                </div>
                                
                                <div v-if="employee.departure_reason" class="md:col-span-2">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.departure_reason') }}</Label>
                                    <p class="text-sm">{{ employee.departure_reason }}</p>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Managers / Workflow -->
                        <Card v-if="employee.manager || employee.direct_manager || employee.additional_approver_2 || employee.additional_approver_3">
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <Icon name="Users" class="h-5 w-5 text-cyan-600" />
                                    {{ t('employees.managers_workflow') }}
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div v-if="employee.manager">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.manager') }}</Label>
                                    <p class="text-sm">{{ employee.manager }}</p>
                                </div>
                                
                                <div v-if="employee.direct_manager">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.direct_manager') }}</Label>
                                    <p class="text-sm">{{ employee.direct_manager }}</p>
                                </div>
                                
                                <div v-if="employee.additional_approver_2">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.additional_approver_2') }}</Label>
                                    <p class="text-sm">{{ employee.additional_approver_2 }}</p>
                                </div>
                                
                                <div v-if="employee.additional_approver_3">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.additional_approver_3') }}</Label>
                                    <p class="text-sm">{{ employee.additional_approver_3 }}</p>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Emergency Contact -->
                        <Card v-if="employee.emergency_contact_name || employee.emergency_contact_phone || employee.emergency_contact_email">
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <Icon name="Phone" class="h-5 w-5 text-red-600" />
                                    {{ t('employees.emergency_contact') }}
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div v-if="employee.emergency_contact_name">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.emergency_contact_name') }}</Label>
                                    <p class="text-sm">{{ employee.emergency_contact_name }}</p>
                                </div>
                                
                                <div v-if="employee.emergency_contact_phone">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.emergency_contact_phone') }}</Label>
                                    <p class="text-sm">{{ employee.emergency_contact_phone }}</p>
                                </div>
                                
                                <div v-if="employee.emergency_contact_email">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.emergency_contact_email') }}</Label>
                                    <p class="text-sm">{{ employee.emergency_contact_email }}</p>
                                </div>
                                
                                <div v-if="employee.emergency_contact_address" class="md:col-span-2">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.emergency_contact_address') }}</Label>
                                    <p class="text-sm whitespace-pre-wrap">{{ employee.emergency_contact_address }}</p>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Additional Information -->
                        <Card v-if="employee.notes">
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <Icon name="FileText" class="h-5 w-5 text-gray-600" />
                                    {{ t('employees.additional_information') }}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div>
                                    <Label class="text-sm font-medium text-muted-foreground mb-2">{{ t('employees.notes') }}</Label>
                                    <p class="text-sm mt-1 whitespace-pre-wrap">{{ employee.notes }}</p>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Debts -->
                        <Card v-if="employee.debts && employee.debts.length > 0">
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
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Assets -->
                        <Card v-if="employee.assets && employee.assets.length > 0">
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
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Tickets -->
                        <Card v-if="employee.reported_tickets && employee.reported_tickets.length > 0">
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
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Quick Stats -->
                    <div class="space-y-6">
                        <Card>
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
</script> 
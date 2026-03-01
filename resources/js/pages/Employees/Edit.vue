<template>
    <Head :title="t('employees.edit_employee')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="max-w-6xl mx-auto p-6">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ t('employees.edit_employee') }}</h1>
                <p class="text-gray-600">{{ t('employees.employee_details_description') }}</p>
                <div class="flex gap-2 mt-4">
                    <Badge v-if="completedSections.general" variant="default">General Info ✓</Badge>
                    <Badge v-if="completedSections.work" variant="default">Work Details ✓</Badge>
                    <Badge v-if="completedSections.legal" variant="default">Legal Info ✓</Badge>
                    <Badge v-if="completedSections.insurance" variant="default">Insurance ✓</Badge>
                    <Badge v-if="completedSections.employment" variant="default">Employment ✓</Badge>
                    <Badge v-if="completedSections.managers" variant="default">Managers ✓</Badge>
                    <Badge v-if="completedSections.emergency" variant="default">Emergency ✓</Badge>
                    <Badge v-if="completedSections.additional" variant="default">Additional ✓</Badge>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- General Information Section -->
                <Card>
                    <CardHeader class="cursor-pointer hover:bg-gray-50">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <Icon name="User" class="h-5 w-5 text-blue-600" />
                                <CardTitle class="text-xl">{{ t('employees.general_information') }}</CardTitle>
                                <Badge v-if="completedSections.general" variant="default">✓</Badge>
                            </div>
                            <Icon :name="!sectionGeneral ? 'ChevronRight' : 'ChevronDown'" class="h-5 w-5" />
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                    <!-- Employee ID -->
                                    <div>
                                        <Label for="employee_id" class="mb-2">{{ t('employees.employee_id') }}</Label>
                                        <Input 
                                            id="employee_id"
                                            v-model="form.employee_id" 
                                            type="text" 
                                            :placeholder="t('employees.employee_id_placeholder')"
                                        />
                                        <div v-if="form.errors.employee_id" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.employee_id || "") }}</div>
                                    </div>

                                    <!-- First Name -->
                                    <div>
                                        <Label for="first_name" class="mb-2">{{ t('employees.first_name') }} *</Label>
                                        <Input 
                                            id="first_name"
                                            v-model="form.first_name" 
                                            type="text" 
                                            :placeholder="t('employees.first_name_placeholder')"
                                            required
                                        />
                                        <div v-if="form.errors.first_name" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.first_name || "") }}</div>
                                    </div>

                                    <!-- Father Name -->
                                    <div>
                                        <Label for="father_name" class="mb-2">{{ t('employees.father_name') }}</Label>
                                        <Input 
                                            id="father_name"
                                            v-model="form.father_name" 
                                            type="text" 
                                            :placeholder="t('employees.father_name_placeholder')"
                                        />
                                        <div v-if="form.errors.father_name" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.father_name || "") }}</div>
                                    </div>

                                    <!-- Last Name -->
                                    <div>
                                        <Label for="last_name" class="mb-2">{{ t('employees.last_name') }} *</Label>
                                        <Input 
                                            id="last_name"
                                            v-model="form.last_name" 
                                            type="text" 
                                            :placeholder="t('employees.last_name_placeholder')"
                                            required
                                        />
                                        <div v-if="form.errors.last_name" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.last_name || "") }}</div>
                                    </div>

                                    <!-- Nationality -->
                                    <div>
                                        <Label for="nationality_id" class="mb-2">{{ t('employees.nationality') }}</Label>
                                        <select 
                                            id="nationality_id"
                                            v-model="form.nationality_id"
                                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            <option :value="null">{{ t('employees.select_nationality') }}</option>
                                            <option v-for="nationality in props.nationalities" :key="nationality.id" :value="nationality.id">
                                                {{ nationality.name }}
                                            </option>
                                        </select>
                                        <div v-if="form.errors.nationality_id" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.nationality_id || "") }}</div>
                                    </div>

                                    <!-- Residence Country -->
                                    <div>
                                        <Label for="residence_country_id" class="mb-2">{{ t('employees.residence_country') }}</Label>
                                        <select 
                                            id="residence_country_id"
                                            v-model="form.residence_country_id"
                                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            <option :value="null">{{ t('employees.select_residence_country') }}</option>
                                            <option v-for="country in props.countries" :key="country.id" :value="country.id">
                                                {{ country.name }}
                                            </option>
                                        </select>
                                        <div v-if="form.errors.residence_country_id" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.residence_country_id || "") }}</div>
                                    </div>

                                    <!-- Birth Date -->
                                    <div>
                                        <Label for="birth_date" class="mb-2">{{ t('employees.birth_date') }}</Label>
                                        <Input 
                                            id="birth_date"
                                            v-model="form.birth_date" 
                                            type="date" 
                                        />
                                        <div v-if="form.errors.birth_date" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.birth_date || "") }}</div>
                                    </div>

                                    <!-- Email -->
                                    <div>
                                        <Label for="email" class="mb-2">{{ t('employees.email') }} *</Label>
                                        <Input 
                                            id="email"
                                            v-model="form.email" 
                                            type="email" 
                                            :placeholder="t('employees.email_placeholder')"
                                            required
                                        />
                                        <div v-if="form.errors.email" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.email || "") }}</div>
                                    </div>

                                    <!-- Personal Email -->
                                    <div>
                                        <Label for="personal_email" class="mb-2">{{ t('employees.personal_email') }}</Label>
                                        <Input 
                                            id="personal_email"
                                            v-model="form.personal_email" 
                                            type="email" 
                                            placeholder="personal@email.com"
                                        />
                                        <div v-if="form.errors.personal_email" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.personal_email || "") }}</div>
                                    </div>

                                    <!-- Work Email -->
                                    <div>
                                        <Label for="work_email" class="mb-2">{{ t('employees.work_email') }}</Label>
                                        <Input 
                                            id="work_email"
                                            v-model="form.work_email" 
                                            type="email" 
                                            placeholder="work@company.com"
                                        />
                                        <div v-if="form.errors.work_email" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.work_email || "") }}</div>
                                    </div>

                                    <!-- Phone -->
                                    <div>
                                        <Label for="phone" class="mb-2">{{ t('employees.phone') }}</Label>
                                        <Input 
                                            id="phone"
                                            v-model="form.phone" 
                                            type="text" 
                                            :placeholder="t('employees.phone_placeholder')"
                                        />
                                        <div v-if="form.errors.phone" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.phone || "") }}</div>
                                    </div>

                                    <!-- Location -->
                                    <div>
                                        <Label for="location_id" class="mb-2">{{ t('employees.location') }}</Label>
                                        <select 
                                            id="location_id"
                                            v-model="form.location_id"
                                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            <option value="">{{ t('employees.select_location') }}</option>
                                            <option v-for="location in locations" :key="location.id" :value="location.id">
                                                {{ location.display_name || `${location.code}: ${location.name}` }}
                                            </option>
                                        </select>
                                        <div v-if="form.errors.location_id" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.location_id || "") }}</div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                <!-- Work Details Section -->
                <Card>
                    <Collapsible v-model:open="sectionWork">
                        <CollapsibleTrigger asChild>
                            <CardHeader class="cursor-pointer hover:bg-gray-50">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <Icon name="Building" class="h-5 w-5 text-green-600" />
                                        <CardTitle class="text-xl">{{ t('employees.work_details') }}</CardTitle>
                                        <Badge v-if="completedSections.work" variant="default">✓</Badge>
                                    </div>
                                    <Icon :name="!sectionWork ? 'ChevronRight' : 'ChevronDown'" class="h-5 w-5" />
                                </div>
                            </CardHeader>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <CardContent class="space-y-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                    <!-- Company -->
                                    <div>
                                        <Label for="company_id" class="mb-2">{{ t('employees.company') }} *</Label>
                                        <select 
                                            id="company_id"
                                            v-model="form.company_id"
                                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                            required
                                        >
                                            <option :value="null">{{ t('employees.select_company') }}</option>
                                            <option v-for="company in props.companies" :key="company.id" :value="company.id">
                                                {{ company.name }}
                                            </option>
                                        </select>
                                        <div v-if="form.errors.company_id" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.company_id || "") }}</div>
                                    </div>

                                    <!-- Employment Date -->
                                    <div>
                                        <Label for="employment_date" class="mb-2">{{ t('employees.employment_date') }}</Label>
                                        <Input 
                                            id="employment_date"
                                            v-model="form.employment_date" 
                                            type="date" 
                                        />
                                        <div v-if="form.errors.employment_date" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.employment_date || "") }}</div>
                                    </div>

                                    <!-- Probation End Date -->
                                    <div>
                                        <Label for="probation_end_date" class="mb-2">{{ t('employees.probation_end_date') }}</Label>
                                        <Input 
                                            id="probation_end_date"
                                            v-model="form.probation_end_date" 
                                            type="date" 
                                        />
                                        <div v-if="form.errors.probation_end_date" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.probation_end_date || "") }}</div>
                                    </div>

                                    <!-- Work Phone -->
                                    <div>
                                        <Label for="work_phone" class="mb-2">{{ t('employees.work_phone') }}</Label>
                                        <Input 
                                            id="work_phone"
                                            v-model="form.work_phone" 
                                            type="text" 
                                            placeholder="Work phone number"
                                        />
                                        <div v-if="form.errors.work_phone" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.work_phone || "") }}</div>
                                    </div>

                                    <!-- Mobile -->
                                    <div>
                                        <Label for="mobile" class="mb-2">{{ t('employees.mobile') }}</Label>
                                        <Input 
                                            id="mobile"
                                            v-model="form.mobile" 
                                            type="text" 
                                            :placeholder="t('employees.mobile_placeholder')"
                                        />
                                        <div v-if="form.errors.mobile" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.mobile || "") }}</div>
                                    </div>

                                    <!-- Fingerprint Device ID -->
                                    <div>
                                        <Label for="fingerprint_device_id" class="mb-2">{{ t('employees.fingerprint_device_id') }}</Label>
                                        <Input 
                                            id="fingerprint_device_id"
                                            v-model="form.fingerprint_device_id" 
                                            type="text" 
                                            placeholder="Fingerprint device ID"
                                        />
                                        <div v-if="form.errors.fingerprint_device_id" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.fingerprint_device_id || "") }}</div>
                                    </div>

                                    <!-- Shift -->
                                    <div>
                                        <Label for="shift_id" class="mb-2">{{ t('employees.shift') }}</Label>
                                        <select 
                                            id="shift_id"
                                            v-model="form.shift_id" 
                                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            <option :value="null">{{ $t('employees.shift_placeholder') }}</option>
                                            <option v-for="shift in props.shifts" :key="shift.id" :value="shift.id">
                                                {{ shift.name }}
                                            </option>
                                        </select>
                                        <div v-if="form.errors.shift_id" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.shift_id || "") }}</div>
                                    </div>

                                    <!-- Work Address -->
                                    <div class="md:col-span-2 lg:col-span-3">
                                        <Label for="work_address" class="mb-2">{{ t('employees.work_address') }}</Label>
                                        <textarea 
                                            id="work_address"
                                            v-model="form.work_address" 
                                            rows="3"
                                            placeholder="Work address"
                                            class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                        ></textarea>
                                        <div v-if="form.errors.work_address" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.work_address || "") }}</div>
                                    </div>

                                    <!-- Department -->
                                    <div class="md:col-span-3">
                                        <Label for="department" class="mb-2">{{ t('employees.department') }}</Label>
                                        <div class="relative">
                                            <div class="flex">
                                                <Input 
                                                    id="department"
                                                    v-model="departmentSearchQuery"
                                                    type="text" 
                                                    :placeholder="form.company_id ? t('employees.department_placeholder') : 'Select a company first'"
                                                    :disabled="!form.company_id"
                                                    class="rounded-r-none"
                                                    @focus="form.company_id && (showDepartmentDropdown = departmentSearchQuery.length >= 2)"
                                                    @blur="handleDepartmentBlur"
                                                />
                                                <Button
                                                    v-if="selectedDepartment"
                                                    type="button"
                                                    variant="outline"
                                                    class="rounded-l-none border-l-0"
                                                    @click="clearDepartment"
                                                >
                                                    <Icon name="X" class="h-4 w-4" />
                                                </Button>
                                            </div>
                                            
                                            <div v-if="!form.company_id" class="mt-2 text-sm text-gray-500">
                                                Please select a company first to search for departments.
                                            </div>
                                            
                                            <!-- Department Dropdown -->
                                            <div v-if="showDepartmentDropdown && form.company_id" class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto">
                                                <div v-if="departmentSearchLoading" class="px-3 py-2 text-gray-500">
                                                    {{ t('common.searching') }}...
                                                </div>
                                                <div v-else-if="departmentSearchResults.length === 0 && departmentSearchQuery.length >= 2" class="px-3 py-2 text-gray-500">
                                                    {{ t('employees.no_departments_found') }}
                                                </div>
                                                <div v-else>
                                                    <button
                                                        v-for="department in departmentSearchResults"
                                                        :key="department.id"
                                                        type="button"
                                                        @click="selectDepartment(department)"
                                                        class="w-full px-3 py-2 text-left hover:bg-gray-100 focus:bg-gray-100 focus:outline-none"
                                                    >
                                                        <div class="font-medium">{{ department.name }}</div>
                                                        <div class="text-sm text-gray-500">{{ department.company_name }}</div>
                                                    </button>
                                                </div>
                                            </div>
                                            
                                            <div v-if="selectedDepartment" class="mt-2 p-3 bg-blue-50 border border-blue-200 rounded-md">
                                                <div class="text-sm font-medium text-blue-800">{{ t('common.selected') }}: {{ selectedDepartment.name }}</div>
                                                <div class="text-xs text-blue-600">{{ selectedDepartment.company_name }}</div>
                                            </div>
                                        </div>
                                        <div v-if="form.errors.department_id" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.department_id || "") }}</div>
                                    </div>

                                    <!-- Job Title -->
                                    <div>
                                        <Label for="job_title" class="mb-2">{{ t('employees.job_title') }}</Label>
                                        <Input 
                                            id="job_title"
                                            v-model="form.job_title" 
                                            type="text" 
                                            :placeholder="t('employees.job_title_placeholder')"
                                        />
                                        <div v-if="form.errors.job_title" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.job_title || "") }}</div>
                                    </div>

                                    <!-- Basic Salary -->
                                    <div>
                                        <Label for="basic_salary" class="mb-2">{{ t('employees.basic_salary') }}</Label>
                                        <Input 
                                            id="basic_salary"
                                            v-model="form.basic_salary" 
                                            type="number" 
                                            step="0.01"
                                            min="0"
                                            :placeholder="t('employees.basic_salary_placeholder')"
                                        />
                                        <div v-if="form.errors.basic_salary" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.basic_salary || "") }}</div>
                                    </div>

                                    <!-- Allowances -->
                                    <div>
                                        <Label for="allowances" class="mb-2">{{ t('employees.allowances') }}</Label>
                                        <Input 
                                            id="allowances"
                                            v-model="form.allowances" 
                                            type="number" 
                                            step="0.01"
                                            min="0"
                                            :placeholder="t('employees.allowances_placeholder')"
                                        />
                                        <div v-if="form.errors.allowances" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.allowances || "") }}</div>
                                    </div>
                                </div>
                            </CardContent>
                        </CollapsibleContent>
                    </Collapsible>
                </Card>

                <!-- Annual Leave Section -->
                <Card>
                    <CardHeader class="cursor-pointer hover:bg-gray-50">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <Icon name="Calendar" class="h-5 w-5 text-amber-600" />
                                <CardTitle class="text-xl">{{ t('leaves.annual_leave_section') }}</CardTitle>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div>
                                <Label for="annual_leave_balance" class="mb-2">{{ t('leaves.annual_leave_balance') }}</Label>
                                <Input
                                    id="annual_leave_balance"
                                    v-model.number="form.annual_leave_balance"
                                    type="number"
                                    min="0"
                                    step="1"
                                    :placeholder="t('leaves.annual_leave_balance_placeholder')"
                                />
                                <div v-if="form.errors.annual_leave_balance" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.annual_leave_balance || "") }}</div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Legal Information Section -->
                <Card>
                    <Collapsible v-model:open="sectionLegal">
                        <CollapsibleTrigger asChild>
                            <CardHeader class="cursor-pointer hover:bg-gray-50">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <Icon name="FileText" class="h-5 w-5 text-orange-600" />
                                        <CardTitle class="text-xl">{{ t('employees.legal_information') }}</CardTitle>
                                        <Badge v-if="completedSections.legal" variant="default">✓</Badge>
                                    </div>
                                    <Icon :name="!sectionLegal ? 'ChevronRight' : 'ChevronDown'" class="h-5 w-5" />
                                </div>
                            </CardHeader>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <CardContent class="space-y-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                    <!-- ID Number -->
                                    <div>
                                        <Label for="id_number" class="mb-2">{{ t('employees.id_number') }}</Label>
                                        <Input 
                                            id="id_number"
                                            v-model="form.id_number" 
                                            type="text" 
                                            placeholder="National ID number"
                                        />
                                        <div v-if="form.errors.id_number" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.id_number || "") }}</div>
                                    </div>

                                    <!-- Residence Expiry Date -->
                                    <div>
                                        <Label for="residence_expiry_date" class="mb-2">{{ t('employees.residence_expiry_date') }}</Label>
                                        <Input 
                                            id="residence_expiry_date"
                                            v-model="form.residence_expiry_date" 
                                            type="date" 
                                        />
                                        <div v-if="form.errors.residence_expiry_date" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.residence_expiry_date || "") }}</div>
                                    </div>

                                    <!-- Contract End Date -->
                                    <div>
                                        <Label for="contract_end_date" class="mb-2">{{ t('employees.contract_end_date') }}</Label>
                                        <Input 
                                            id="contract_end_date"
                                            v-model="form.contract_end_date" 
                                            type="date" 
                                        />
                                        <div v-if="form.errors.contract_end_date" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.contract_end_date || "") }}</div>
                                    </div>

                                    <!-- Exit Re-entry Visa Expiry -->
                                    <div>
                                        <Label for="exit_reentry_visa_expiry" class="mb-2">{{ t('employees.exit_reentry_visa_expiry') }}</Label>
                                        <Input 
                                            id="exit_reentry_visa_expiry"
                                            v-model="form.exit_reentry_visa_expiry" 
                                            type="date" 
                                        />
                                        <div v-if="form.errors.exit_reentry_visa_expiry" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.exit_reentry_visa_expiry || "") }}</div>
                                    </div>

                                    <!-- Passport Number -->
                                    <div>
                                        <Label for="passport_number" class="mb-2">{{ t('employees.passport_number') }}</Label>
                                        <Input 
                                            id="passport_number"
                                            v-model="form.passport_number" 
                                            type="text" 
                                            placeholder="Passport number"
                                        />
                                        <div v-if="form.errors.passport_number" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.passport_number || "") }}</div>
                                    </div>

                                    <!-- Passport Expiry Date -->
                                    <div>
                                        <Label for="passport_expiry_date" class="mb-2">{{ t('employees.passport_expiry_date') }}</Label>
                                        <Input 
                                            id="passport_expiry_date"
                                            v-model="form.passport_expiry_date" 
                                            type="date" 
                                        />
                                        <div v-if="form.errors.passport_expiry_date" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.passport_expiry_date || "") }}</div>
                                    </div>
                                </div>
                            </CardContent>
                        </CollapsibleContent>
                    </Collapsible>
                </Card>

                <!-- Insurance Section -->
                <Card>
                    <Collapsible v-model:open="sectionInsurance">
                        <CollapsibleTrigger asChild>
                            <CardHeader class="cursor-pointer hover:bg-gray-50">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <Icon name="Shield" class="h-5 w-5 text-purple-600" />
                                        <CardTitle class="text-xl">{{ t('employees.insurance') }}</CardTitle>
                                        <Badge v-if="completedSections.insurance" variant="default">✓</Badge>
                                    </div>
                                    <Icon :name="!sectionInsurance ? 'ChevronRight' : 'ChevronDown'" class="h-5 w-5" />
                                </div>
                            </CardHeader>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <CardContent class="space-y-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Insurance Policy -->
                                    <div>
                                        <Label for="insurance_policy" class="mb-2">{{ t('employees.insurance_policy') }}</Label>
                                        <Input 
                                            id="insurance_policy"
                                            v-model="form.insurance_policy" 
                                            type="text" 
                                            placeholder="Insurance policy number"
                                        />
                                        <div v-if="form.errors.insurance_policy" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.insurance_policy || "") }}</div>
                                    </div>

                                    <!-- Insurance Expiry Date -->
                                    <div>
                                        <Label for="insurance_expiry_date" class="mb-2">{{ t('employees.insurance_expiry_date') }}</Label>
                                        <Input 
                                            id="insurance_expiry_date"
                                            v-model="form.insurance_expiry_date" 
                                            type="date" 
                                        />
                                        <div v-if="form.errors.insurance_expiry_date" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.insurance_expiry_date || "") }}</div>
                                    </div>
                                </div>
                            </CardContent>
                        </CollapsibleContent>
                    </Collapsible>
                </Card>

                <!-- Employment Status Section -->
                <Card>
                    <Collapsible v-model:open="sectionEmployment">
                        <CollapsibleTrigger asChild>
                            <CardHeader class="cursor-pointer hover:bg-gray-50">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <Icon name="Calendar" class="h-5 w-5 text-indigo-600" />
                                        <CardTitle class="text-xl">{{ t('employees.employment_status') }}</CardTitle>
                                        <Badge v-if="completedSections.employment" variant="default">✓</Badge>
                                    </div>
                                    <Icon :name="!sectionEmployment ? 'ChevronRight' : 'ChevronDown'" class="h-5 w-5" />
                                </div>
                            </CardHeader>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <CardContent class="space-y-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                    <!-- Hire Date -->
                                    <div>
                                        <Label for="hire_date" class="mb-2">{{ t('employees.hire_date') }}</Label>
                                        <Input 
                                            id="hire_date"
                                            v-model="form.hire_date" 
                                            type="date" 
                                        />
                                        <div v-if="form.errors.hire_date" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.hire_date || "") }}</div>
                                    </div>

                                    <!-- Employment Status -->
                                    <div>
                                        <Label for="employment_status" class="mb-2">{{ t('employees.employment_status') }}</Label>
                                        <select 
                                            id="employment_status"
                                            v-model="form.employment_status"
                                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            <option v-for="status in employmentStatuses" :key="status.value" :value="status.value">
                                                {{ status.label }}
                                            </option>
                                        </select>
                                        <div v-if="form.errors.employment_status" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.employment_status || "") }}</div>
                                    </div>

                                    <!-- Termination Date -->
                                    <div v-if="form.employment_status === 'terminated'">
                                        <Label for="termination_date" class="mb-2">{{ t('employees.termination_date') }}</Label>
                                        <Input 
                                            id="termination_date"
                                            v-model="form.termination_date" 
                                            type="date" 
                                        />
                                        <div v-if="form.errors.termination_date" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.termination_date || "") }}</div>
                                    </div>

                                    <!-- Departure Date -->
                                    <div>
                                        <Label for="departure_date" class="mb-2">{{ t('employees.departure_date') }}</Label>
                                        <Input 
                                            id="departure_date"
                                            v-model="form.departure_date" 
                                            type="date" 
                                        />
                                        <div v-if="form.errors.departure_date" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.departure_date || "") }}</div>
                                    </div>

                                    <!-- Departure Reason -->
                                    <div class="md:col-span-2">
                                        <Label for="departure_reason" class="mb-2">{{ t('employees.departure_reason') }}</Label>
                                        <Input 
                                            id="departure_reason"
                                            v-model="form.departure_reason" 
                                            type="text" 
                                            placeholder="Reason for departure"
                                        />
                                        <div v-if="form.errors.departure_reason" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.departure_reason || "") }}</div>
                                    </div>
                                </div>
                            </CardContent>
                        </CollapsibleContent>
                    </Collapsible>
                </Card>

                <!-- Managers / Workflow Section -->
                <Card>
                    <Collapsible v-model:open="sectionManagers">
                        <CollapsibleTrigger asChild>
                            <CardHeader class="cursor-pointer hover:bg-gray-50">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <Icon name="Users" class="h-5 w-5 text-cyan-600" />
                                        <CardTitle class="text-xl">{{ t('employees.managers_workflow') }}</CardTitle>
                                        <Badge v-if="completedSections.managers" variant="default">✓</Badge>
                                    </div>
                                    <Icon :name="!sectionManagers ? 'ChevronRight' : 'ChevronDown'" class="h-5 w-5" />
                                </div>
                            </CardHeader>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <CardContent class="space-y-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Manager -->
                                    <div>
                                        <Label for="manager" class="mb-2">{{ t('employees.manager') }}</Label>
                                        <Input 
                                            id="manager"
                                            v-model="form.manager" 
                                            type="text" 
                                            :placeholder="t('employees.manager_placeholder')"
                                        />
                                        <div v-if="form.errors.manager" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.manager || "") }}</div>
                                    </div>

                                    <!-- Direct Manager -->
                                    <div>
                                        <Label for="direct_manager" class="mb-2">{{ t('employees.direct_manager') }}</Label>
                                        <Input 
                                            id="direct_manager"
                                            v-model="form.direct_manager" 
                                            type="text" 
                                            placeholder="Direct manager name"
                                        />
                                        <div v-if="form.errors.direct_manager" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.direct_manager || "") }}</div>
                                    </div>

                                    <!-- Additional Approver 2 -->
                                    <div>
                                        <Label for="additional_approver_2" class="mb-2">{{ t('employees.additional_approver_2') }}</Label>
                                        <Input 
                                            id="additional_approver_2"
                                            v-model="form.additional_approver_2" 
                                            type="text" 
                                            placeholder="Second approver name"
                                        />
                                        <div v-if="form.errors.additional_approver_2" class="text-red-500 text-sm mt-1">{{ form.errors.additional_approver_2 }}</div>
                                    </div>

                                    <!-- Additional Approver 3 -->
                                    <div>
                                        <Label for="additional_approver_3" class="mb-2">{{ t('employees.additional_approver_3') }}</Label>
                                        <Input 
                                            id="additional_approver_3"
                                            v-model="form.additional_approver_3" 
                                            type="text" 
                                            placeholder="Third approver name"
                                        />
                                        <div v-if="form.errors.additional_approver_3" class="text-red-500 text-sm mt-1">{{ form.errors.additional_approver_3 }}</div>
                                    </div>
                                </div>
                            </CardContent>
                        </CollapsibleContent>
                    </Collapsible>
                </Card>

                <!-- Emergency Contact Section -->
                <Card>
                    <Collapsible v-model:open="sectionEmergency">
                        <CollapsibleTrigger asChild>
                            <CardHeader class="cursor-pointer hover:bg-gray-50">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <Icon name="Phone" class="h-5 w-5 text-red-600" />
                                        <CardTitle class="text-xl">{{ t('employees.emergency_contact') }}</CardTitle>
                                        <Badge v-if="completedSections.emergency" variant="default">✓</Badge>
                                    </div>
                                    <Icon :name="!sectionEmergency ? 'ChevronRight' : 'ChevronDown'" class="h-5 w-5" />
                                </div>
                            </CardHeader>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <CardContent class="space-y-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Emergency Contact Name -->
                                    <div>
                                        <Label for="emergency_contact_name" class="mb-2">{{ t('employees.emergency_contact_name') }}</Label>
                                        <Input 
                                            id="emergency_contact_name"
                                            v-model="form.emergency_contact_name" 
                                            type="text" 
                                            placeholder="Emergency contact name"
                                        />
                                        <div v-if="form.errors.emergency_contact_name" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.emergency_contact_name || "") }}</div>
                                    </div>

                                    <!-- Emergency Contact Phone -->
                                    <div>
                                        <Label for="emergency_contact_phone" class="mb-2">{{ t('employees.emergency_contact_phone') }}</Label>
                                        <Input 
                                            id="emergency_contact_phone"
                                            v-model="form.emergency_contact_phone" 
                                            type="text" 
                                            placeholder="Emergency contact phone"
                                        />
                                        <div v-if="form.errors.emergency_contact_phone" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.emergency_contact_phone || "") }}</div>
                                    </div>

                                    <!-- Emergency Contact Email -->
                                    <div>
                                        <Label for="emergency_contact_email" class="mb-2">{{ t('employees.emergency_contact_email') }}</Label>
                                        <Input 
                                            id="emergency_contact_email"
                                            v-model="form.emergency_contact_email" 
                                            type="email" 
                                            placeholder="emergency@contact.com"
                                        />
                                        <div v-if="form.errors.emergency_contact_email" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.emergency_contact_email || "") }}</div>
                                    </div>

                                    <!-- Emergency Contact Address -->
                                    <div>
                                        <Label for="emergency_contact_address" class="mb-2">{{ t('employees.emergency_contact_address') }}</Label>
                                        <textarea 
                                            id="emergency_contact_address"
                                            v-model="form.emergency_contact_address" 
                                            rows="3"
                                            placeholder="Emergency contact address"
                                            class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                        ></textarea>
                                        <div v-if="form.errors.emergency_contact_address" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.emergency_contact_address || "") }}</div>
                                    </div>
                                </div>
                            </CardContent>
                        </CollapsibleContent>
                    </Collapsible>
                </Card>

                <!-- Additional Information Section -->
                <Card>
                    <Collapsible v-model:open="sectionAdditional">
                        <CollapsibleTrigger asChild>
                            <CardHeader class="cursor-pointer hover:bg-gray-50">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <Icon name="FileText" class="h-5 w-5 text-gray-600" />
                                        <CardTitle class="text-xl">{{ t('employees.additional_information') }}</CardTitle>
                                        <Badge v-if="completedSections.additional" variant="default">✓</Badge>
                                    </div>
                                    <Icon :name="!sectionAdditional ? 'ChevronRight' : 'ChevronDown'" class="h-5 w-5" />
                                </div>
                            </CardHeader>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <CardContent class="space-y-6">
                                <div>
                                    <Label for="notes" class="mb-2">{{ t('employees.notes') }}</Label>
                                    <textarea 
                                        id="notes"
                                        v-model="form.notes" 
                                        rows="4"
                                        :placeholder="t('employees.notes_placeholder')"
                                        class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                    ></textarea>
                                    <div v-if="form.errors.notes" class="text-red-500 text-sm mt-1">{{ translateValidationError(form.errors.notes || "") }}</div>
                                </div>
                            </CardContent>
                        </CollapsibleContent>
                    </Collapsible>
                </Card>

                <!-- Debts Section -->
                <Card>
                    <Collapsible v-model:open="sectionDebts">
                        <CollapsibleTrigger asChild>
                            <CardHeader class="cursor-pointer hover:bg-gray-50">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <Icon name="CreditCard" class="h-5 w-5 text-purple-600" />
                                        <CardTitle class="text-xl">{{ t('debts.title') }}</CardTitle>
                                        <Badge v-if="debts.length > 0" variant="default">{{ debts.length }}</Badge>
                                    </div>
                                    <Icon :name="!sectionDebts ? 'ChevronRight' : 'ChevronDown'" class="h-5 w-5" />
                                </div>
                            </CardHeader>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <CardContent class="space-y-6 pt-6">
                                <!-- Debts List -->
                                <div v-if="debts.length > 0" class="space-y-3 mb-6">
                                    <div 
                                        v-for="(debt, index) in debts" 
                                        :key="debt.id || index"
                                        class="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50 transition-colors"
                                    >
                                        <div class="flex-1">
                                            <div class="font-medium text-lg mb-1">{{ formatCurrency(debt.amount) }}</div>
                                            <div v-if="debt.debt_type" class="text-sm text-gray-500">{{ debt.debt_type }}</div>
                                        </div>
                                        <div class="flex gap-2">
                                            <Button 
                                                type="button"
                                                variant="ghost" 
                                                size="sm"
                                                @click="editDebt(index)"
                                                class="h-8 w-8 p-0"
                                            >
                                                <Icon name="SquarePen" class="h-4 w-4" />
                                            </Button>
                                            <Button 
                                                type="button"
                                                variant="ghost" 
                                                size="sm"
                                                @click="removeDebt(index)"
                                                class="h-8 w-8 p-0"
                                            >
                                                <Icon name="Trash2" class="h-4 w-4 text-red-600" />
                                            </Button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Add/Edit Debt Form -->
                                <div v-if="showAddDebtForm" class="space-y-4 p-5 border rounded-lg bg-gray-50 mt-4">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div class="space-y-2">
                                            <Label for="debt_amount" class="text-sm font-medium">{{ t('debts.debt_amount') }}</Label>
                                            <Input 
                                                id="debt_amount"
                                                v-model.number="newDebt.amount" 
                                                type="number"
                                                step="0.01"
                                                min="0.01"
                                                :placeholder="t('debts.debt_amount')"
                                                class="mt-1"
                                            />
                                        </div>
                                        <div class="space-y-2">
                                            <Label for="debt_type" class="text-sm font-medium">
                                                {{ t('debts.debt_type') }} 
                                                <span class="text-gray-400 text-xs">({{ t('common.optional') }})</span>
                                            </Label>
                                            <Input 
                                                id="debt_type"
                                                v-model="newDebt.debt_type" 
                                                type="text"
                                                :placeholder="t('debts.debt_type')"
                                                class="mt-1"
                                            />
                                        </div>
                                    </div>
                                    <div class="flex gap-2 pt-2">
                                        <Button 
                                            type="button" 
                                            @click="editingDebtIndex !== null ? updateDebt() : addDebt()" 
                                            :disabled="!newDebt.amount || newDebt.amount <= 0"
                                        >
                                            {{ editingDebtIndex !== null ? t('common.save') : t('common.add') }}
                                        </Button>
                                        <Button type="button" variant="outline" @click="cancelDebtForm">
                                            {{ t('common.cancel') }}
                                        </Button>
                                    </div>
                                </div>

                                <!-- Add Debt Button -->
                                <div v-else class="pt-2">
                                    <Button type="button" variant="outline" @click="showAddDebtForm = true" class="w-full md:w-auto">
                                        <Icon name="Plus" class="h-4 w-4 mr-2" />
                                        {{ t('debts.add_debt') }}
                                    </Button>
                                </div>

                                <p v-if="debts.length === 0 && !showAddDebtForm" class="text-sm text-gray-500 text-center py-4">
                                    {{ t('debts.no_debts') }}
                                </p>
                            </CardContent>
                        </CollapsibleContent>
                    </Collapsible>
                </Card>

                <!-- Form Actions -->
                <div class="flex justify-end gap-4 pt-6 border-t border-gray-200">
                    <Button variant="outline" asChild>
                        <Link :href="`/employees/${employee.id}`">{{ t('common.cancel') }}</Link>
                    </Button>
                    <Button 
                        type="submit" 
                        :disabled="form.processing"
                        class="min-w-32"
                    >
                        <span v-if="form.processing">{{ t('common.saving') }}</span>
                        <span v-else>{{ t('common.save_changes') }}</span>
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Badge } from '@/components/ui/badge';
import Icon from '@/components/Icon.vue';
import type { Employee, Department, Company, Location, BreadcrumbItem } from '@/types';

const { t } = useI18n();
const page = usePage();

interface Country {
    id: number
    name: string
    code: string
}

interface Nationality {
    id: number
    name: string
    code: string
}

interface Props {
    employee: Employee;
    companies: any[];
    defaultCompanyId?: number;
    countries: Country[];
    nationalities: Nationality[];
    defaultResidenceCountryId?: number;
    locations: Location[];
    departments: Department[];
    shifts?: any[];
}

const props = defineProps<Props>();

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
        title: props.employee.first_name + ' ' + props.employee.last_name,
        href: `/employees/${props.employee.id}`,
    },
    {
        title: t('employees.edit_employee'),
        href: `/employees/${props.employee.id}/edit`,
    },
]);

const form = useForm({
    // General Information
    employee_id: props.employee.employee_id || '',
    first_name: props.employee.first_name || '',
    father_name: props.employee.father_name || '',
    last_name: props.employee.last_name || '',
    nationality_id: props.employee.nationality_id || null as number | null,
    residence_country_id: props.employee.residence_country_id || props.defaultResidenceCountryId || null,
    birth_date: props.employee.birth_date || '',
    email: props.employee.email || '',
    personal_email: props.employee.personal_email || '',
    work_email: props.employee.work_email || '',
    phone: props.employee.phone || '',
    location_id: props.employee.location_id || null as number | null,
    
    // Work Details
    company_id: props.employee.company_id || props.defaultCompanyId || null,
    employment_date: props.employee.employment_date || '',
    probation_end_date: props.employee.probation_end_date || '',
    work_phone: props.employee.work_phone || '',
    mobile: props.employee.mobile || '',
    fingerprint_device_id: props.employee.fingerprint_device_id || '',
    shift_id: props.employee.shift_id || null as number | null,
    work_address: props.employee.work_address || '',
    department: props.employee.department || '',
    department_id: props.employee.department_id || null as number | null,
    job_title: props.employee.job_title || '',
    basic_salary: props.employee.basic_salary || '',
    allowances: props.employee.allowances || '',
    annual_leave_balance: props.employee.annual_leave_balance ?? 21,

    // Legal Information
    id_number: props.employee.id_number || '',
    residence_expiry_date: props.employee.residence_expiry_date || '',
    contract_end_date: props.employee.contract_end_date || '',
    exit_reentry_visa_expiry: props.employee.exit_reentry_visa_expiry || '',
    passport_number: props.employee.passport_number || '',
    passport_expiry_date: props.employee.passport_expiry_date || '',
    
    // Insurance
    insurance_policy: props.employee.insurance_policy || '',
    insurance_expiry_date: props.employee.insurance_expiry_date || '',
    
    // Employment Status
    hire_date: props.employee.hire_date || '',
    employment_status: props.employee.employment_status || 'active' as 'active' | 'inactive' | 'terminated',
    termination_date: props.employee.termination_date || '',
    departure_date: props.employee.departure_date || '',
    departure_reason: props.employee.departure_reason || '',
    
    // Managers / Workflow
    manager: props.employee.manager || '',
    direct_manager: props.employee.direct_manager || '',
    additional_approver_2: props.employee.additional_approver_2 || '',
    additional_approver_3: props.employee.additional_approver_3 || '',
    
    // Emergency Contact
    emergency_contact_name: props.employee.emergency_contact_name || '',
    emergency_contact_phone: props.employee.emergency_contact_phone || '',
    emergency_contact_email: props.employee.emergency_contact_email || '',
    emergency_contact_address: props.employee.emergency_contact_address || '',
    
    // Additional Information
    notes: props.employee.notes || '',
})

// Section collapse states
const sectionGeneral = ref(true)
const sectionWork = ref(true)
const sectionLegal = ref(false)
const sectionInsurance = ref(false)
const sectionEmployment = ref(false)
const sectionManagers = ref(false)
const sectionEmergency = ref(false)
const sectionAdditional = ref(false)
const sectionDebts = ref(false)

// Debts management
interface Debt {
    id?: number
    amount: number
    debt_type: string
}

const debts = ref<Debt[]>((props.employee.debts || []).map((debt: any) => ({
    id: debt.id,
    amount: parseFloat(debt.amount),
    debt_type: debt.debt_type || '',
})))
const editingDebtIndex = ref<number | null>(null)
const newDebt = ref<Debt>({ amount: 0, debt_type: '' })
const showAddDebtForm = ref(false)

const addDebt = () => {
    if (newDebt.value.amount > 0) {
        router.post(`/employees/${props.employee.id}/debts`, {
            amount: newDebt.value.amount,
            debt_type: newDebt.value.debt_type || null,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                debts.value.push({ ...newDebt.value })
                newDebt.value = { amount: 0, debt_type: '' }
                showAddDebtForm.value = false
            },
        })
    }
}

const editDebt = (index: number) => {
    editingDebtIndex.value = index
    newDebt.value = { ...debts.value[index] }
    showAddDebtForm.value = true
}

const updateDebt = () => {
    if (editingDebtIndex.value !== null && newDebt.value.amount > 0) {
        const debt = debts.value[editingDebtIndex.value]
        if (debt.id) {
            router.put(`/employees/${props.employee.id}/debts/${debt.id}`, {
                amount: newDebt.value.amount,
                debt_type: newDebt.value.debt_type || null,
            }, {
                preserveScroll: true,
                onSuccess: () => {
                    debts.value[editingDebtIndex.value!] = { ...newDebt.value, id: debt.id }
                    editingDebtIndex.value = null
                    newDebt.value = { amount: 0, debt_type: '' }
                    showAddDebtForm.value = false
                },
            })
        }
    }
}

const removeDebt = (index: number) => {
    const debt = debts.value[index]
    if (debt.id && confirm(t('debts.delete_debt') + '?')) {
        router.delete(`/employees/${props.employee.id}/debts/${debt.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                debts.value.splice(index, 1)
            },
        })
    } else if (!debt.id) {
        debts.value.splice(index, 1)
    }
}

const cancelDebtForm = () => {
    editingDebtIndex.value = null
    newDebt.value = { amount: 0, debt_type: '' }
    showAddDebtForm.value = false
}

const formatCurrency = (amount: number) => {
    return amount.toFixed(2) + ' SAR'
}

// Department search state
const departmentSearchQuery = ref(props.employee.department || '')
const departmentSearchResults = ref<Department[]>([])
const departmentSearchLoading = ref(false)
const showDepartmentDropdown = ref(false)
const selectedDepartment = ref<Department | null>(null)

// Initialize selected department if employee has one
if (props.employee.department_id) {
    const existingDept = props.departments.find(dept => dept.id === props.employee.department_id)
    if (existingDept) {
        selectedDepartment.value = existingDept
    }
}

// Form completion tracking
const completedSections = computed(() => {
    const sections = {
        general: form.first_name && form.last_name && form.email,
        work: form.job_title || form.department || form.employment_date,
        legal: form.id_number || form.passport_number,
        insurance: form.insurance_policy,
        employment: form.hire_date || form.employment_status,
        managers: form.manager || form.direct_manager,
        emergency: form.emergency_contact_name,
        additional: form.notes
    }
    
    return sections
})

// Debounced department search
let departmentSearchTimeout: ReturnType<typeof setTimeout> | null = null
const searchDepartments = (query: string) => {
    if (departmentSearchTimeout) {
        clearTimeout(departmentSearchTimeout)
    }
    
    departmentSearchTimeout = setTimeout(async () => {
        if (query.length < 2) {
            departmentSearchResults.value = []
            return
        }
        
        departmentSearchLoading.value = true
        
        try {
            const companyParam = form.company_id ? `&company_id=${form.company_id}` : ''
            const response = await fetch(`/api/departments/search?q=${encodeURIComponent(query)}${companyParam}`)
            if (response.ok) {
                departmentSearchResults.value = await response.json()
            }
        } catch (error) {
            console.error('Error searching departments:', error)
        } finally {
            departmentSearchLoading.value = false
        }
    }, 300)
}

// Watch for department search changes
watch(departmentSearchQuery, (newQuery) => {
    if (newQuery.length >= 2) {
        showDepartmentDropdown.value = true
        searchDepartments(newQuery)
    } else {
        showDepartmentDropdown.value = false
        departmentSearchResults.value = []
    }
})

// Watch for company changes to clear department selection
watch(() => form.company_id, (newCompanyId, oldCompanyId) => {
    // Clear department when company changes (but not on initial load)
    if (oldCompanyId !== undefined && newCompanyId !== oldCompanyId) {
        clearDepartment()
    }
    // Clear department results when no company is selected
    if (!newCompanyId) {
        departmentSearchResults.value = []
    }
})

const selectDepartment = (department: Department) => {
    selectedDepartment.value = department
    form.department_id = department.id
    departmentSearchQuery.value = department.name
    showDepartmentDropdown.value = false
}

const clearDepartment = () => {
    selectedDepartment.value = null
    form.department_id = null
    departmentSearchQuery.value = ''
    departmentSearchResults.value = []
    showDepartmentDropdown.value = false
}

const handleDepartmentBlur = () => {
    setTimeout(() => {
        showDepartmentDropdown.value = false
    }, 200)
}

const submit = () => {
    form.put(`/employees/${props.employee.id}`)
}

const translateValidationError = (error: string) => {
    // Handle empty or undefined errors
    if (!error || error.trim() === '') {
        return t('validation.required')
    }
    
    // First, check for the most common Laravel validation patterns
    const lowerError = error.toLowerCase().trim()
    
    // Handle unique constraint violations first (most specific)
    // Check for various patterns that indicate uniqueness violations
    if (lowerError.includes('has already been taken') || 
        lowerError.includes('already exists') ||
        lowerError.includes('already been taken') ||
        lowerError.includes('duplicate') ||
        lowerError.includes('unique') ||
        lowerError.includes('not unique') ||
        lowerError.includes('already in use') ||
        lowerError.includes('already registered') ||
        lowerError.includes('email already exists') ||
        lowerError.includes('must be unique') ||
        lowerError.includes('is not unique') ||
        lowerError.includes('constraint') ||
        lowerError.includes('integrity') ||
        // Sometimes Laravel returns database constraint messages
        lowerError.includes('duplicate entry') ||
        lowerError.includes('duplicate key') ||
        lowerError.includes('violates unique constraint') ||
        // Check if it's specifically about email uniqueness
        (lowerError.includes('email') && (lowerError.includes('taken') || lowerError.includes('exists') || lowerError.includes('duplicate')))) {
        return t('validation.unique')
    }
    
    // Handle required field errors
    if (lowerError.includes('required') || 
        lowerError.includes('is required') || 
        lowerError.includes('field is required') ||
        lowerError.includes('this field is required') ||
        lowerError.includes('cannot be empty') ||
        lowerError.includes('must not be empty')) {
        return t('validation.required')
    }
    
    // Handle email validation errors
    if (lowerError.includes('email') && 
        (lowerError.includes('valid') || lowerError.includes('format') || lowerError.includes('invalid'))) {
        return t('validation.email')
    }
    
    // Handle numeric validation errors
    if (lowerError.includes('must be a number') || 
        lowerError.includes('numeric') ||
        lowerError.includes('must be numeric')) {
        return t('validation.numeric')
    }
    
    // Handle string validation errors
    if (lowerError.includes('must be a string') || 
        lowerError.includes('string')) {
        return t('validation.string')
    }
    
    // Handle date validation errors
    if (lowerError.includes('must be a date') || 
        lowerError.includes('valid date') ||
        lowerError.includes('date format')) {
        return t('validation.date')
    }
    
    // Handle integer validation errors
    if (lowerError.includes('must be an integer') || 
        lowerError.includes('integer')) {
        return t('validation.integer')
    }
    
    // Handle boolean validation errors
    if (lowerError.includes('must be a boolean') || 
        lowerError.includes('boolean') ||
        lowerError.includes('true or false')) {
        return t('validation.boolean')
    }
    
    // Handle minimum length errors
    if (lowerError.includes('minimum') || 
        lowerError.includes('at least') ||
        (lowerError.includes('min') && lowerError.includes('character'))) {
        return t('validation.min')
    }
    
    // Handle maximum length errors
    if (lowerError.includes('maximum') || 
        lowerError.includes('no more than') ||
        (lowerError.includes('max') && lowerError.includes('character'))) {
        return t('validation.max')
    }
    
    // Handle file upload errors
    if (lowerError.includes('must be an image') || 
        lowerError.includes('image')) {
        return t('validation.image')
    }
    
    if (lowerError.includes('must be a file') || 
        lowerError.includes('file')) {
        return t('validation.file')
    }
    
    // Handle URL validation errors
    if (lowerError.includes('must be a valid url') || 
        lowerError.includes('url') ||
        lowerError.includes('valid url')) {
        return t('validation.url')
    }
    
    // Handle confirmed field errors (password confirmation, etc.)
    if (lowerError.includes('confirmation') || 
        lowerError.includes('confirmed') ||
        lowerError.includes('does not match')) {
        return t('validation.confirmed')
    }
    
    // Handle selection/exists errors
    if (lowerError.includes('selected') && lowerError.includes('invalid') || 
        lowerError.includes('does not exist') ||
        lowerError.includes('invalid selection')) {
        return t('validation.exists')
    }
    
    // Handle accepted field errors (terms, privacy, etc.)
    if (lowerError.includes('must be accepted') || 
        lowerError.includes('accepted')) {
        return t('validation.accepted')
    }
    
    // Handle array validation errors
    if (lowerError.includes('must be an array') || 
        lowerError.includes('array')) {
        return t('validation.array')
    }
    
    // Handle phone number validation errors
    if (lowerError.includes('phone') || 
        lowerError.includes('telephone')) {
        return t('validation.phone')
    }
    
    // Handle password validation errors
    if (lowerError.includes('password')) {
        return t('validation.password')
    }
    
    // Handle file upload errors
    if (lowerError.includes('uploaded') || 
        lowerError.includes('upload failed')) {
        return t('validation.uploaded')
    }
    
    // Handle between validation errors
    if (lowerError.includes('between')) {
        return t('validation.between')
    }
    
    // Handle size validation errors
    if (lowerError.includes('size')) {
        return t('validation.size')
    }
    
    // Handle regex/format validation errors
    if (lowerError.includes('format') || 
        lowerError.includes('regex') ||
        lowerError.includes('pattern')) {
        return t('validation.regex')
    }
    
    // Handle alpha validation errors
    if (lowerError.includes('only contain letters') || 
        (lowerError.includes('alpha') && !lowerError.includes('numeric'))) {
        return t('validation.alpha')
    }
    
    // Handle alphanumeric validation errors
    if (lowerError.includes('letters and numbers') || 
        lowerError.includes('alphanumeric') ||
        lowerError.includes('alpha_num')) {
        return t('validation.alpha_num')
    }
    
    // Handle JSON validation errors
    if (lowerError.includes('json')) {
        return t('validation.json')
    }
    
    // Handle MIME type validation errors
    if (lowerError.includes('type') && lowerError.includes('file') || 
        lowerError.includes('mime')) {
        return t('validation.mimes')
    }
    
    // Handle digits validation errors
    if (lowerError.includes('digits')) {
        return t('validation.digits')
    }
    
    // Handle before/after date validation errors
    if (lowerError.includes('before')) {
        return t('validation.before')
    }
    
    if (lowerError.includes('after')) {
        return t('validation.after')
    }
    
    // Handle timezone validation errors
    if (lowerError.includes('timezone')) {
        return t('validation.timezone')
    }
    
    // Handle IP address validation errors
    if (lowerError.includes('ip')) {
        return t('validation.ip')
    }
    
    // Handle UUID validation errors
    if (lowerError.includes('uuid')) {
        return t('validation.uuid')
    }
    
    // If no pattern matches, return the original error
    return error
}

const employmentStatuses = [
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
    { value: 'terminated', label: 'Terminated' }
]
</script> 
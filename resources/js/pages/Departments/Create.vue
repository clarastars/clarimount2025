<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

interface Company {
    id: number
    name_en: string
    name_ar: string
    company_email: string
}

interface Props {
    companies: Company[]
}

const props = defineProps<Props>()

const form = useForm({
    name: '',
    code: '',
    company_id: props.companies.length === 1 ? props.companies[0].id : '' as number | '',
    description: '',
})

const submit = () => {
    form.post('/departments')
}
</script>

<template>
    <AppLayout>
        <div class="max-w-2xl mx-auto p-6">
            <h1 class="text-2xl font-bold mb-6">{{ t('departments.create_department') }}</h1>

            <form class="space-y-4" @submit.prevent="submit">
                <div v-if="companies.length === 0" class="bg-yellow-50 border border-yellow-200 rounded-md p-4">
                    <p class="text-yellow-800">{{ t('departments.company_required') }}</p>
                    <a href="/companies/create" class="text-yellow-900 underline font-medium">{{ t('departments.create_company') }}</a>
                </div>

                <div v-if="companies.length > 1">
                    <label class="block text-sm font-medium mb-1">{{ t('departments.company') }} *</label>
                    <select
                        v-model="form.company_id"
                        required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md"
                    >
                        <option value="">{{ t('departments.select_company') }}</option>
                        <option v-for="company in companies" :key="company.id" :value="company.id">
                            {{ company.name_en }}
                        </option>
                    </select>
                    <div v-if="form.errors.company_id" class="text-red-500 text-sm mt-1">{{ form.errors.company_id }}</div>
                </div>

                <div v-else-if="companies.length === 1" class="bg-blue-50 border border-blue-200 rounded-md p-3">
                    <p class="text-blue-800 text-sm">
                        {{ t('departments.department_will_be_created_for') }} <strong>{{ companies[0].name_en }}</strong>
                    </p>
                    <div v-if="form.errors.company_id" class="text-red-500 text-sm mt-1">{{ form.errors.company_id }}</div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">{{ t('departments.name') }} *</label>
                    <input
                        v-model="form.name"
                        type="text"
                        required
                        :placeholder="t('departments.name_placeholder')"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md"
                        :disabled="companies.length === 0"
                    >
                    <div v-if="form.errors.name" class="text-red-500 text-sm mt-1">{{ form.errors.name }}</div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">{{ t('departments.code') }}</label>
                    <input
                        v-model="form.code"
                        type="text"
                        :placeholder="t('departments.code_auto_generated')"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md"
                        :disabled="companies.length === 0"
                    >
                    <div class="text-xs text-gray-500 mt-1">
                        {{ t('departments.code_auto_generated_description') }}
                    </div>
                    <div v-if="form.errors.code" class="text-red-500 text-sm mt-1">{{ form.errors.code }}</div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">{{ t('departments.description') }}</label>
                    <textarea
                        v-model="form.description"
                        rows="3"
                        :placeholder="t('departments.description_placeholder')"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md"
                        :disabled="companies.length === 0"
                    ></textarea>
                    <div v-if="form.errors.description" class="text-red-500 text-sm mt-1">{{ form.errors.description }}</div>
                </div>

                <div class="flex gap-4 pt-4">
                    <a href="/departments" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                        {{ t('departments.cancel') }}
                    </a>
                    <button
                        type="submit"
                        :disabled="form.processing || companies.length === 0 || !form.company_id || !form.name"
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
                    >
                        <span v-if="form.processing">{{ t('departments.creating') }}</span>
                        <span v-else>{{ t('departments.create') }}</span>
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

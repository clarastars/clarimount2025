<script setup lang="ts">
import { useForm, router } from '@inertiajs/vue3'
import { computed } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { useI18n } from 'vue-i18n'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import Icon from '@/components/Icon.vue'
import type { BreadcrumbItem } from '@/types'

const { t } = useI18n()

const breadcrumbs = computed((): BreadcrumbItem[] => [
    { title: t('nav.dashboard'), href: '/dashboard' },
    { title: t('nav.settings'), href: '#' },
    { title: t('nav.shifts'), href: '/shifts' },
    { title: t('shifts.create_shift'), href: '/shifts/create' },
])

const weekdays = [
    { weekday: 0, labelKey: 'shifts.weekday_sun' },
    { weekday: 1, labelKey: 'shifts.weekday_mon' },
    { weekday: 2, labelKey: 'shifts.weekday_tue' },
    { weekday: 3, labelKey: 'shifts.weekday_wed' },
    { weekday: 4, labelKey: 'shifts.weekday_thu' },
    { weekday: 5, labelKey: 'shifts.weekday_fri' },
    { weekday: 6, labelKey: 'shifts.weekday_sat' },
]

const form = useForm({
    name: '',
    start_time: '08:00',
    end_time: '17:00',
    grace_minutes: 0,
    workdays: weekdays.map((w) => ({
        weekday: w.weekday,
        is_workday: true,
        start_time: '',
        end_time: '',
    })),
})

const workdayErrors = computed(() =>
    Object.entries(form.errors).filter(([k]) => k.startsWith('workdays.')),
)

function setWorkday(weekday: number, is_workday: boolean) {
    const idx = form.workdays.findIndex((w: { weekday: number }) => w.weekday === weekday)
    if (idx !== -1) {
        form.workdays[idx].is_workday = is_workday
        if (!is_workday) {
            form.workdays[idx].start_time = ''
            form.workdays[idx].end_time = ''
        }
    }
}

function getWorkday(weekday: number) {
    return form.workdays.find((w: { weekday: number }) => w.weekday === weekday)?.is_workday ?? true
}

function workdayRow(weekday: number) {
    return form.workdays.find((w: { weekday: number }) => w.weekday === weekday)!
}

const submit = () => {
    form.post(route('shifts.store'))
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="py-12">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <Card>
                    <CardHeader>
                        <CardTitle>{{ t('shifts.create_shift') }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submit" class="space-y-6">
                            <div>
                                <Label for="name">{{ t('shifts.name') }} *</Label>
                                <Input id="name" v-model="form.name" class="mt-1 w-full" required :placeholder="t('shifts.name_placeholder')" />
                                <p v-if="form.errors.name" class="text-red-500 text-sm mt-1">{{ form.errors.name }}</p>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <Label for="start_time">{{ t('shifts.start_time') }} *</Label>
                                    <Input id="start_time" v-model="form.start_time" type="time" class="mt-1 w-full" required />
                                    <p v-if="form.errors.start_time" class="text-red-500 text-sm mt-1">{{ form.errors.start_time }}</p>
                                </div>
                                <div>
                                    <Label for="end_time">{{ t('shifts.end_time') }} *</Label>
                                    <Input id="end_time" v-model="form.end_time" type="time" class="mt-1 w-full" required />
                                    <p v-if="form.errors.end_time" class="text-red-500 text-sm mt-1">{{ form.errors.end_time }}</p>
                                </div>
                            </div>
                            <p class="text-xs text-muted-foreground -mt-2">{{ t('shifts.default_times_hint') }}</p>
                            <div>
                                <Label for="grace_minutes">{{ t('shifts.grace_minutes') }}</Label>
                                <Input id="grace_minutes" v-model.number="form.grace_minutes" type="number" min="0" max="120" class="mt-1 w-full" />
                                <p class="text-xs text-muted-foreground mt-1">{{ t('shifts.grace_minutes_hint') }}</p>
                                <p v-if="form.errors.grace_minutes" class="text-red-500 text-sm mt-1">{{ form.errors.grace_minutes }}</p>
                            </div>
                            <div>
                                <Label class="mb-2 block">{{ t('shifts.workdays') }}</Label>
                                <p class="text-xs text-muted-foreground mb-3">{{ t('shifts.custom_times_per_day') }} — {{ t('shifts.custom_times_hint') }}</p>
                                <div class="space-y-3">
                                    <div
                                        v-for="w in weekdays"
                                        :key="w.weekday"
                                        class="rounded-md border border-border p-3 space-y-2"
                                    >
                                        <label class="flex items-center gap-2 cursor-pointer font-medium">
                                            <input
                                                type="checkbox"
                                                :checked="getWorkday(w.weekday)"
                                                @change="setWorkday(w.weekday, ($event.target as HTMLInputElement).checked)"
                                            />
                                            <span class="text-sm">{{ t(w.labelKey) }}</span>
                                        </label>
                                        <div v-if="getWorkday(w.weekday)" class="grid grid-cols-2 gap-3 pl-6 pt-1">
                                            <div>
                                                <Label class="text-xs text-muted-foreground">{{ t('shifts.custom_start') }}</Label>
                                                <Input
                                                    v-model="workdayRow(w.weekday).start_time"
                                                    type="time"
                                                    class="mt-1 w-full"
                                                />
                                            </div>
                                            <div>
                                                <Label class="text-xs text-muted-foreground">{{ t('shifts.custom_end') }}</Label>
                                                <Input
                                                    v-model="workdayRow(w.weekday).end_time"
                                                    type="time"
                                                    class="mt-1 w-full"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <p v-if="form.errors.workdays" class="text-red-500 text-sm mt-1">{{ form.errors.workdays }}</p>
                                <p
                                    v-for="[key, message] in workdayErrors"
                                    :key="key"
                                    class="text-red-500 text-sm mt-1"
                                >
                                    {{ Array.isArray(message) ? message[0] : message }}
                                </p>
                            </div>
                            <div class="flex justify-end gap-4 pt-4">
                                <Button type="button" variant="outline" @click="router.visit(route('shifts.index'))">
                                    {{ t('common.cancel') }}
                                </Button>
                                <Button type="submit" :disabled="form.processing">
                                    <span v-if="form.processing">{{ t('common.creating') }}</span>
                                    <span v-else>{{ t('common.create') }}</span>
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>

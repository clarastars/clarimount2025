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

const props = defineProps<{
    shift: {
        id: number
        name: string
        start_time: string
        end_time: string
        grace_minutes: number
        workdays: { weekday: number; is_workday: boolean }[]
    }
}>()

const breadcrumbs = computed((): BreadcrumbItem[] => [
    { title: t('nav.dashboard'), href: '/dashboard' },
    { title: t('nav.settings'), href: '#' },
    { title: t('nav.shifts'), href: '/shifts' },
    { title: t('shifts.edit_shift'), href: route('shifts.edit', props.shift.id) },
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

function toTimeStr(v: string): string {
    if (!v) return '08:00'
    const s = String(v)
    const tIndex = s.indexOf('T')
    if (tIndex !== -1) return s.substring(tIndex + 1, tIndex + 6)
    return s.length >= 5 ? s.substring(0, 5) : s
}

const form = useForm({
    name: props.shift.name,
    start_time: toTimeStr(props.shift.start_time),
    end_time: toTimeStr(props.shift.end_time),
    grace_minutes: props.shift.grace_minutes ?? 0,
    workdays: weekdays.map((w) => {
        const existing = props.shift.workdays?.find((x: { weekday: number }) => x.weekday === w.weekday)
        return { weekday: w.weekday, is_workday: existing ? existing.is_workday : true }
    }),
})

function setWorkday(weekday: number, is_workday: boolean) {
    const idx = form.workdays.findIndex((w: { weekday: number }) => w.weekday === weekday)
    if (idx !== -1) form.workdays[idx].is_workday = is_workday
}

function getWorkday(weekday: number) {
    return form.workdays.find((w: { weekday: number }) => w.weekday === weekday)?.is_workday ?? true
}

const submit = () => {
    form.put(route('shifts.update', props.shift.id))
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="py-12">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <Card>
                    <CardHeader>
                        <CardTitle>{{ t('shifts.edit_shift') }}</CardTitle>
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
                            <div>
                                <Label for="grace_minutes">{{ t('shifts.grace_minutes') }}</Label>
                                <Input id="grace_minutes" v-model.number="form.grace_minutes" type="number" min="0" max="120" class="mt-1 w-full" />
                                <p class="text-xs text-muted-foreground mt-1">{{ t('shifts.grace_minutes_hint') }}</p>
                                <p v-if="form.errors.grace_minutes" class="text-red-500 text-sm mt-1">{{ form.errors.grace_minutes }}</p>
                            </div>
                            <div>
                                <Label class="mb-2 block">{{ t('shifts.workdays') }}</Label>
                                <div class="flex flex-wrap gap-4">
                                    <label v-for="w in weekdays" :key="w.weekday" class="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            :checked="getWorkday(w.weekday)"
                                            @change="setWorkday(w.weekday, ($event.target as HTMLInputElement).checked)"
                                        />
                                        <span class="text-sm">{{ t(w.labelKey) }}</span>
                                    </label>
                                </div>
                                <p v-if="form.errors.workdays" class="text-red-500 text-sm mt-1">{{ form.errors.workdays }}</p>
                            </div>
                            <div class="flex justify-end gap-4 pt-4">
                                <Button type="button" variant="outline" @click="router.visit(route('shifts.index'))">
                                    {{ t('common.cancel') }}
                                </Button>
                                <Button type="submit" :disabled="form.processing">
                                    <span v-if="form.processing">{{ t('common.saving') }}</span>
                                    <span v-else>{{ t('common.save_changes') }}</span>
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Select } from '@/components/ui/select'
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'

const props = defineProps<{
  /** Current filter value `YYYY-MM` */
  month: string
  /** Inclusive range from backend (operational month when configured), `YYYY-MM-DD` */
  periodStart: string
  periodEnd: string
}>()

const emit = defineEmits<{
  'update:month': [value: string]
}>()

const { locale, t } = useI18n()

function parseYm(m: string): { year: number; month: number } {
  const parts = String(m || '').split('-')
  const y = parseInt(parts[0] ?? '', 10)
  const mo = parseInt(parts[1] ?? '', 10)
  const now = new Date()
  return {
    year: Number.isFinite(y) ? y : now.getFullYear(),
    month: Number.isFinite(mo) && mo >= 1 && mo <= 12 ? mo : now.getMonth() + 1,
  }
}

function applyParsed(y: number, mo: number) {
  filterYear.value = y
  filterMonth.value = mo
}

const initialYm = parseYm(props.month)
const filterYear = ref(initialYm.year)
const filterMonth = ref(initialYm.month)

watch(
  () => props.month,
  (v: string) => {
    const p = parseYm(v)
    if (p.year !== filterYear.value || p.month !== filterMonth.value) {
      applyParsed(p.year, p.month)
    }
  }
)

const monthLabels = computed(() => {
  const loc = String(locale.value || 'en').toLowerCase().startsWith('ar') ? 'ar-SA' : 'en-US'
  return Array.from({ length: 12 }, (_, i) =>
    new Intl.DateTimeFormat(loc, { month: 'long' }).format(new Date(Date.UTC(2000, i, 15)))
  )
})

const currentYear = computed(() => new Date().getFullYear())
const yearOptions = computed(() => {
  const min = Math.min(currentYear.value - 8, filterYear.value - 2)
  const max = Math.max(currentYear.value + 3, filterYear.value + 2)
  const list: number[] = []
  for (let y = min; y <= max; y++) list.push(y)
  return list
})

function toYm(y: number, mo: number): string {
  return `${y}-${String(mo).padStart(2, '0')}`
}

function emitIfChanged(y: number, mo: number) {
  const ym = toYm(y, mo)
  if (ym !== props.month) {
    emit('update:month', ym)
  }
}

function shiftMonth(delta: number) {
  let y = filterYear.value
  let m = filterMonth.value + delta
  while (m < 1) {
    m += 12
    y -= 1
  }
  while (m > 12) {
    m -= 12
    y += 1
  }
  applyParsed(y, m)
  emitIfChanged(y, m)
}

function onMonthSelect(value: string) {
  const mo = parseInt(value, 10)
  if (!Number.isFinite(mo)) return
  applyParsed(filterYear.value, mo)
  emitIfChanged(filterYear.value, mo)
}

function onYearSelect(value: string) {
  const y = parseInt(value, 10)
  if (!Number.isFinite(y)) return
  applyParsed(y, filterMonth.value)
  emitIfChanged(y, filterMonth.value)
}

const periodSummary = computed(() => {
  const loc = String(locale.value || 'en').toLowerCase().startsWith('ar') ? 'ar-SA' : 'en-US'
  const d = new Date(Date.UTC(filterYear.value, filterMonth.value - 1, 1))
  return new Intl.DateTimeFormat(loc, { month: 'long', year: 'numeric' }).format(d)
})

function parseYmdLocal(ymd: string): Date {
  const parts = String(ymd).split('-').map((x) => parseInt(x, 10))
  const y = parts[0] ?? 0
  const m = parts[1] ?? 1
  const d = parts[2] ?? 1
  return new Date(y, m - 1, d)
}

const periodRangeHint = computed(() => {
  const start = String(props.periodStart || '').trim()
  const end = String(props.periodEnd || '').trim()
  if (!start || !end) return ''

  const loc = String(locale.value || 'en').toLowerCase().startsWith('ar') ? 'ar-SA' : 'en-US'
  try {
    const fmt = new Intl.DateTimeFormat(loc, { dateStyle: 'medium' })
    return t('attendance.payroll_period_date_range', {
      from: fmt.format(parseYmdLocal(start)),
      to: fmt.format(parseYmdLocal(end)),
    })
  } catch {
    return ''
  }
})
</script>

<template>
  <div class="space-y-2">
    <Label for="payroll-month-filter">{{ t('attendance.filter_month') }}</Label>
    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
      <div class="flex items-center gap-1 shrink-0">
        <Button
          type="button"
          variant="outline"
          size="icon"
          class="h-10 w-10 shrink-0"
          :title="t('attendance.prev_payroll_month')"
          :aria-label="t('attendance.prev_payroll_month')"
          @click="shiftMonth(-1)"
        >
          <ChevronLeft class="h-4 w-4 rtl:rotate-180" />
        </Button>
        <Button
          type="button"
          variant="outline"
          size="icon"
          class="h-10 w-10 shrink-0"
          :title="t('attendance.next_payroll_month')"
          :aria-label="t('attendance.next_payroll_month')"
          @click="shiftMonth(1)"
        >
          <ChevronRight class="h-4 w-4 rtl:rotate-180" />
        </Button>
      </div>
      <div class="grid grid-cols-2 gap-2 flex-1 min-w-0 sm:max-w-md">
        <Select
          id="payroll-month-filter"
          class="h-10 !text-sm"
          size="lg"
          :model-value="String(filterMonth)"
          @update:model-value="(v) => onMonthSelect(String(v))"
        >
          <option v-for="(label, idx) in monthLabels" :key="idx + 1" :value="String(idx + 1)">
            {{ label }}
          </option>
        </Select>
        <Select
          class="h-10 !text-sm"
          size="lg"
          :model-value="String(filterYear)"
          @update:model-value="(v) => onYearSelect(String(v))"
        >
          <option v-for="y in yearOptions" :key="y" :value="String(y)">
            {{ y }}
          </option>
        </Select>
      </div>
    </div>
    <p class="text-xs text-muted-foreground">{{ periodSummary }}</p>
    <p v-if="periodRangeHint" class="text-xs text-muted-foreground">{{ periodRangeHint }}</p>
  </div>
</template>

<script setup lang="ts">
import { cn } from '@/lib/utils'
import type { HTMLAttributes } from 'vue'

const props = withDefaults(
  defineProps<{
    checked: boolean
    disabled?: boolean
    class?: HTMLAttributes['class']
  }>(),
  {
    disabled: false,
  }
)

const emit = defineEmits<{
  'update:checked': [value: boolean]
}>()

function toggle(): void {
  if (props.disabled) {
    return
  }
  emit('update:checked', !props.checked)
}
</script>

<template>
  <button
    type="button"
    role="switch"
    :aria-checked="checked"
    :disabled="disabled"
    :class="cn(
      'relative inline-flex h-7 w-12 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:cursor-not-allowed disabled:opacity-50',
      checked ? 'bg-blue-600 hover:bg-blue-700' : 'bg-gray-200 hover:bg-gray-300 dark:bg-gray-600 dark:hover:bg-gray-500',
      props.class
    )"
    @click="toggle"
  >
    <span
      :class="cn(
        'pointer-events-none block h-5 w-5 rounded-full bg-white shadow-md ring-0 transition-transform duration-200 ease-in-out',
        checked ? 'translate-x-5 rtl:-translate-x-5' : 'translate-x-0.5 rtl:-translate-x-0.5'
      )"
    />
  </button>
</template>

<script setup lang="ts">
interface Props {
    title: string;
    description?: string;
}

defineProps<Props>();
</script>

<template>
    <div class="app-page-header">
        <div class="min-w-0 flex-1">
            <h1 class="app-page-title">{{ title }}</h1>
            <p v-if="description" class="app-page-description">{{ description }}</p>
        </div>
        <div v-if="$slots.actions" class="flex shrink-0 flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>

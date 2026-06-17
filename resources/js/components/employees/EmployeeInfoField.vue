<script setup lang="ts">
interface Props {
    label: string;
    value: string;
    mono?: boolean;
    dir?: 'ltr' | 'rtl';
    highlight?: boolean;
}

withDefaults(defineProps<Props>(), {
    mono: false,
    highlight: false,
});
</script>

<template>
    <div
        class="flex h-full min-w-0 flex-col rounded-lg border border-transparent p-3 transition-colors hover:border-border/60 hover:bg-muted/30"
    >
        <p class="mb-2 min-h-[2.75rem] text-xs font-medium leading-snug text-muted-foreground text-start">
            {{ label }}
        </p>
        <p
            class="text-sm font-medium text-foreground text-start"
            :class="[mono ? 'font-mono' : '', highlight ? 'text-base font-semibold text-blue-700' : '']"
            :dir="dir"
        >
            {{ value }}
        </p>
    </div>
</template>

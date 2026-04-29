<script setup lang="ts">
import { SidebarInset } from '@/components/ui/sidebar';
import { computed } from 'vue';
import { cn } from '@/lib/utils';

interface Props {
    variant?: 'header' | 'sidebar';
    class?: string;
}

const props = defineProps<Props>();
const className = computed(() => cn(props.class));
const sidebarClassName = computed(() => cn('px-4 pb-6 pt-4 md:px-6 md:pb-6 md:pt-4', props.class));
</script>

<template>
    <SidebarInset v-if="props.variant === 'sidebar'" :class="sidebarClassName">
        <slot />
    </SidebarInset>
    <main v-else class="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-4 rounded-xl" :class="className">
        <slot />
    </main>
</template>

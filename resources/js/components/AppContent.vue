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
const sidebarClassName = computed(() =>
    cn('flex flex-1 flex-col px-4 pb-8 pt-2 md:px-6 md:pb-8 md:pt-3', props.class),
);
</script>

<template>
    <SidebarInset v-if="props.variant === 'sidebar'" :class="sidebarClassName">
        <div class="app-page">
            <slot />
        </div>
    </SidebarInset>
    <main v-else class="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-4 rounded-xl" :class="className">
        <slot />
    </main>
</template>

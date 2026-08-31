<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import type { User } from '@/types';
import { computed } from 'vue';
import { cn } from '@/lib/utils';

interface Props {
    user: User;
    showEmail?: boolean;
    variant?: 'sidebar' | 'menu';
}

const props = withDefaults(defineProps<Props>(), {
    showEmail: false,
    variant: 'sidebar',
});

const { getInitials } = useInitials();

const showAvatar = computed(() => props.user.avatar && props.user.avatar !== '');
const isMenu = computed(() => props.variant === 'menu');
</script>

<template>
    <Avatar
        :class="cn(
            'size-8 overflow-hidden rounded-lg',
            isMenu ? 'ring-1 ring-border' : 'ring-1 ring-sidebar-border',
        )"
    >
        <AvatarImage v-if="showAvatar" :src="user.avatar!" :alt="user.name" />
        <AvatarFallback
            :class="cn(
                'rounded-lg text-xs font-semibold',
                isMenu
                    ? 'bg-primary text-primary-foreground'
                    : 'bg-sidebar-primary text-sidebar-primary-foreground',
            )"
        >
            {{ getInitials(user.name) }}
        </AvatarFallback>
    </Avatar>

    <div class="grid flex-1 text-start text-sm leading-tight">
        <span
            :class="cn(
                'truncate font-semibold',
                isMenu ? 'text-foreground' : 'text-sidebar-foreground',
            )"
        >
            {{ user.name }}
        </span>
        <span
            v-if="showEmail"
            :class="cn(
                'truncate text-xs',
                isMenu ? 'text-muted-foreground' : 'text-sidebar-foreground/60',
            )"
        >
            {{ user.email }}
        </span>
    </div>
</template>

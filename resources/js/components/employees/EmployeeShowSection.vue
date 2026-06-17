<script setup lang="ts">
import { ref } from 'vue';

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import Icon from '@/components/Icon.vue';

interface Props {
    title: string;
    icon: string;
    iconClass?: string;
    defaultOpen?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    iconClass: 'text-blue-600',
    defaultOpen: false,
});

const open = ref(props.defaultOpen);
</script>

<template>
    <Card class="overflow-hidden border-border/60 shadow-sm">
        <Collapsible v-model:open="open">
            <CollapsibleTrigger as-child>
                <CardHeader class="cursor-pointer transition-colors hover:bg-muted/40">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-muted">
                                <Icon :name="icon" class="h-4 w-4" :class="iconClass" />
                            </div>
                            <CardTitle class="text-base font-semibold">{{ title }}</CardTitle>
                        </div>
                        <Icon :name="open ? 'ChevronDown' : 'ChevronRight'" class="h-5 w-5 text-muted-foreground" />
                    </div>
                </CardHeader>
            </CollapsibleTrigger>
            <CollapsibleContent>
                <CardContent class="border-t border-border/60 pt-5">
                    <slot />
                </CardContent>
            </CollapsibleContent>
        </Collapsible>
    </Card>
</template>

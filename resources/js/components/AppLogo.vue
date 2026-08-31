<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { useI18n } from 'vue-i18n';
import { ref } from 'vue';

const { t } = useI18n();
const logoError = ref(false);
</script>

<template>
    <div class="flex items-center gap-3">
        <div class="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-sidebar-primary shadow-sm">
            <img
                v-if="!logoError"
                src="/ITMAAL.png"
                alt="إعتمال"
                class="size-7 object-contain"
                @error="logoError = true"
            >
            <AppLogoIcon v-else class="size-5 text-sidebar-primary-foreground" />
        </div>
        <div class="grid flex-1 text-start text-sm leading-tight group-data-[collapsible=icon]:hidden">
            <span class="truncate font-bold text-sidebar-foreground">إعتمال</span>
            <span class="truncate text-xs text-sidebar-foreground/60">{{ t('dashboard.work-portal') }}</span>
        </div>
    </div>
</template>

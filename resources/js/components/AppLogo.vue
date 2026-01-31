<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { useI18n } from 'vue-i18n';
const { t } = useI18n();
</script>

<template>
<!--    <div class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">-->
<!--        <AppLogoIcon class="size-5 fill-current text-white dark:text-black" />-->
<!--    </div>-->
    <div class="ml-1 grid flex-1 text-left rtl:text-right text-sm">
        <span class="mb-0.5 truncate leading-tight font-bold text-white">{{ t('dashboard.work-portal') }}</span>
    </div>
</template>

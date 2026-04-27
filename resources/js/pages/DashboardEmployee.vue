<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';
import type { BreadcrumbItem } from '@/types';
import { Card, CardContent } from '@/components/ui/card';
import { Handshake } from 'lucide-vue-next';

const { t } = useI18n();

interface Props {
    employee: {
        id: number;
        first_name: string;
        last_name: string;
        full_name: string;
    };
}

const props = defineProps<Props>();

const breadcrumbs = computed((): BreadcrumbItem[] => []);
</script>

<template>
    <Head :title="t('nav.dashboard')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 items-center justify-center p-6 pt-0">
            <Card class="w-full max-w-2xl border-border/60 shadow-xl bg-gradient-to-b from-background to-muted/30">
                <CardContent class="flex flex-col items-center gap-4 py-12 text-center">
                    <div class="rounded-full bg-primary/10 p-4 text-primary">
                        <Handshake class="h-8 w-8" />
                    </div>
                    <h1 class="text-3xl font-bold tracking-tight text-foreground">
                        {{ t('dashboard.employee_welcome') }}{{ props.employee?.first_name ? `، ${props.employee.first_name}` : '' }}
                    </h1>
                    <p class="text-muted-foreground max-w-xl">
                        {{ t('dashboard.employee_subtitle') }}
                    </p>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

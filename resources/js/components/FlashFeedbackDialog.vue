<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { CheckCircle2, Info, X } from 'lucide-vue-next';

import {
    flashFeedbackState,
    hideFlashFeedback,
    showFlashFeedbackFromProps,
} from '@/lib/flashFeedback';

type FlashProps = {
    success?: string | null;
    info?: string | null;
    error?: string | null;
};

let listenerRegistered = false;

function ensureListener() {
    if (listenerRegistered) {
        return;
    }

    listenerRegistered = true;
    router.on('success', (event) => {
        showFlashFeedbackFromProps(event.detail.page.props.flash as FlashProps | undefined);
    });
}

ensureListener();
onMounted(() => ensureListener());

const { t } = useI18n();

const open = computed(() => flashFeedbackState.open);
const message = computed(() => flashFeedbackState.message);
const variant = computed(() => flashFeedbackState.variant);

const title = computed(() => {
    if (variant.value === 'error') {
        return t('common.error');
    }

    if (variant.value === 'info') {
        return t('common.notice');
    }

    return t('common.success');
});

const panelClass = computed(() => {
    if (variant.value === 'error') {
        return 'border-red-300 bg-red-50 text-red-900 dark:border-red-800 dark:bg-red-950 dark:text-red-100';
    }

    if (variant.value === 'info') {
        return 'border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100';
    }

    return 'border-green-300 bg-green-50 text-green-900 dark:border-green-800 dark:bg-green-950 dark:text-green-100';
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="translate-y-3 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-3 opacity-0"
        >
            <div
                v-if="open"
                class="pointer-events-none fixed inset-x-0 top-4 z-[100] flex justify-center px-4"
                role="status"
                aria-live="polite"
            >
                <div
                    class="pointer-events-auto flex w-full max-w-lg items-start gap-3 rounded-lg border px-4 py-3 shadow-lg"
                    :class="panelClass"
                >
                    <CheckCircle2
                        v-if="variant === 'success'"
                        class="mt-0.5 h-5 w-5 shrink-0 text-green-600 dark:text-green-400"
                    />
                    <Info
                        v-else
                        class="mt-0.5 h-5 w-5 shrink-0"
                        :class="variant === 'error' ? 'text-red-600 dark:text-red-400' : 'text-amber-600 dark:text-amber-400'"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold">{{ title }}</p>
                        <p class="mt-0.5 text-sm leading-relaxed">{{ message }}</p>
                    </div>
                    <button
                        type="button"
                        class="rounded p-1 opacity-70 transition hover:opacity-100"
                        :aria-label="t('common.close')"
                        @click="hideFlashFeedback"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

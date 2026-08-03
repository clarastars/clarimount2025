import { reactive } from 'vue';

export type FeedbackVariant = 'success' | 'info' | 'error';

export const flashFeedbackState = reactive({
    open: false,
    message: '',
    variant: 'success' as FeedbackVariant,
});

let showTimer: ReturnType<typeof setTimeout> | null = null;

function clearShowTimer() {
    if (showTimer) {
        clearTimeout(showTimer);
        showTimer = null;
    }
}

export function hideFlashFeedback() {
    clearShowTimer();
    flashFeedbackState.open = false;
}

export function showFlashFeedback(message: string, variant: FeedbackVariant = 'success') {
    if (!message) {
        return;
    }

    clearShowTimer();
    flashFeedbackState.open = false;
    flashFeedbackState.message = message;
    flashFeedbackState.variant = variant;

    // Wait for any open page dialogs to finish closing first.
    showTimer = setTimeout(() => {
        flashFeedbackState.open = true;
    }, 350);
}

export function showFlashFeedbackFromProps(flash: {
    success?: string | null;
    info?: string | null;
    error?: string | null;
} | null | undefined) {
    if (!flash) {
        return;
    }

    if (flash.success) {
        showFlashFeedback(flash.success, 'success');
        return;
    }

    if (flash.error) {
        showFlashFeedback(flash.error, 'error');
        return;
    }

    if (flash.info) {
        showFlashFeedback(flash.info, 'info');
    }
}

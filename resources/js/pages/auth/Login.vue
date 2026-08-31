<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();

locale.value = 'ar';

interface Props {
    status?: string | null;
    loginStep?: 'email' | 'otp' | 'password';
    loginEmail?: string;
}

const props = withDefaults(defineProps<Props>(), {
    status: null,
    loginStep: 'email',
    loginEmail: '',
});

const step = ref<'email' | 'otp' | 'password'>(props.loginStep);
const email = ref(props.loginEmail);
const currentYear = new Date().getFullYear();

watch(
    () => [props.loginStep, props.loginEmail] as const,
    ([newStep, newEmail]) => {
        step.value = newStep;
        if (newEmail) {
            email.value = newEmail;
        }
    },
);

const identifyForm = useForm({
    email: email.value,
});

const otpForm = useForm({
    email: email.value,
    otp: '',
    remember: false,
});

const passwordForm = useForm({
    email: email.value,
    password: '',
    remember: false,
});

const syncEmail = () => {
    identifyForm.email = email.value;
    otpForm.email = email.value;
    passwordForm.email = email.value;
};

const submitIdentify = () => {
    syncEmail();
    identifyForm.post(route('login.identify'));
};

const submitOtp = () => {
    syncEmail();
    otpForm.post(route('login.verify-otp'));
};

const submitPassword = () => {
    syncEmail();
    passwordForm.post(route('login'));
};

const resendOtp = () => {
    syncEmail();
    router.post(route('login.resend-otp'), { email: email.value });
};

const goBackToEmail = () => {
    step.value = 'email';
    otpForm.reset('otp');
    passwordForm.reset('password');
};

const stepTitle = computed(() => {
    if (step.value === 'otp') {
        return t('auth.otp_step_title');
    }
    if (step.value === 'password') {
        return t('auth.password_step_title');
    }

    return t('auth.welcome');
});

const stepMessage = computed(() => {
    if (step.value === 'otp') {
        return t('auth.otp_step_message');
    }
    if (step.value === 'password') {
        return t('auth.password_step_message');
    }

    return t('auth.welcome_message');
});
</script>

<template>
    <Head :title="t('auth.welcome')" />

    <div class="login-page">
        <div class="w-full max-w-xl">
            <div class="login-card p-10 sm:p-12">
                <div class="mb-8 text-center">
                    <h2 class="text-2xl font-bold text-foreground sm:text-3xl">{{ stepTitle }}</h2>
                    <p class="mt-3 text-base leading-relaxed text-muted-foreground">{{ stepMessage }}</p>
                </div>

                <div
                    v-if="status"
                    class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900 dark:bg-green-950/40 dark:text-green-300"
                >
                    {{ status }}
                </div>

                <form v-if="step === 'email'" class="space-y-5" @submit.prevent="submitIdentify">
                    <div>
                        <label for="email" class="mb-2 block text-sm font-medium text-foreground">
                            {{ t('auth.work_email') }}
                        </label>
                        <input
                            id="email"
                            v-model="email"
                            type="email"
                            dir="ltr"
                            required
                            autocomplete="username"
                            :placeholder="t('auth.work_email_placeholder')"
                            class="w-full rounded-lg border border-input bg-background px-4 py-3.5 text-base text-foreground shadow-xs outline-none transition placeholder:text-muted-foreground focus:border-ring focus:ring-2 focus:ring-ring/30"
                        >
                        <div v-if="identifyForm.errors.email" class="mt-2 text-sm text-destructive">
                            {{ identifyForm.errors.email }}
                        </div>
                    </div>

                    <button
                        type="submit"
                        :disabled="identifyForm.processing"
                        class="flex w-full items-center justify-center rounded-lg bg-primary px-4 py-3.5 text-base font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span v-if="identifyForm.processing">{{ t('auth.continuing') }}</span>
                        <span v-else>{{ t('auth.continue') }}</span>
                    </button>
                </form>

                <form v-else-if="step === 'otp'" class="space-y-5" @submit.prevent="submitOtp">
                    <div class="rounded-lg border border-border bg-muted/50 px-4 py-3 text-center text-sm text-muted-foreground" dir="ltr">
                        {{ email }}
                    </div>

                    <div>
                        <label for="otp" class="mb-2 block text-sm font-medium text-foreground">
                            {{ t('auth.otp_code') }}
                        </label>
                        <input
                            id="otp"
                            v-model="otpForm.otp"
                            type="text"
                            inputmode="numeric"
                            pattern="[0-9]{4}"
                            maxlength="4"
                            dir="ltr"
                            required
                            autocomplete="one-time-code"
                            :placeholder="t('auth.otp_placeholder')"
                            class="w-full rounded-lg border border-input bg-background px-4 py-3 text-center text-2xl tracking-[0.5em] text-foreground shadow-xs outline-none transition placeholder:text-muted-foreground focus:border-ring focus:ring-2 focus:ring-ring/30"
                        >
                        <div v-if="otpForm.errors.otp" class="mt-2 text-sm text-destructive">
                            {{ otpForm.errors.otp }}
                        </div>
                        <div v-if="otpForm.errors.email" class="mt-2 text-sm text-destructive">
                            {{ otpForm.errors.email }}
                        </div>
                    </div>

                    <button
                        type="submit"
                        :disabled="otpForm.processing"
                        class="flex w-full items-center justify-center rounded-lg bg-primary px-4 py-3.5 text-base font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span v-if="otpForm.processing">{{ t('auth.verifying') }}</span>
                        <span v-else>{{ t('auth.verify_otp') }}</span>
                    </button>

                    <div class="flex items-center justify-between gap-3 text-sm">
                        <button
                            type="button"
                            class="text-muted-foreground transition hover:text-foreground"
                            @click="goBackToEmail"
                        >
                            {{ t('auth.change_email') }}
                        </button>
                        <button
                            type="button"
                            class="font-medium text-primary transition hover:text-primary/80"
                            @click="resendOtp"
                        >
                            {{ t('auth.resend_otp') }}
                        </button>
                    </div>
                </form>

                <form v-else class="space-y-5" @submit.prevent="submitPassword">
                    <div class="rounded-lg border border-border bg-muted/50 px-4 py-3 text-center text-sm text-muted-foreground" dir="ltr">
                        {{ email }}
                    </div>

                    <div>
                        <label for="password" class="mb-2 block text-sm font-medium text-foreground">
                            {{ t('auth.password') }}
                        </label>
                        <input
                            id="password"
                            v-model="passwordForm.password"
                            type="password"
                            dir="ltr"
                            required
                            autocomplete="current-password"
                            class="w-full rounded-lg border border-input bg-background px-4 py-3.5 text-base text-foreground shadow-xs outline-none transition placeholder:text-muted-foreground focus:border-ring focus:ring-2 focus:ring-ring/30"
                        >
                        <div v-if="passwordForm.errors.password" class="mt-2 text-sm text-destructive">
                            {{ passwordForm.errors.password }}
                        </div>
                        <div v-if="passwordForm.errors.email" class="mt-2 text-sm text-destructive">
                            {{ passwordForm.errors.email }}
                        </div>
                    </div>

                    <button
                        type="submit"
                        :disabled="passwordForm.processing"
                        class="flex w-full items-center justify-center rounded-lg bg-primary px-4 py-3.5 text-base font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span v-if="passwordForm.processing">{{ t('auth.signing_in') }}</span>
                        <span v-else>{{ t('auth.sign_in') }}</span>
                    </button>

                    <button
                        type="button"
                        class="w-full text-center text-sm text-muted-foreground transition hover:text-foreground"
                        @click="goBackToEmail"
                    >
                        {{ t('auth.change_email') }}
                    </button>
                </form>
            </div>

            <p class="mt-8 text-center text-xs text-muted-foreground">
                © {{ currentYear }} إعتمال — منصة إدارة الموارد البشرية
            </p>
        </div>
    </div>
</template>

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

    <div class="min-h-screen bg-gradient-to-b from-slate-50 to-slate-100 px-4 py-10 sm:px-6 lg:px-8">
        <div class="mx-auto flex min-h-[85vh] w-full max-w-xl items-center justify-center">
            <div class="w-full rounded-2xl border border-slate-200 bg-white/95 p-8 shadow-xl backdrop-blur sm:p-10">
                <div class="mb-8 text-center">
                    <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 text-blue-700 ring-1 ring-blue-200">
                        <span class="text-lg font-bold">HR</span>
                    </div>
                    <h2 class="text-3xl font-bold tracking-tight text-slate-900">
                        {{ stepTitle }}
                    </h2>
                    <p class="mt-2 text-sm text-slate-600">
                        {{ stepMessage }}
                    </p>
                </div>

                <div
                    v-if="status"
                    class="mb-5 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700"
                >
                    {{ status }}
                </div>

                <form v-if="step === 'email'" class="space-y-5" @submit.prevent="submitIdentify">
                    <div>
                        <label for="email" class="mb-2 block text-sm font-medium text-slate-700">
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
                            class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        />
                        <div v-if="identifyForm.errors.email" class="mt-2 text-sm text-red-600">
                            {{ identifyForm.errors.email }}
                        </div>
                    </div>

                    <button
                        type="submit"
                        :disabled="identifyForm.processing"
                        class="mt-2 flex w-full items-center justify-center rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span v-if="identifyForm.processing">{{ t('auth.continuing') }}</span>
                        <span v-else>{{ t('auth.continue') }}</span>
                    </button>
                </form>

                <form v-else-if="step === 'otp'" class="space-y-5" @submit.prevent="submitOtp">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600" dir="ltr">
                        {{ email }}
                    </div>

                    <div>
                        <label for="otp" class="mb-2 block text-sm font-medium text-slate-700">
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
                            class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-center text-2xl tracking-[0.5em] text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        />
                        <div v-if="otpForm.errors.otp" class="mt-2 text-sm text-red-600">
                            {{ otpForm.errors.otp }}
                        </div>
                        <div v-if="otpForm.errors.email" class="mt-2 text-sm text-red-600">
                            {{ otpForm.errors.email }}
                        </div>
                    </div>

                    <button
                        type="submit"
                        :disabled="otpForm.processing"
                        class="flex w-full items-center justify-center rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span v-if="otpForm.processing">{{ t('auth.verifying') }}</span>
                        <span v-else>{{ t('auth.verify_otp') }}</span>
                    </button>

                    <div class="flex items-center justify-between gap-3 text-sm">
                        <button
                            type="button"
                            class="text-slate-600 transition hover:text-slate-900"
                            @click="goBackToEmail"
                        >
                            {{ t('auth.change_email') }}
                        </button>
                        <button
                            type="button"
                            class="font-medium text-blue-600 transition hover:text-blue-700"
                            @click="resendOtp"
                        >
                            {{ t('auth.resend_otp') }}
                        </button>
                    </div>
                </form>

                <form v-else class="space-y-5" @submit.prevent="submitPassword">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600" dir="ltr">
                        {{ email }}
                    </div>

                    <div>
                        <label for="password" class="mb-2 block text-sm font-medium text-slate-700">
                            {{ t('auth.password') }}
                        </label>
                        <input
                            id="password"
                            v-model="passwordForm.password"
                            type="password"
                            dir="ltr"
                            required
                            autocomplete="current-password"
                            class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        />
                        <div v-if="passwordForm.errors.password" class="mt-2 text-sm text-red-600">
                            {{ passwordForm.errors.password }}
                        </div>
                        <div v-if="passwordForm.errors.email" class="mt-2 text-sm text-red-600">
                            {{ passwordForm.errors.email }}
                        </div>
                    </div>

                    <button
                        type="submit"
                        :disabled="passwordForm.processing"
                        class="flex w-full items-center justify-center rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span v-if="passwordForm.processing">{{ t('auth.signing_in') }}</span>
                        <span v-else>{{ t('auth.sign_in') }}</span>
                    </button>

                    <button
                        type="button"
                        class="text-sm text-slate-600 transition hover:text-slate-900"
                        @click="goBackToEmail"
                    >
                        {{ t('auth.change_email') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>

<style scoped>
[dir='rtl'] .text-left {
    text-align: right;
}

[dir='rtl'] .text-right {
    text-align: left;
}
</style>

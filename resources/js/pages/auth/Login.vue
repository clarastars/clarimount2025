<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();

// Set locale to Arabic by default for Welcome page
locale.value = 'ar';

const form = useForm({
    email: '',
    password: '',
});

const submit = () => {
    form.post(route('login'));
};
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
                        {{ t('auth.welcome') }}
                    </h2>
                    <p class="mt-2 text-sm text-slate-600">
                        {{ t('auth.welcome_message') }}
                    </p>
                </div>

                <form @submit.prevent="submit" class="space-y-5">
                    <div>
                        <label for="email" class="mb-2 block text-sm font-medium text-slate-700">
                            {{ t('auth.email') }}
                        </label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            dir="ltr"
                            required
                            autocomplete="username"
                            class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        />
                        <div v-if="form.errors.email" class="mt-2 text-sm text-red-600">
                            {{ form.errors.email }}
                        </div>
                    </div>

                    <div>
                        <label for="password" class="mb-2 block text-sm font-medium text-slate-700">
                            {{ t('auth.password') }}
                        </label>
                        <input
                            id="password"
                            dir="ltr"
                            v-model="form.password"
                            type="password"
                            required
                            autocomplete="current-password"
                            class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        />
                        <div v-if="form.errors.password" class="mt-2 text-sm text-red-600">
                            {{ form.errors.password }}
                        </div>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="mt-2 flex w-full items-center justify-center rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span v-if="form.processing">{{ t('auth.signing_in') }}</span>
                        <span v-else>{{ t('auth.sign_in') }}</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* RTL support for Arabic */
[dir="rtl"] .text-left {
    text-align: right;
}

[dir="rtl"] .text-right {
    text-align: left;
}
</style>

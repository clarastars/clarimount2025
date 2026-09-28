<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Cake, ChevronLeft, EyeOff, PartyPopper, Sparkles, X } from 'lucide-vue-next';
import { fetchWithCsrf } from '@/lib/csrf';

export interface UpcomingBirthday {
    id: number;
    full_name: string;
    company_name?: string | null;
    department_name?: string | null;
    birth_date: string;
    birthday_month_day: string;
    days_until: number;
    is_today: boolean;
    is_self?: boolean;
}

interface ConfettiPiece {
    id: number;
    left: number;
    delay: number;
    duration: number;
    color: string;
    rotate: number;
    size: number;
    shape: 'rect' | 'circle' | 'ribbon';
}

interface EmojiOption {
    key: string;
    emoji: string;
    labelKey: string;
}

const props = withDefaults(
    defineProps<{
        items?: UpcomingBirthday[];
        linkEmployees?: boolean;
    }>(),
    {
        items: () => [],
        linkEmployees: false,
    },
);

const { t, locale } = useI18n();
const open = ref(false);
const confettiBurst = ref(0);
const celebratedId = ref<number | null>(null);
const pickerMode = ref<'all' | 'one' | null>(null);
const pickerEmployeeId = ref<number | null>(null);
const sending = ref(false);
const hiding = ref(false);
const feedback = ref<string | null>(null);
const feedbackOk = ref(true);
const wishMessage = ref('');
const selectedEmoji = ref<string | null>(null);
const localItems = ref<UpcomingBirthday[]>([...props.items]);

watch(
    () => props.items,
    (value) => {
        localItems.value = [...value];
    },
    { deep: true },
);

const emojiOptions: EmojiOption[] = [
    { key: 'cake', emoji: '🎂', labelKey: 'dashboard.birthday_emoji_cake' },
    { key: 'balloon', emoji: '🎈', labelKey: 'dashboard.birthday_emoji_balloon' },
    { key: 'party', emoji: '🎉', labelKey: 'dashboard.birthday_emoji_party' },
    { key: 'gift', emoji: '🎁', labelKey: 'dashboard.birthday_emoji_gift' },
    { key: 'flowers', emoji: '💐', labelKey: 'dashboard.birthday_emoji_flowers' },
    { key: 'sparkles', emoji: '✨', labelKey: 'dashboard.birthday_emoji_sparkles' },
    { key: 'heart', emoji: '❤️', labelKey: 'dashboard.birthday_emoji_heart' },
    { key: 'clap', emoji: '👏', labelKey: 'dashboard.birthday_emoji_clap' },
];

const confettiColors = ['#f43f5e', '#fb7185', '#f59e0b', '#fbbf24', '#ec4899', '#a855f7', '#22d3ee', '#34d399'];

const hasItems = computed(() => localItems.value.length > 0);
const others = computed(() => localItems.value.filter((row) => !row.is_self));
const selfRow = computed(() => localItems.value.find((row) => row.is_self) ?? null);
const todayCount = computed(() => localItems.value.filter((row) => row.is_today || row.days_until === 0).length);
const pickerOpen = computed(() => pickerMode.value !== null);

const noticeTitle = computed(() => {
    if (selfRow.value && others.value.length === 0) {
        return selfRow.value.is_today
            ? t('dashboard.birthday_self_notice_today')
            : t('dashboard.birthday_self_notice', { days: selfRow.value.days_until });
    }

    if (todayCount.value > 0) {
        return t('dashboard.birthday_notice_today', { count: todayCount.value });
    }

    return t('dashboard.birthday_notice', { count: localItems.value.length });
});

const pickerTargetLabel = computed(() => {
    if (pickerMode.value === 'all') {
        return t('dashboard.birthday_wish_all', { count: others.value.length });
    }

    const row = localItems.value.find((item) => item.id === pickerEmployeeId.value);
    return row?.full_name ?? '';
});

const confettiPieces = computed((): ConfettiPiece[] => {
    void confettiBurst.value;

    return Array.from({ length: 36 }, (_, index) => ({
        id: index,
        left: Math.random() * 100,
        delay: Math.random() * 0.8,
        duration: 1.8 + Math.random() * 1.6,
        color: confettiColors[index % confettiColors.length],
        rotate: Math.random() * 360,
        size: 6 + Math.random() * 8,
        shape: (['rect', 'circle', 'ribbon'] as const)[index % 3],
    }));
});

watch(open, (isOpen) => {
    if (isOpen) {
        confettiBurst.value += 1;
        celebratedId.value = null;
        closePicker();
        feedback.value = null;
        wishMessage.value = '';
        selectedEmoji.value = null;
    }
});

const triggerConfetti = (employeeId?: number): void => {
    confettiBurst.value += 1;
    if (employeeId !== undefined) {
        celebratedId.value = employeeId;
        window.setTimeout(() => {
            if (celebratedId.value === employeeId) {
                celebratedId.value = null;
            }
        }, 1200);
    }
};

const openPickerForAll = (): void => {
    if (others.value.length === 0) {
        return;
    }

    pickerMode.value = 'all';
    pickerEmployeeId.value = null;
    feedback.value = null;
    wishMessage.value = '';
    selectedEmoji.value = null;
};

const openPickerForOne = (employeeId: number): void => {
    const row = localItems.value.find((item) => item.id === employeeId);
    if (row?.is_self) {
        return;
    }

    pickerMode.value = 'one';
    pickerEmployeeId.value = employeeId;
    feedback.value = null;
    wishMessage.value = '';
    selectedEmoji.value = null;
};

const closePicker = (): void => {
    pickerMode.value = null;
    pickerEmployeeId.value = null;
    selectedEmoji.value = null;
};

const sendWish = async (emojiKey?: string): Promise<void> => {
    const emoji = emojiKey ?? selectedEmoji.value;
    if (sending.value || pickerMode.value === null || !emoji) {
        return;
    }

    selectedEmoji.value = emoji;

    const body: Record<string, unknown> = {
        emoji,
        message: wishMessage.value.trim() || null,
    };
    if (pickerMode.value === 'all') {
        body.employee_ids = others.value.map((item) => item.id);
    } else if (pickerEmployeeId.value !== null) {
        body.employee_id = pickerEmployeeId.value;
    }

    sending.value = true;
    feedback.value = null;

    try {
        const response = await fetchWithCsrf('/api/birthday-wishes', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(body),
        });

        const payload = (await response.json().catch(() => ({}))) as {
            success?: boolean;
            message?: string;
            sent?: number;
            errors?: Record<string, string[]>;
        };

        if (!response.ok) {
            const firstError = payload.errors
                ? Object.values(payload.errors).flat()[0]
                : null;
            feedbackOk.value = false;
            feedback.value = firstError || payload.message || t('dashboard.birthday_wish_failed');
            return;
        }

        feedbackOk.value = Boolean(payload.success) || (payload.sent ?? 0) > 0 || Boolean(payload.message);
        feedback.value = payload.message || t('dashboard.birthday_wish_sent', { count: payload.sent ?? 1 });

        if ((payload.sent ?? 0) > 0 || payload.success) {
            triggerConfetti(pickerEmployeeId.value ?? undefined);
            wishMessage.value = '';
            window.setTimeout(() => closePicker(), 700);
        }
    } catch {
        feedbackOk.value = false;
        feedback.value = t('dashboard.birthday_wish_failed');
    } finally {
        sending.value = false;
    }
};

const formatBirthdayDate = (value: string): string => {
    try {
        return new Date(`${value}T00:00:00`).toLocaleDateString(locale.value === 'ar' ? 'ar-SA' : 'en-GB', {
            day: 'numeric',
            month: 'long',
        });
    } catch {
        return value;
    }
};

const daysUntilLabel = (row: UpcomingBirthday): string => {
    if (row.is_self) {
        if (row.is_today || row.days_until === 0) {
            return t('dashboard.birthday_self_today');
        }

        if (row.days_until === 1) {
            return t('dashboard.birthday_self_in_one_day');
        }

        return t('dashboard.birthday_self_in_days', { days: row.days_until });
    }

    if (row.is_today || row.days_until === 0) {
        return t('dashboard.birthday_today');
    }

    if (row.days_until === 1) {
        return t('dashboard.birthday_in_one_day');
    }

    return t('dashboard.birthday_in_days', { days: row.days_until });
};

const hideOwnBirthday = async (): Promise<void> => {
    if (hiding.value || !selfRow.value) {
        return;
    }

    hiding.value = true;
    feedback.value = null;

    try {
        const response = await fetchWithCsrf('/api/birthday-privacy/hide', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({}),
        });

        const payload = (await response.json().catch(() => ({}))) as {
            success?: boolean;
            message?: string;
            message_key?: string;
        };

        if (!response.ok || !payload.success) {
            feedbackOk.value = false;
            feedback.value = payload.message || t('dashboard.birthday_hide_failed');
            return;
        }

        localItems.value = localItems.value.filter((row) => !row.is_self);
        feedbackOk.value = true;
        feedback.value = payload.message || t('dashboard.birthday_hidden_success');

        if (localItems.value.length === 0) {
            open.value = false;
        }

        router.reload({
            only: ['upcomingBirthdays'],
            preserveScroll: true,
            preserveState: true,
        });
    } catch {
        feedbackOk.value = false;
        feedback.value = t('dashboard.birthday_hide_failed');
    } finally {
        hiding.value = false;
    }
};

const initials = (name: string): string => {
    const parts = name.trim().split(/\s+/).filter(Boolean);
    if (parts.length === 0) {
        return '?';
    }

    return parts
        .slice(0, 2)
        .map((part) => part.charAt(0))
        .join('');
};
</script>

<template>
    <div v-if="hasItems" class="mb-6">
        <button
            type="button"
            class="group flex w-full items-center gap-3 rounded-xl border border-pink-200 bg-gradient-to-l from-pink-50 via-rose-50 to-amber-50 px-4 py-3 text-start shadow-sm transition hover:border-pink-300 hover:shadow-md dark:border-pink-900/60 dark:from-pink-950/40 dark:via-rose-950/30 dark:to-amber-950/20"
            @click="open = true"
        >
            <div class="relative flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-pink-500 to-rose-600 text-white shadow-sm">
                <Cake class="h-5 w-5" />
                <span
                    class="absolute -end-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-amber-500 px-1 text-[10px] font-bold text-white ring-2 ring-pink-50 dark:ring-pink-950"
                >
                    {{ localItems.length }}
                </span>
            </div>

            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-pink-900 dark:text-pink-100">
                    {{ noticeTitle }}
                </p>
                <p class="truncate text-xs text-pink-700/80 dark:text-pink-200/70">
                    {{ t('dashboard.birthday_notice_hint') }}
                </p>
            </div>

            <ChevronLeft class="h-4 w-4 shrink-0 text-pink-500 opacity-70 transition group-hover:opacity-100 rtl:rotate-180" />
        </button>

        <Dialog v-model:open="open">
            <DialogContent
                class="birthday-modal max-h-[90vh] max-w-lg overflow-hidden border-0 p-0 shadow-2xl sm:max-w-lg"
            >
                <div class="relative overflow-hidden rounded-t-lg bg-gradient-to-b from-rose-500 via-pink-500 to-fuchsia-600 text-white">
                    <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
                        <span class="balloon balloon-a" />
                        <span class="balloon balloon-b" />
                        <span class="balloon balloon-c" />
                        <span class="balloon balloon-d" />
                    </div>

                    <div
                        :key="confettiBurst"
                        class="pointer-events-none absolute inset-0 overflow-hidden"
                        aria-hidden="true"
                    >
                        <span
                            v-for="piece in confettiPieces"
                            :key="`${confettiBurst}-${piece.id}`"
                            class="confetti-piece"
                            :class="`confetti-${piece.shape}`"
                            :style="{
                                left: `${piece.left}%`,
                                width: `${piece.size}px`,
                                height: piece.shape === 'ribbon' ? `${piece.size * 2.2}px` : `${piece.size}px`,
                                backgroundColor: piece.color,
                                animationDelay: `${piece.delay}s`,
                                animationDuration: `${piece.duration}s`,
                                '--spin': `${piece.rotate}deg`,
                            }"
                        />
                    </div>

                    <DialogHeader class="relative z-10 space-y-3 px-6 pb-5 pt-8 text-center sm:text-center">
                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-white/20 shadow-lg ring-4 ring-white/30 backdrop-blur cake-bob">
                            <Cake class="h-8 w-8 text-white" />
                        </div>
                        <DialogTitle class="text-2xl font-bold tracking-tight text-white">
                            {{ t('dashboard.birthday_modal_title') }}
                        </DialogTitle>
                        <DialogDescription class="text-sm text-pink-50/90">
                            {{ t('dashboard.upcoming_birthdays_description') }}
                        </DialogDescription>
                        <Button
                            v-if="others.length > 0"
                            type="button"
                            class="mx-auto mt-1 gap-2 border-0 bg-white/20 text-white hover:bg-white/30"
                            @click="openPickerForAll"
                        >
                            <PartyPopper class="h-4 w-4" />
                            {{ t('dashboard.birthday_celebrate') }}
                        </Button>
                    </DialogHeader>
                </div>

                <div class="relative max-h-[50vh] space-y-3 overflow-y-auto bg-gradient-to-b from-rose-50/80 to-background px-4 py-4 dark:from-pink-950/40">
                    <article
                        v-for="(row, index) in localItems"
                        :key="row.id"
                        class="birthday-card group relative overflow-hidden rounded-2xl border p-4 shadow-sm transition duration-300 hover:-translate-y-0.5 hover:shadow-md"
                        :class="[
                            celebratedId === row.id ? 'ring-2 ring-amber-400 celebrate-pulse' : '',
                            row.is_self
                                ? 'border-amber-200 bg-gradient-to-l from-amber-50/90 to-pink-50/80 dark:border-amber-900/50 dark:from-amber-950/40 dark:to-pink-950/30'
                                : 'border-pink-100 bg-white/90 hover:border-pink-300 dark:border-pink-900/50 dark:bg-zinc-900/80',
                        ]"
                        :style="{ animationDelay: `${index * 80}ms` }"
                    >
                        <div class="pointer-events-none absolute -end-6 -top-6 h-20 w-20 rounded-full bg-gradient-to-br from-pink-200/50 to-amber-200/40 blur-xl transition group-hover:scale-125 dark:from-pink-800/30 dark:to-amber-800/20" />

                        <div class="relative flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex min-w-0 items-start gap-3">
                                <div
                                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full text-sm font-bold text-white shadow-md"
                                    :class="row.is_self
                                        ? 'bg-gradient-to-br from-amber-400 to-pink-500 ring-2 ring-amber-200 ring-offset-2'
                                        : (row.is_today ? 'bg-gradient-to-br from-pink-400 to-rose-500 ring-2 ring-amber-300 ring-offset-2' : 'bg-gradient-to-br from-pink-400 to-rose-500')"
                                >
                                    {{ initials(row.full_name) }}
                                </div>
                                <div class="min-w-0 space-y-1">
                                    <p v-if="row.is_self" class="text-base font-semibold text-foreground">
                                        {{ t('dashboard.birthday_self_title') }}
                                    </p>
                                    <component
                                        v-else
                                        :is="linkEmployees ? Link : 'p'"
                                        v-bind="linkEmployees ? { href: route('employees.show', row.id) } : {}"
                                        class="block truncate text-base font-semibold text-foreground"
                                        :class="linkEmployees ? 'hover:text-pink-600 hover:underline' : ''"
                                    >
                                        {{ row.full_name }}
                                    </component>
                                    <p v-if="row.is_self" class="text-xs text-amber-800/80 dark:text-amber-200/80">
                                        {{ t('dashboard.birthday_self_hint') }}
                                    </p>
                                    <p v-else class="truncate text-xs text-muted-foreground">
                                        <span v-if="row.company_name">{{ row.company_name }}</span>
                                        <span v-if="row.company_name && row.department_name"> — </span>
                                        <span v-if="row.department_name">{{ row.department_name }}</span>
                                    </p>
                                    <p class="text-xs font-medium text-pink-600 dark:text-pink-300">
                                        {{ formatBirthdayDate(row.birth_date) }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex shrink-0 items-center gap-2 self-end sm:flex-col sm:items-end sm:self-center">
                                <span
                                    class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="row.is_today || row.is_self
                                        ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200'
                                        : 'bg-pink-100 text-pink-700 dark:bg-pink-900/40 dark:text-pink-200'"
                                >
                                    <Sparkles v-if="row.is_today || row.is_self" class="h-3 w-3" />
                                    {{ daysUntilLabel(row) }}
                                </span>
                                <Button
                                    v-if="row.is_self"
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    class="h-8 gap-1.5 border-amber-300 text-amber-800 hover:bg-amber-50 dark:border-amber-800 dark:text-amber-200 dark:hover:bg-amber-950/50"
                                    :disabled="hiding"
                                    @click.stop.prevent="hideOwnBirthday"
                                >
                                    <EyeOff class="h-3.5 w-3.5" />
                                    {{ hiding ? t('dashboard.birthday_hiding') : t('dashboard.birthday_hide_mine') }}
                                </Button>
                                <Button
                                    v-else
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    class="h-8 gap-1.5 border-pink-200 text-pink-700 hover:bg-pink-50 dark:border-pink-800 dark:text-pink-200 dark:hover:bg-pink-950/50"
                                    @click="openPickerForOne(row.id)"
                                >
                                    <PartyPopper class="h-3.5 w-3.5" />
                                    {{ t('dashboard.birthday_wish') }}
                                </Button>
                            </div>
                        </div>
                    </article>

                    <!-- Emoji picker overlay -->
                    <div
                        v-if="pickerOpen"
                        class="sticky bottom-0 z-20 -mx-4 border-t border-pink-200 bg-white/95 p-4 shadow-[0_-8px_24px_rgba(244,63,94,0.12)] backdrop-blur dark:border-pink-900 dark:bg-zinc-950/95"
                    >
                        <div class="mb-3 flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-pink-800 dark:text-pink-200">
                                    {{ t('dashboard.birthday_pick_emoji') }}
                                </p>
                                <p class="truncate text-xs text-muted-foreground">
                                    {{ pickerTargetLabel }} — {{ t('dashboard.birthday_pick_emoji_hint') }}
                                </p>
                            </div>
                            <button
                                type="button"
                                class="rounded-full p-1 text-muted-foreground hover:bg-muted"
                                :aria-label="t('common.close')"
                                @click="closePicker"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>

                        <div class="grid grid-cols-4 gap-2 sm:grid-cols-8">
                            <button
                                v-for="option in emojiOptions"
                                :key="option.key"
                                type="button"
                                class="emoji-btn flex aspect-square flex-col items-center justify-center rounded-xl border bg-gradient-to-b from-pink-50 to-white text-2xl shadow-sm transition hover:-translate-y-1 hover:border-pink-300 hover:shadow-md disabled:opacity-50 dark:from-pink-950/40 dark:to-zinc-900"
                                :class="selectedEmoji === option.key
                                    ? 'border-pink-400 ring-2 ring-pink-300 dark:border-pink-500'
                                    : 'border-pink-100 dark:border-pink-900'"
                                :disabled="sending"
                                :title="t(option.labelKey)"
                                @click="selectedEmoji = option.key"
                            >
                                <span class="emoji-pop">{{ option.emoji }}</span>
                            </button>
                        </div>

                        <div class="mt-3 space-y-1.5">
                            <label class="text-xs font-medium text-pink-800 dark:text-pink-200">
                                {{ t('dashboard.birthday_wish_message_label') }}
                            </label>
                            <textarea
                                v-model="wishMessage"
                                rows="2"
                                maxlength="200"
                                class="w-full resize-none rounded-xl border border-pink-200 bg-white px-3 py-2 text-sm outline-none ring-pink-300 placeholder:text-muted-foreground focus:ring-2 dark:border-pink-900 dark:bg-zinc-900"
                                :placeholder="t('dashboard.birthday_wish_message_placeholder')"
                                :disabled="sending"
                            />
                            <p class="text-end text-[10px] text-muted-foreground">
                                {{ wishMessage.length }}/200
                            </p>
                        </div>

                        <div class="mt-3 flex justify-end">
                            <Button
                                type="button"
                                size="sm"
                                class="gap-1.5 bg-pink-600 text-white hover:bg-pink-700"
                                :disabled="sending || !selectedEmoji"
                                @click="sendWish()"
                            >
                                <PartyPopper class="h-3.5 w-3.5" />
                                {{ sending ? t('dashboard.birthday_wish_sending') : t('dashboard.birthday_wish') }}
                            </Button>
                        </div>

                        <p
                            v-if="sending"
                            class="mt-3 text-center text-xs text-pink-600"
                        >
                            {{ t('dashboard.birthday_wish_sending') }}
                        </p>
                    </div>
                </div>

                <div class="flex flex-col items-center gap-2 border-t border-pink-100 bg-background px-4 py-3 dark:border-pink-900/40">
                    <p
                        v-if="feedback"
                        class="text-center text-xs"
                        :class="feedbackOk ? 'text-emerald-600' : 'text-destructive'"
                    >
                        {{ feedback }}
                    </p>
                    <Button type="button" variant="ghost" class="text-muted-foreground" @click="open = false">
                        {{ t('common.close') }}
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style scoped>
.birthday-modal :deep(button.absolute) {
    color: white;
    opacity: 0.85;
}

.birthday-modal :deep(button.absolute:hover) {
    opacity: 1;
    background: rgba(255, 255, 255, 0.15);
}

.confetti-piece {
    position: absolute;
    top: -12px;
    opacity: 0;
    animation-name: confetti-fall;
    animation-timing-function: cubic-bezier(0.22, 0.61, 0.36, 1);
    animation-fill-mode: forwards;
    transform: rotate(var(--spin, 0deg));
}

.confetti-circle {
    border-radius: 9999px;
}

.confetti-ribbon {
    border-radius: 2px;
}

.confetti-rect {
    border-radius: 1px;
}

@keyframes confetti-fall {
    0% {
        opacity: 1;
        transform: translate3d(0, -10px, 0) rotate(var(--spin, 0deg));
    }
    100% {
        opacity: 0;
        transform: translate3d(18px, 280px, 0) rotate(calc(var(--spin, 0deg) + 420deg));
    }
}

.balloon {
    position: absolute;
    width: 18px;
    height: 24px;
    border-radius: 50% 50% 50% 50% / 45% 45% 55% 55%;
    opacity: 0.55;
    animation: balloon-float 4.5s ease-in-out infinite;
}

.balloon::after {
    content: '';
    position: absolute;
    left: 50%;
    bottom: -10px;
    width: 1px;
    height: 14px;
    background: rgba(255, 255, 255, 0.55);
    transform: translateX(-50%);
}

.balloon-a {
    background: #fde68a;
    top: 18%;
    left: 8%;
}

.balloon-b {
    background: #fda4af;
    top: 28%;
    right: 10%;
    animation-delay: 0.6s;
}

.balloon-c {
    background: #c4b5fd;
    top: 12%;
    left: 22%;
    animation-delay: 1.1s;
}

.balloon-d {
    background: #67e8f9;
    top: 35%;
    right: 22%;
    animation-delay: 1.7s;
}

@keyframes balloon-float {
    0%,
    100% {
        transform: translateY(0) rotate(-4deg);
    }
    50% {
        transform: translateY(-10px) rotate(4deg);
    }
}

.cake-bob {
    animation: cake-bob 2.2s ease-in-out infinite;
}

@keyframes cake-bob {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-4px);
    }
}

.birthday-card {
    animation: card-in 0.45s ease-out both;
}

@keyframes card-in {
    from {
        opacity: 0;
        transform: translateY(12px) scale(0.98);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.celebrate-pulse {
    animation: celebrate-pulse 0.9s ease-out;
}

@keyframes celebrate-pulse {
    0% {
        transform: scale(1);
    }
    40% {
        transform: scale(1.03);
    }
    100% {
        transform: scale(1);
    }
}

.emoji-btn:active .emoji-pop {
    animation: emoji-pop 0.35s ease-out;
}

@keyframes emoji-pop {
    0% {
        transform: scale(1);
    }
    40% {
        transform: scale(1.35) rotate(-8deg);
    }
    100% {
        transform: scale(1) rotate(0deg);
    }
}
</style>

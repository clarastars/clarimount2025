<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { router, usePage } from '@inertiajs/vue3';
import { Bell } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

interface NotificationData {
    event_type: string;
    salary_run_id?: number;
    leave_request_id?: number;
    employee_name?: string;
    leave_type?: string;
    start_date?: string;
    end_date?: string;
    days?: number;
    company_id?: number;
    company_name?: string;
    year?: number;
    month?: number;
    step_title?: string;
    actor_name?: string;
    reason?: string;
    after_rejection?: boolean;
    review_notes?: string;
    url?: string;
}

interface NotificationItem {
    id: string;
    read_at: string | null;
    created_at: string | null;
    event_type: string;
    data: NotificationData;
}

const { t, locale } = useI18n();
const page = usePage();

const authProps = computed(() => (page.props.auth as {
    can_view_salary_run_notifications?: boolean;
    can_view_leave_request_notifications?: boolean;
    is_employee?: boolean;
    unread_notifications_count?: number;
}) ?? {});

const showBell = computed(() =>
    authProps.value.can_view_salary_run_notifications === true
    || authProps.value.can_view_leave_request_notifications === true
    || authProps.value.is_employee === true,
);
const unreadCount = ref(authProps.value.unread_notifications_count ?? 0);
const notifications = ref<NotificationItem[]>([]);
const loading = ref(false);
const open = ref(false);

let pollTimer: ReturnType<typeof setInterval> | null = null;

watch(
    () => authProps.value.unread_notifications_count,
    (value) => {
        if (typeof value === 'number') {
            unreadCount.value = value;
        }
    },
);

const monthLabel = (month?: number): string => {
    if (!month) {
        return '';
    }

    try {
        const date = new Date(2000, month - 1, 1);
        return date.toLocaleDateString(locale.value === 'ar' ? 'ar-SA' : 'en-GB', { month: 'long' });
    } catch {
        return String(month);
    }
};

const periodLabel = (notification: NotificationItem): string => {
    const month = monthLabel(notification.data.month);
    const year = notification.data.year ?? '';

    return [month, year].filter((part) => part !== '').join(' ');
};

const formatNotificationMessage = (notification: NotificationItem): string => {
    const data = notification.data;

    if (data.event_type === 'leave_request_submitted') {
        const leaveTypeKey = `leaves.type_${data.leave_type ?? ''}`;
        const leaveType = t(leaveTypeKey);
        return t('notifications.leave_request_submitted', {
            employee: data.employee_name ?? '',
            company: data.company_name ?? '',
            type: leaveType === leaveTypeKey ? (data.leave_type ?? '') : leaveType,
            start: data.start_date ?? '',
            end: data.end_date ?? '',
            days: data.days ?? '',
        });
    }

    if (data.event_type === 'leave_request_approved' || data.event_type === 'leave_request_rejected') {
        const leaveTypeKey = `leaves.type_${data.leave_type ?? ''}`;
        const leaveType = t(leaveTypeKey);
        const messageKey = data.event_type === 'leave_request_approved'
            ? 'notifications.leave_request_approved'
            : 'notifications.leave_request_rejected';

        let message = t(messageKey, {
            company: data.company_name ?? '',
            type: leaveType === leaveTypeKey ? (data.leave_type ?? '') : leaveType,
            start: data.start_date ?? '',
            end: data.end_date ?? '',
            days: data.days ?? '',
        });

        if (data.review_notes) {
            message += ` ${t('notifications.leave_request_decision_notes', { notes: data.review_notes })}`;
        }

        return message;
    }

    const params = {
        company: data.company_name ?? '',
        period: periodLabel(notification),
        month: monthLabel(data.month),
        year: data.year ?? '',
        step: data.step_title ?? '',
        name: data.actor_name ?? '',
        reason: data.reason ?? '',
    };

    if (data.event_type === 'your_turn' && data.after_rejection) {
        return t('notifications.salary_run_your_turn_after_rejection', params);
    }

    return t(`notifications.salary_run_${data.event_type}`, params);
};

const formatTime = (iso: string | null): string => {
    if (!iso) {
        return '';
    }

    try {
        const date = new Date(iso);
        return date.toLocaleString(locale.value === 'ar' ? 'ar-SA' : 'en-GB', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return iso;
    }
};

const fetchNotifications = async (): Promise<void> => {
    if (!showBell.value) {
        return;
    }

    loading.value = true;
    try {
        const response = await fetch('/api/notifications', {
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            return;
        }

        const payload = (await response.json()) as {
            notifications: NotificationItem[];
            unread_count: number;
        };

        notifications.value = payload.notifications ?? [];
        unreadCount.value = payload.unread_count ?? 0;
    } finally {
        loading.value = false;
    }
};

const markAsRead = async (notification: NotificationItem): Promise<void> => {
    if (notification.read_at) {
        return;
    }

    await fetch(`/api/notifications/${notification.id}/read`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
        },
    });

    notification.read_at = new Date().toISOString();
    unreadCount.value = Math.max(0, unreadCount.value - 1);
};

const markAllAsRead = async (): Promise<void> => {
    await fetch('/api/notifications/read-all', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
        },
    });

    notifications.value = notifications.value.map((notification) => ({
        ...notification,
        read_at: notification.read_at ?? new Date().toISOString(),
    }));
    unreadCount.value = 0;
};

const openNotification = async (notification: NotificationItem): Promise<void> => {
    await markAsRead(notification);
    open.value = false;

    if (notification.data.url) {
        router.visit(notification.data.url);
    }
};

onMounted(() => {
    void fetchNotifications();
    pollTimer = setInterval(() => {
        void fetchNotifications();
    }, 60000);
});

onBeforeUnmount(() => {
    if (pollTimer) {
        clearInterval(pollTimer);
    }
});

watch(open, (isOpen) => {
    if (isOpen) {
        void fetchNotifications();
    }
});
</script>

<template>
    <DropdownMenu v-if="showBell" v-model:open="open">
        <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="icon" class="relative h-9 w-9 shrink-0">
                <Bell class="h-5 w-5" />
                <span
                    v-if="unreadCount > 0"
                    class="absolute -top-0.5 -end-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-semibold text-white"
                >
                    {{ unreadCount > 99 ? '99+' : unreadCount }}
                </span>
                <span class="sr-only">{{ t('notifications.title') }}</span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-[min(24rem,calc(100vw-2rem))] p-0">
            <div class="flex items-center justify-between border-b px-3 py-2">
                <div class="text-sm font-semibold">{{ t('notifications.title') }}</div>
                <Button
                    v-if="unreadCount > 0"
                    variant="ghost"
                    size="sm"
                    class="h-8 text-xs"
                    @click="markAllAsRead"
                >
                    {{ t('notifications.mark_all_read') }}
                </Button>
            </div>

            <div v-if="loading && notifications.length === 0" class="px-3 py-6 text-center text-sm text-muted-foreground">
                ...
            </div>

            <div v-else-if="notifications.length === 0" class="px-3 py-6 text-center text-sm text-muted-foreground">
                {{ t('notifications.empty') }}
            </div>

            <div v-else class="max-h-[min(24rem,70vh)] overflow-auto">
                <button
                    v-for="notification in notifications"
                    :key="notification.id"
                    type="button"
                    class="flex w-full flex-col gap-1 border-b px-3 py-3 text-start transition hover:bg-accent"
                    :class="notification.read_at ? 'opacity-75' : 'bg-accent/30'"
                    @click="openNotification(notification)"
                >
                    <div class="text-sm leading-5">{{ formatNotificationMessage(notification) }}</div>
                    <div class="text-xs text-muted-foreground">{{ formatTime(notification.created_at) }}</div>
                </button>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>

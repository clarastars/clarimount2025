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
    salary_certificate_request_id?: number;
    settlement_id?: number;
    employee_id?: number;
    employee_name?: string;
    leave_type?: string;
    leave_type_label?: string;
    purpose?: string;
    start_date?: string;
    end_date?: string;
    settlement_date?: string;
    settlement_reason?: string;
    net_due?: string;
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
    remaining_steps?: number;
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
    can_view_entitlement_settlement_notifications?: boolean;
    is_employee?: boolean;
    unread_notifications_count?: number;
}) ?? {});

const showBell = computed(() =>
    authProps.value.can_view_salary_run_notifications === true
    || authProps.value.can_view_leave_request_notifications === true
    || authProps.value.can_view_entitlement_settlement_notifications === true
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
    const leaveTypeValue = data.leave_type_label || (() => {
        const leaveTypeKey = `leaves.type_${data.leave_type ?? ''}`;
        const leaveType = t(leaveTypeKey);
        return leaveType === leaveTypeKey ? (data.leave_type ?? '') : leaveType;
    })();

    if (data.event_type === 'leave_request_submitted') {
        return t('notifications.leave_request_submitted', {
            employee: data.employee_name ?? '',
            company: data.company_name ?? '',
            type: leaveTypeValue,
            start: data.start_date ?? '',
            end: data.end_date ?? '',
            days: data.days ?? '',
        });
    }

    if (data.event_type === 'leave_request_approved' || (data.event_type === 'leave_request_rejected' && !data.step_title)) {
        const messageKey = data.event_type === 'leave_request_approved'
            ? 'notifications.leave_request_approved'
            : 'notifications.leave_request_rejected';

        let message = t(messageKey, {
            company: data.company_name ?? '',
            type: leaveTypeValue,
            start: data.start_date ?? '',
            end: data.end_date ?? '',
            days: data.days ?? '',
        });

        if (data.review_notes) {
            message += ` ${t('notifications.leave_request_decision_notes', { notes: data.review_notes })}`;
        }

        return message;
    }

    const leaveWorkflowTypes = [
        'leave_request_your_turn',
        'leave_request_step_approved',
        'leave_request_step_progress',
        'leave_request_workflow_rejected',
        'leave_request_finalized',
    ];

    if (leaveWorkflowTypes.includes(data.event_type) || (data.event_type === 'leave_request_rejected' && data.step_title)) {
        const params = {
            employee: data.employee_name ?? '',
            company: data.company_name ?? '',
            type: leaveTypeValue,
            start: data.start_date ?? '',
            end: data.end_date ?? '',
            days: data.days ?? '',
            step: data.step_title ?? '',
            name: data.actor_name ?? '',
            reason: data.reason ?? '',
            remaining: data.remaining_steps ?? '',
        };

        if (data.event_type === 'leave_request_your_turn' && data.after_rejection) {
            return t('notifications.leave_request_workflow_your_turn_after_rejection', params);
        }

        const keyMap: Record<string, string> = {
            leave_request_your_turn: 'notifications.leave_request_workflow_your_turn',
            leave_request_step_approved: 'notifications.leave_request_workflow_step_approved',
            leave_request_step_progress: 'notifications.leave_request_workflow_step_progress',
            leave_request_workflow_rejected: 'notifications.leave_request_workflow_employee_rejected',
            leave_request_finalized: 'notifications.leave_request_workflow_finalized',
            leave_request_rejected: 'notifications.leave_request_workflow_rejected',
            leave_request_approved: 'notifications.leave_request_workflow_finalized',
        };

        const messageKey = keyMap[data.event_type] ?? 'notifications.leave_request_workflow_your_turn';

        return t(messageKey, params);
    }

    if (data.event_type === 'salary_certificate_request_submitted') {
        return t('notifications.salary_certificate_request_submitted', {
            employee: data.employee_name ?? '',
            company: data.company_name ?? '',
            purpose: data.purpose ?? '',
        });
    }

    if (
        data.event_type === 'salary_certificate_request_completed'
        || (data.event_type === 'salary_certificate_request_rejected' && !data.step_title)
    ) {
        const messageKey = data.event_type === 'salary_certificate_request_completed'
            ? 'notifications.salary_certificate_request_completed'
            : 'notifications.salary_certificate_request_rejected';

        let message = t(messageKey, {
            company: data.company_name ?? '',
            purpose: data.purpose ?? '',
        });

        if (data.review_notes) {
            message += ` ${t('notifications.salary_certificate_request_decision_notes', { notes: data.review_notes })}`;
        }

        return message;
    }

    const salaryCertificateWorkflowTypes = [
        'salary_certificate_request_your_turn',
        'salary_certificate_request_step_approved',
        'salary_certificate_request_step_progress',
        'salary_certificate_request_workflow_rejected',
        'salary_certificate_request_finalized',
    ];

    if (
        salaryCertificateWorkflowTypes.includes(data.event_type)
        || (data.event_type === 'salary_certificate_request_rejected' && data.step_title)
    ) {
        const params = {
            employee: data.employee_name ?? '',
            company: data.company_name ?? '',
            purpose: data.purpose ?? '',
            step: data.step_title ?? '',
            name: data.actor_name ?? '',
            reason: data.reason ?? '',
            remaining: data.remaining_steps ?? '',
        };

        if (data.event_type === 'salary_certificate_request_your_turn' && data.after_rejection) {
            return t('notifications.salary_certificate_request_workflow_your_turn_after_rejection', params);
        }

        const keyMap: Record<string, string> = {
            salary_certificate_request_your_turn: 'notifications.salary_certificate_request_workflow_your_turn',
            salary_certificate_request_step_approved: 'notifications.salary_certificate_request_workflow_step_approved',
            salary_certificate_request_step_progress: 'notifications.salary_certificate_request_workflow_step_progress',
            salary_certificate_request_workflow_rejected: 'notifications.salary_certificate_request_workflow_employee_rejected',
            salary_certificate_request_finalized: 'notifications.salary_certificate_request_workflow_finalized',
            salary_certificate_request_rejected: 'notifications.salary_certificate_request_workflow_rejected',
            salary_certificate_request_completed: 'notifications.salary_certificate_request_workflow_finalized',
        };

        const messageKey = keyMap[data.event_type] ?? 'notifications.salary_certificate_request_workflow_your_turn';

        return t(messageKey, params);
    }

    const entitlementSettlementWorkflowTypes = [
        'entitlement_settlement_your_turn',
        'entitlement_settlement_step_approved',
        'entitlement_settlement_step_progress',
        'entitlement_settlement_rejected',
        'entitlement_settlement_workflow_rejected',
        'entitlement_settlement_finalized',
        'entitlement_settlement_approved',
    ];

    if (entitlementSettlementWorkflowTypes.includes(data.event_type)) {
        const settlementParams = {
            employee: data.employee_name ?? '',
            company: data.company_name ?? '',
            date: data.settlement_date ?? '',
            reason: data.settlement_reason ?? '',
            net: data.net_due ?? '',
            step: data.step_title ?? '',
            name: data.actor_name ?? '',
            remaining: data.remaining_steps ?? '',
            reject_reason: data.reason ?? '',
        };

        if (data.event_type === 'entitlement_settlement_your_turn' && data.after_rejection) {
            return t('notifications.entitlement_settlement_workflow_your_turn_after_rejection', settlementParams);
        }

        const settlementKeyMap: Record<string, string> = {
            entitlement_settlement_your_turn: 'notifications.entitlement_settlement_workflow_your_turn',
            entitlement_settlement_step_approved: 'notifications.entitlement_settlement_workflow_step_approved',
            entitlement_settlement_step_progress: 'notifications.entitlement_settlement_workflow_step_progress',
            entitlement_settlement_rejected: 'notifications.entitlement_settlement_workflow_rejected',
            entitlement_settlement_workflow_rejected: 'notifications.entitlement_settlement_workflow_employee_rejected',
            entitlement_settlement_finalized: 'notifications.entitlement_settlement_workflow_finalized',
            entitlement_settlement_approved: 'notifications.entitlement_settlement_workflow_finalized',
        };

        return t(
            settlementKeyMap[data.event_type] ?? 'notifications.entitlement_settlement_workflow_your_turn',
            settlementParams,
        );
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

const resolveNotificationUrl = (data: NotificationData): string | null => {
    if (data.url) {
        if (data.url.startsWith('/')) {
            return data.url;
        }

        try {
            const parsed = new URL(data.url, window.location.origin);

            if (parsed.origin === window.location.origin) {
                return parsed.pathname + parsed.search + parsed.hash;
            }
        } catch {
            return data.url;
        }

        return data.url;
    }

    if (data.employee_id && data.settlement_id) {
        return route('employees.entitlement-settlement.show', [data.employee_id, data.settlement_id]);
    }

    return null;
};

const openNotification = async (notification: NotificationItem): Promise<void> => {
    await markAsRead(notification);
    open.value = false;

    const targetUrl = resolveNotificationUrl(notification.data);

    if (targetUrl) {
        router.visit(targetUrl);
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

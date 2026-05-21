<script setup lang="ts">
import UserInfo from '@/components/UserInfo.vue';
import { DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import type { User } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { LogOut, Settings } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';

const { t } = useI18n();
const page = usePage();

const settingsHref = computed(() => {
    const auth = page.props.auth as { is_employee?: boolean; can_access_settings?: boolean } | undefined;

    if (auth?.is_employee && ! auth?.can_access_settings) {
        return route('password.edit');
    }

    return route('profile.edit');
});

interface Props {
    user: User;
}

const logout = () => {
    router.post(route('logout'));
};

defineProps<Props>();
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full" :href="settingsHref" prefetch as="button">
                <Settings class="ltr:mr-2 rtl:ml-2 h-4 w-4" />
                {{ t('nav.settings') }}
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem class="cursor-pointer" @click="logout">
        <LogOut class="ltr:mr-2 rtl:ml-2 h-4 w-4" />
        {{ t('nav.logout') }}
    </DropdownMenuItem>
</template>

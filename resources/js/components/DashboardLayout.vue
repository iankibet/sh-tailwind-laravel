<script setup>
import { formatDate, getAuthStrategy, shRepo, useStreamline, useUserStore } from '@iankibetsh/sh-core';
import { Passkeys } from '@laravel/passkeys';
import { clearTableCache } from '@iankibetsh/sh-tailwind';
import {
    Bell,
    CheckCheck,
    ChevronDown,
    ChevronRight,
    Fingerprint,
    LogOut,
    Menu,
    UserRound,
    X,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { rememberEmail } from '../lib/rememberedAccount';
import { menuItemsForPosition } from '../navigation/menu';
import { useNotificationStore } from '../stores/notifications';
import SidebarMenu from './SidebarMenu.vue';

const props = defineProps({
    breadcrumbLabel: { type: String, default: '' },
});

const route = useRoute();
const router = useRouter();
const userStore = useUserStore();
const notificationStore = useNotificationStore();
const { service: authStream } = useStreamline('auth/user');
const notifications = computed(() => notificationStore.unread);
const unreadCount = computed(() => notificationStore.unreadCount);
const mobileSidebarOpen = ref(false);
const notificationsOpen = ref(false);
const profileOpen = ref(false);
const signingOut = ref(false);
const notificationsMenu = ref(null);
const profileMenu = ref(null);
const showPasskeySuggestion = ref(false);
const mainMenuItems = menuItemsForPosition('main');
const bottomMenuItems = menuItemsForPosition('bottom');

const pageTitle = computed(() => route.meta.title || 'Dashboard');
const breadcrumbs = computed(() => {
    const parents = route.meta.breadcrumbs ?? [];
    const current = props.breadcrumbLabel || pageTitle.value;

    return [
        ...parents,
        { label: current },
    ];
});
const currentUser = computed(() => userStore.user);
const userInitials = computed(() => currentUser.value?.name
    ?.split(/\s+/)
    .slice(0, 2)
    .map((part) => part[0])
    .join('')
    .toUpperCase() || 'U');
const roleLabel = computed(() => currentUser.value?.role?.replaceAll('_', ' ') || 'User');

watch(
    () => route.path,
    () => {
        mobileSidebarOpen.value = false;
        notificationsOpen.value = false;
        profileOpen.value = false;
    },
    { immediate: true },
);

const toggleNotifications = () => {
    notificationsOpen.value = !notificationsOpen.value;
    profileOpen.value = false;

    if (notificationsOpen.value) {
        notificationStore.fetch().catch(() => {});
    }
};

const openNotification = async (notification) => {
    notificationsOpen.value = false;

    try {
        const actionUrl = await notificationStore.markRead(notification.id);

        if (!actionUrl) return;

        if (/^https?:\/\//i.test(actionUrl)) {
            window.location.href = actionUrl;
        } else {
            await router.push(actionUrl);
        }
    } catch (reason) {
        shRepo.showToast(reason.response?.data?.message ?? 'Unable to open notification', 'error');
    }
};

const markAllNotificationsRead = async () => {
    try {
        await notificationStore.markAllRead();
    } catch (reason) {
        shRepo.showToast(reason.response?.data?.message ?? 'Unable to update notifications', 'error');
    }
};

const toggleProfile = () => {
    profileOpen.value = !profileOpen.value;
    notificationsOpen.value = false;
};

const handleDocumentClick = (event) => {
    if (notificationsMenu.value && !notificationsMenu.value.contains(event.target)) {
        notificationsOpen.value = false;
    }

    if (profileMenu.value && !profileMenu.value.contains(event.target)) {
        profileOpen.value = false;
    }
};

const handleEscape = (event) => {
    if (event.key !== 'Escape') return;

    notificationsOpen.value = false;
    profileOpen.value = false;
    mobileSidebarOpen.value = false;
};

const logout = async () => {
    signingOut.value = true;
    rememberEmail(userStore.user?.email);

    try {
        await authStream.logout();
    } finally {
        getAuthStrategy().clear();
        userStore.$reset();
        await clearTableCache();
        signingOut.value = false;
        await router.replace('/login');
    }
};

const checkPasskeySuggestion = async () => {
    if (!Passkeys.isSupported() || route.path === '/profile') return;

    const user = userStore.user;
    const dismissedKey = `passkey_suggestion_dismissed_${user?.id}`;
    if (!user || sessionStorage.getItem(dismissedKey)) return;

    try {
        const response = await fetch('/user/passkeys', {
            headers: {
                Accept: 'application/json',
                Authorization: `Bearer ${getAuthStrategy().getToken()}`,
            },
        });
        const data = response.ok ? await response.json() : null;
        showPasskeySuggestion.value = data?.passkeys?.length === 0;
    } catch {
        showPasskeySuggestion.value = false;
    }
};

const dismissPasskeySuggestion = () => {
    showPasskeySuggestion.value = false;
    if (userStore.user?.id) {
        sessionStorage.setItem(`passkey_suggestion_dismissed_${userStore.user.id}`, '1');
    }
};

onMounted(async () => {
    if (!userStore.user) {
        await userStore.fetchUser('auth/user:current');
    }

    await checkPasskeySuggestion();
    notificationStore.fetch().catch(() => {});

    document.addEventListener('click', handleDocumentClick);
    document.addEventListener('keydown', handleEscape);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleDocumentClick);
    document.removeEventListener('keydown', handleEscape);
});
</script>

<template>
    <div class="min-h-screen bg-[#f4f7f5] lg:pl-72">
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-200"
            leave-to-class="opacity-0"
        >
            <button
                v-if="mobileSidebarOpen"
                type="button"
                class="fixed inset-0 z-40 bg-ink-950/45 backdrop-blur-sm lg:hidden"
                aria-label="Close navigation"
                @click="mobileSidebarOpen = false"
            />
        </Transition>

        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-slate-200 bg-white px-4 py-5 text-ink-950 shadow-2xl transition-transform duration-300 ease-out lg:translate-x-0 lg:shadow-none"
            :class="mobileSidebarOpen ? 'translate-x-0' : '-translate-x-full'"
            aria-label="Main navigation"
        >
            <div class="flex items-center justify-between px-2">
                <RouterLink to="/users" class="flex items-center gap-3 font-semibold tracking-tight">
                    <span class="grid size-10 place-items-center rounded-xl bg-ink-950 text-sm font-black text-white">PV</span>
                    <span>Pius Videos</span>
                </RouterLink>
                <button
                    type="button"
                    class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-ink-950 lg:hidden"
                    aria-label="Close navigation"
                    @click="mobileSidebarOpen = false"
                >
                    <X :size="20" />
                </button>
            </div>

            <nav class="mt-10 flex-1" aria-label="Primary navigation">
                <p class="px-3 text-[0.68rem] font-bold uppercase tracking-[0.2em] text-slate-500">Workspace</p>
                <SidebarMenu :items="mainMenuItems" class="mt-3" @navigate="mobileSidebarOpen = false" />
            </nav>

            <div class="relative border-t border-slate-200 pt-4">
                <SidebarMenu
                    :items="bottomMenuItems"
                    position="bottom"
                    @navigate="mobileSidebarOpen = false"
                />
            </div>
        </aside>

        <div class="min-h-screen">
            <header class="sticky top-0 z-30 border-b border-slate-200/80 bg-white/90 backdrop-blur-xl">
                <div class="flex h-18 items-center gap-4 px-5 sm:px-8">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 bg-white p-2.5 text-slate-600 shadow-sm transition hover:text-ink-950 lg:hidden"
                        aria-label="Open navigation"
                        @click="mobileSidebarOpen = true"
                    >
                        <Menu :size="20" />
                    </button>

                    <div class="min-w-0 flex-1">
                        <nav class="flex min-w-0 items-center gap-1.5 text-sm" aria-label="Breadcrumb">
                            <template v-for="(crumb, index) in breadcrumbs" :key="`${crumb.label}-${index}`">
                                <ChevronRight v-if="index" :size="14" class="shrink-0 text-slate-300" />
                                <RouterLink
                                    v-if="crumb.to"
                                    :to="crumb.to"
                                    class="shrink-0 font-medium text-slate-400 transition hover:text-ink-950"
                                >
                                    {{ crumb.label }}
                                </RouterLink>
                                <span v-else class="truncate font-semibold text-ink-950" :aria-current="index === breadcrumbs.length - 1 ? 'page' : undefined">
                                    {{ crumb.label }}
                                </span>
                            </template>
                        </nav>
                        <p class="hidden text-xs text-slate-400 sm:block">Pius Videos administration</p>
                    </div>

                    <div ref="notificationsMenu" class="relative">
                        <button
                            type="button"
                            class="relative rounded-xl p-2.5 text-slate-500 transition hover:bg-slate-100 hover:text-ink-950"
                            aria-label="Notifications"
                            :aria-expanded="notificationsOpen"
                            @click.stop="toggleNotifications"
                        >
                            <Bell :size="20" />
                            <span
                                v-if="unreadCount"
                                class="absolute -right-1 -top-1 grid h-[1.1rem] min-w-[1.1rem] place-items-center rounded-full bg-brand-500 px-1 text-[0.6rem] font-bold text-white ring-2 ring-white"
                            >
                                {{ unreadCount > 9 ? '9+' : unreadCount }}
                            </span>
                        </button>

                        <Transition enter-active-class="transition duration-150" enter-from-class="translate-y-1 opacity-0" leave-active-class="transition duration-100" leave-to-class="translate-y-1 opacity-0">
                            <div v-if="notificationsOpen" class="absolute right-0 mt-3 w-80 max-w-[calc(100vw-2.5rem)] rounded-2xl border border-slate-200 bg-white p-4 shadow-2xl shadow-ink-950/10">
                                <div class="flex items-center justify-between">
                                    <p class="font-semibold text-ink-950">Notifications</p>
                                    <button
                                        v-if="unreadCount"
                                        type="button"
                                        class="text-xs font-semibold text-brand-700 transition hover:text-brand-600"
                                        @click.stop="markAllNotificationsRead"
                                    >
                                        Mark all read
                                    </button>
                                </div>

                                <div v-if="notifications.length" class="mt-3 max-h-96 space-y-1.5 overflow-y-auto">
                                    <button
                                        v-for="notification in notifications"
                                        :key="notification.id"
                                        type="button"
                                        class="block w-full rounded-xl border border-transparent bg-brand-50/60 px-3 py-2.5 text-left transition hover:bg-brand-50"
                                        @click="openNotification(notification)"
                                    >
                                        <div class="flex items-start gap-2">
                                            <span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-brand-500" />
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate text-sm font-semibold text-ink-950">
                                                    {{ notification.subject }}
                                                </p>
                                                <p class="mt-0.5 line-clamp-2 text-xs leading-5 text-slate-500">{{ notification.message }}</p>
                                                <p class="mt-1 text-[0.68rem] text-slate-400">{{ formatDate(notification.created_at) }}</p>
                                            </div>
                                        </div>
                                    </button>
                                </div>
                                <div v-else class="mt-4 flex flex-col items-center gap-2 rounded-xl bg-slate-50 px-4 py-7 text-center">
                                    <span class="grid size-10 place-items-center rounded-full bg-brand-50 text-brand-600">
                                        <CheckCheck :size="20" />
                                    </span>
                                    <p class="text-sm font-semibold text-ink-950">You're all caught up</p>
                                    <p class="text-xs text-slate-500">No unread notifications.</p>
                                </div>

                                <RouterLink
                                    to="/notifications/all"
                                    class="mt-3 block rounded-xl py-2 text-center text-sm font-semibold text-brand-700 transition hover:bg-slate-50"
                                    @click="notificationsOpen = false"
                                >
                                    View all notifications
                                </RouterLink>
                            </div>
                        </Transition>
                    </div>

                    <div ref="profileMenu" class="relative">
                        <button
                            type="button"
                            class="flex items-center gap-2 rounded-xl p-1.5 pr-2 text-left transition hover:bg-slate-100"
                            :aria-expanded="profileOpen"
                            aria-label="Open user menu"
                            @click.stop="toggleProfile"
                        >
                            <span class="grid size-9 place-items-center rounded-lg bg-brand-100 text-xs font-bold text-brand-700">
                                {{ userInitials }}
                            </span>
                            <span class="hidden sm:block">
                                <span class="block max-w-40 truncate text-sm font-semibold leading-4 text-ink-950">{{ currentUser?.name || 'Loading...' }}</span>
                                <span class="block max-w-40 truncate text-xs capitalize text-slate-400">{{ roleLabel }}</span>
                            </span>
                            <ChevronDown :size="16" class="hidden text-slate-400 sm:block" />
                        </button>

                        <Transition enter-active-class="transition duration-150" enter-from-class="translate-y-1 opacity-0" leave-active-class="transition duration-100" leave-to-class="translate-y-1 opacity-0">
                            <div v-if="profileOpen" class="absolute right-0 mt-3 w-64 rounded-2xl border border-slate-200 bg-white p-2 shadow-2xl shadow-ink-950/10">
                                <div class="border-b border-slate-100 px-3 py-3">
                                    <p class="truncate text-sm font-semibold text-ink-950">{{ currentUser?.name }}</p>
                                    <p class="mt-0.5 truncate text-xs text-slate-400">{{ currentUser?.email }}</p>
                                </div>
                                <RouterLink to="/profile" class="mt-1 flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-slate-600 transition hover:bg-slate-50 hover:text-ink-950">
                                    <UserRound :size="17" />
                                    Your profile
                                </RouterLink>
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-red-600 transition hover:bg-red-50 disabled:opacity-60"
                                    :disabled="signingOut"
                                    @click="logout"
                                >
                                    <LogOut :size="17" />
                                    {{ signingOut ? 'Signing out...' : 'Sign out' }}
                                </button>
                            </div>
                        </Transition>
                    </div>
                </div>
            </header>

            <main class="px-5 py-8 sm:px-8 sm:py-10">
                <div class="mx-auto max-w-7xl">
                    <div v-if="showPasskeySuggestion" class="mb-6 flex flex-col gap-4 rounded-2xl border border-brand-100 bg-brand-50 p-5 sm:flex-row sm:items-center">
                        <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-white text-brand-700 shadow-sm"><Fingerprint :size="22" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-ink-950">Sign in faster next time</p>
                            <p class="mt-1 text-sm leading-6 text-slate-600">Add a passkey to use your fingerprint, face, device PIN, or security key instead of a password.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <RouterLink to="/profile" class="rounded-xl bg-ink-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800">Set up passkey</RouterLink>
                            <button type="button" class="rounded-xl p-2.5 text-slate-400 transition hover:bg-white hover:text-ink-950" aria-label="Dismiss passkey suggestion" @click="dismissPasskeySuggestion">
                                <X :size="19" />
                            </button>
                        </div>
                    </div>
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>

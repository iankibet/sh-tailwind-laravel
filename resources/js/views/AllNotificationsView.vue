<script setup>
import { formatDate, shRepo } from '@iankibetsh/sh-core';
import { Bell, CheckCheck, ChevronRight, LoaderCircle } from '@lucide/vue';
import { computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import DashboardLayout from '../components/DashboardLayout.vue';
import { useNotificationStore } from '../stores/notifications';

const router = useRouter();
const notificationStore = useNotificationStore();

const notifications = computed(() => notificationStore.all);
const loading = computed(() => notificationStore.loadingAll);
const unreadCount = computed(() => notificationStore.unreadCount);

const open = async (notification) => {
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

const markAllRead = async () => {
    try {
        await notificationStore.markAllRead();
    } catch (reason) {
        shRepo.showToast(reason.response?.data?.message ?? 'Unable to update notifications', 'error');
    }
};

onMounted(() => {
    notificationStore.fetchAll().catch(() => {});
});
</script>

<template>
    <DashboardLayout>
        <section>
            <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="max-w-2xl leading-7 text-slate-500">
                    Your notification history. Unread notifications are highlighted; opening one marks it as read.
                </p>
                <button
                    v-if="unreadCount"
                    type="button"
                    class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50"
                    @click="markAllRead"
                >
                    <CheckCheck :size="16" />
                    Mark all read
                </button>
            </div>

            <div v-if="loading" class="grid min-h-[20rem] place-items-center">
                <div class="text-center text-slate-500">
                    <LoaderCircle :size="28" class="mx-auto animate-spin" />
                    <p class="mt-3 text-sm">Loading notifications...</p>
                </div>
            </div>

            <div
                v-else-if="!notifications.length"
                class="grid min-h-[20rem] place-items-center rounded-[1.5rem] border border-slate-200 bg-white shadow-[0_24px_70px_-45px_rgba(15,35,30,0.45)]"
            >
                <div class="flex flex-col items-center gap-3 px-6 text-center">
                    <span class="grid size-14 place-items-center rounded-2xl bg-brand-50 text-brand-600">
                        <CheckCheck :size="26" />
                    </span>
                    <p class="text-lg font-semibold text-ink-950">You're all caught up</p>
                    <p class="max-w-sm text-sm text-slate-500">You don't have any notifications yet. New notifications will show up here.</p>
                </div>
            </div>

            <div v-else class="overflow-hidden rounded-[1.5rem] border border-slate-200 bg-white shadow-[0_24px_70px_-45px_rgba(15,35,30,0.45)]">
                <ul class="divide-y divide-slate-100">
                    <li v-for="notification in notifications" :key="notification.id">
                        <button
                            type="button"
                            class="flex w-full items-start gap-4 px-5 py-4 text-left transition hover:bg-slate-50"
                            :class="notification.read_at ? '' : 'bg-brand-50/50'"
                            @click="open(notification)"
                        >
                            <span class="mt-0.5 grid size-9 shrink-0 place-items-center rounded-xl bg-slate-100 text-ink-950">
                                <Bell :size="18" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <p class="truncate text-sm text-ink-950" :class="notification.read_at ? 'font-medium' : 'font-semibold'">
                                        {{ notification.subject }}
                                    </p>
                                    <span v-if="!notification.read_at" class="size-1.5 shrink-0 rounded-full bg-brand-500" />
                                </div>
                                <p class="mt-1 line-clamp-2 text-sm leading-6 text-slate-500">{{ notification.message }}</p>
                                <p class="mt-1.5 text-xs text-slate-400">{{ formatDate(notification.created_at) }}</p>
                            </div>
                            <ChevronRight v-if="notification.action_url" :size="18" class="mt-2 shrink-0 text-slate-300" />
                        </button>
                    </li>
                </ul>
            </div>
        </section>
    </DashboardLayout>
</template>

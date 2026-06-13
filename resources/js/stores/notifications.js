import { shApis } from '@iankibetsh/sh-core';
import { defineStore } from 'pinia';

export const useNotificationStore = defineStore('notifications', {
    state: () => ({
        unread: [],
        all: [],
        unreadCount: 0,
        loading: false,
        loadingAll: false,
        loaded: false,
    }),
    actions: {
        async fetch() {
            this.loading = true;
            try {
                const { data } = await shApis.doPost('auth/notifications:list');
                this.unread = data.notifications ?? [];
                this.unreadCount = data.unread_count ?? 0;
                this.loaded = true;
            } finally {
                this.loading = false;
            }
        },

        async fetchAll() {
            this.loadingAll = true;
            try {
                const { data } = await shApis.doPost('auth/notifications:all');
                this.all = data.notifications ?? [];
                this.unreadCount = data.unread_count ?? 0;
            } finally {
                this.loadingAll = false;
            }
        },

        async markRead(id) {
            const { data } = await shApis.doPost('auth/notifications:read', { params: [id] });

            const readAt = new Date().toISOString();
            const wasUnread = this.unread.some((item) => item.id === id);

            // Drop it from the dropdown's unread list.
            this.unread = this.unread.filter((item) => item.id !== id);

            // Reflect the read state on the full history list (if loaded).
            const historyItem = this.all.find((item) => item.id === id);
            if (historyItem && !historyItem.read_at) {
                historyItem.read_at = readAt;
            }

            if (wasUnread) {
                this.unreadCount = Math.max(0, this.unreadCount - 1);
            }

            return data.action_url ?? null;
        },

        async markAllRead() {
            await shApis.doPost('auth/notifications:markAllRead');
            const readAt = new Date().toISOString();
            this.unread = [];
            this.all.forEach((item) => {
                item.read_at = item.read_at ?? readAt;
            });
            this.unreadCount = 0;
        },
    },
});

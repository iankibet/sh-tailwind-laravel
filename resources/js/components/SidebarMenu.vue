<script setup>
import { useUserStore } from '@iankibetsh/sh-core';
import { ChevronDown, ChevronUp } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useRoute } from 'vue-router';

const props = defineProps({
    items: { type: Array, default: () => [] },
    position: { type: String, default: 'main' },
});

const emit = defineEmits(['navigate']);
const route = useRoute();
const userStore = useUserStore();
const openDropdowns = ref({});

const isPathActive = (path) => Boolean(
    path && (route.path === path || route.path.startsWith(`${path}/`)),
);

const canView = (item) => !item.permission || userStore.isAllowedTo(item.permission);

const visibleItems = computed(() => props.items.reduce((items, item) => {
    const type = item.type ?? 'link';

    if (type === 'dropdown') {
        const children = (item.children ?? []).filter(canView);

        if (canView(item) && children.length) {
            items.push({ ...item, type, children });
        }

        return items;
    }

    if (canView(item)) {
        items.push({ ...item, type });
    }

    return items;
}, []));

const itemKey = (item) => item.path ?? item.label;
const dropdownActive = (item) => item.children.some((child) => isPathActive(child.path));
const dropdownOpen = (item) => Boolean(openDropdowns.value[itemKey(item)]);
const dropdownId = (item) => `sidebar-menu-${itemKey(item).toLowerCase().replace(/[^a-z0-9]+/g, '-')}`;

const toggleDropdown = (item) => {
    const key = itemKey(item);
    openDropdowns.value[key] = !openDropdowns.value[key];
};

watch(
    [() => route.path, visibleItems],
    () => {
        visibleItems.value.forEach((item) => {
            if (item.type === 'dropdown' && dropdownActive(item)) {
                openDropdowns.value[itemKey(item)] = true;
            }
        });
    },
    { immediate: true },
);
</script>

<template>
    <div v-if="visibleItems.length" :class="position === 'bottom' ? 'space-y-2' : 'space-y-1'">
        <template v-for="item in visibleItems" :key="itemKey(item)">
            <RouterLink
                v-if="item.type === 'link' && item.permission"
                v-if-user-can="item.permission"
                :to="item.path"
                class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold transition"
                :class="isPathActive(item.path) ? 'bg-slate-100 text-ink-950' : 'text-slate-600 hover:bg-slate-50 hover:text-ink-950'"
                @click="emit('navigate')"
            >
                <component :is="item.icon" :size="19" />
                {{ item.label }}
            </RouterLink>

            <RouterLink
                v-else-if="item.type === 'link'"
                :to="item.path"
                class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold transition"
                :class="isPathActive(item.path) ? 'bg-slate-100 text-ink-950' : 'text-slate-600 hover:bg-slate-50 hover:text-ink-950'"
                @click="emit('navigate')"
            >
                <component :is="item.icon" :size="19" />
                {{ item.label }}
            </RouterLink>

            <div
                v-else-if="item.type === 'dropdown'"
                class="flex"
                :class="position === 'bottom' ? 'flex-col-reverse' : 'flex-col'"
            >
                <button
                    type="button"
                    class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left text-sm font-semibold transition"
                    :class="dropdownActive(item) || dropdownOpen(item) ? 'bg-slate-100 text-ink-950' : 'text-slate-600 hover:bg-slate-50 hover:text-ink-950'"
                    :aria-expanded="dropdownOpen(item)"
                    :aria-controls="dropdownId(item)"
                    @click="toggleDropdown(item)"
                >
                    <component :is="item.icon" :size="19" />
                    <span class="flex-1">{{ item.label }}</span>
                    <template v-if="position === 'bottom'">
                        <ChevronDown v-if="dropdownOpen(item)" :size="17" />
                        <ChevronUp v-else :size="17" />
                    </template>
                    <template v-else>
                        <ChevronUp v-if="dropdownOpen(item)" :size="17" />
                        <ChevronDown v-else :size="17" />
                    </template>
                </button>

                <Transition
                    enter-active-class="overflow-hidden transition-[max-height,opacity,transform] duration-300 ease-out"
                    :enter-from-class="position === 'bottom' ? 'max-h-0 translate-y-2 opacity-0' : 'max-h-0 -translate-y-2 opacity-0'"
                    enter-to-class="max-h-96 translate-y-0 opacity-100"
                    leave-active-class="overflow-hidden transition-[max-height,opacity,transform] duration-250 ease-in"
                    leave-from-class="max-h-96 translate-y-0 opacity-100"
                    :leave-to-class="position === 'bottom' ? 'max-h-0 translate-y-2 opacity-0' : 'max-h-0 -translate-y-2 opacity-0'"
                >
                    <div
                        v-if="dropdownOpen(item)"
                        :id="dropdownId(item)"
                        class="max-h-96 space-y-1 rounded-2xl border border-slate-200 bg-white p-2 shadow-xl shadow-slate-200/60"
                        :class="position === 'bottom' ? 'mb-2' : 'mt-2'"
                    >
                        <template v-for="child in item.children" :key="itemKey(child)">
                            <RouterLink
                                v-if="child.permission"
                                v-if-user-can="child.permission"
                                :to="child.path"
                                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition"
                                :class="isPathActive(child.path) ? 'bg-slate-100 text-ink-950' : 'text-slate-600 hover:bg-slate-50 hover:text-ink-950'"
                                @click="emit('navigate')"
                            >
                                <component :is="child.icon" :size="18" />
                                {{ child.label }}
                            </RouterLink>
                            <RouterLink
                                v-else
                                :to="child.path"
                                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition"
                                :class="isPathActive(child.path) ? 'bg-slate-100 text-ink-950' : 'text-slate-600 hover:bg-slate-50 hover:text-ink-950'"
                                @click="emit('navigate')"
                            >
                                <component :is="child.icon" :size="18" />
                                {{ child.label }}
                            </RouterLink>
                        </template>
                    </div>
                </Transition>
            </div>
        </template>
    </div>
</template>

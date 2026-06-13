<script setup>
import { ChevronRight } from '@lucide/vue';

defineOptions({ name: 'PermissionTree' });

const props = defineProps({
    nodes: { type: Array, required: true },
    selected: { type: Array, required: true },
    depth: { type: Number, default: 0 },
});

const emit = defineEmits(['change']);

const descendants = (node) => [
    node.slug,
    ...node.children.flatMap((child) => descendants(child)),
];

const branchSelected = (node) => descendants(node).every((slug) => props.selected.includes(slug));

const toggle = (node) => {
    const branch = descendants(node);
    const next = branchSelected(node)
        ? props.selected.filter((slug) => !branch.includes(slug))
        : [...new Set([...props.selected, ...branch])];

    emit('change', next);
};
</script>

<template>
    <div class="space-y-2">
        <div v-for="node in nodes" :key="node.slug">
            <label
                class="group flex cursor-pointer items-start gap-3 rounded-xl border px-3 py-3 transition"
                :class="selected.includes(node.slug) ? 'border-slate-300 bg-white text-ink-950 shadow-sm' : 'border-transparent text-slate-600 hover:border-slate-200 hover:bg-white'"
                :style="{ marginLeft: `${depth * 1.25}rem` }"
            >
                <input
                    type="checkbox"
                    class="mt-0.5 size-4 shrink-0 rounded border-slate-300 accent-ink-950"
                    :checked="selected.includes(node.slug)"
                    @change="toggle(node)"
                >
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-medium">{{ node.label }}</span>
                    <span class="mt-0.5 block truncate text-xs text-slate-400">{{ node.slug }}</span>
                </span>
                <ChevronRight v-if="node.children.length" :size="16" class="mt-0.5 shrink-0 text-slate-300" />
            </label>

            <PermissionTree
                v-if="node.children.length"
                :nodes="node.children"
                :selected="selected"
                :depth="depth + 1"
                @change="emit('change', $event)"
            />
        </div>
    </div>
</template>

<script setup>
import { shRepo, useStreamline } from '@iankibetsh/sh-core';
import { Bell, LoaderCircle, RotateCcw, X } from '@lucide/vue';
import { computed, onMounted, reactive, ref } from 'vue';
import DashboardLayout from '../components/DashboardLayout.vue';

const { service } = useStreamline('admin/notifications');

const channelOptions = [
    { value: 'database', label: 'In-app' },
    { value: 'mail', label: 'Email' },
    { value: 'sms', label: 'SMS' },
    { value: 'whatsapp', label: 'WhatsApp' },
];
const channelLabels = Object.fromEntries(channelOptions.map((option) => [option.value, option.label]));

const definitions = ref([]);
const loading = ref(true);
const saving = ref(false);
const resetting = ref(false);
const editingSlug = ref(null);
const form = reactive({
    subject: '',
    mail: '',
    sms: '',
    whatsapp: '',
    channels: [],
    action_label: '',
    action_url: '',
});
const placeholders = ref([]);
const isOverridden = ref(false);

const editing = computed(() => definitions.value.find((item) => item.slug === editingSlug.value));

const loadDefinitions = async () => {
    loading.value = true;
    try {
        const data = await service.list();
        definitions.value = data.definitions ?? [];
    } catch (reason) {
        shRepo.showToast(reason.response?.data?.message ?? 'Unable to load notifications', 'error');
    } finally {
        loading.value = false;
    }
};

const openEditor = (definition) => {
    editingSlug.value = definition.slug;
    placeholders.value = definition.placeholders ?? [];
    isOverridden.value = definition.is_overridden;
    Object.assign(form, {
        subject: definition.subject ?? '',
        mail: definition.mail ?? '',
        sms: definition.sms ?? '',
        whatsapp: definition.whatsapp ?? '',
        channels: [...(definition.channels ?? [])],
        action_label: definition.action_label ?? '',
        action_url: definition.action_url ?? '',
    });
};

const closeEditor = () => {
    editingSlug.value = null;
};

const toggleChannel = (value) => {
    const index = form.channels.indexOf(value);
    if (index === -1) {
        form.channels.push(value);
    } else {
        form.channels.splice(index, 1);
    }
};

const save = async () => {
    if (!form.channels.length) {
        shRepo.showToast('Select at least one channel', 'error');
        return;
    }

    saving.value = true;
    try {
        await service.update(editingSlug.value, { ...form });
        shRepo.showToast('Notification updated');
        await loadDefinitions();
        closeEditor();
    } catch (reason) {
        shRepo.showToast(reason.response?.data?.message ?? 'Unable to update notification', 'error');
    } finally {
        saving.value = false;
    }
};

const resetToDefault = async () => {
    resetting.value = true;
    try {
        await service.reset(editingSlug.value);
        shRepo.showToast('Notification reset to default');
        await loadDefinitions();
        closeEditor();
    } catch (reason) {
        shRepo.showToast(reason.response?.data?.message ?? 'Unable to reset notification', 'error');
    } finally {
        resetting.value = false;
    }
};

onMounted(loadDefinitions);
</script>

<template>
    <DashboardLayout>
        <section>
            <div class="mb-8">
                <p class="max-w-2xl leading-7 text-slate-500">
                    Edit the content and channels for each system notification. Edited notifications override the shipped defaults; reset one to restore its default. Use <code class="rounded bg-slate-100 px-1 text-xs">{placeholder}</code> tokens that are filled in when the notification is sent.
                </p>
            </div>

            <div v-if="loading" class="grid min-h-[20rem] place-items-center">
                <div class="text-center text-slate-500">
                    <LoaderCircle :size="28" class="mx-auto animate-spin" />
                    <p class="mt-3 text-sm">Loading notifications...</p>
                </div>
            </div>

            <div v-else class="overflow-hidden rounded-[1.5rem] border border-slate-200 bg-white shadow-[0_24px_70px_-45px_rgba(15,35,30,0.45)]">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3.5 font-semibold">Notification</th>
                            <th class="hidden px-5 py-3.5 font-semibold sm:table-cell">Channels</th>
                            <th class="px-5 py-3.5 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="definition in definitions"
                            :key="definition.slug"
                            class="cursor-pointer transition hover:bg-slate-50"
                            @click="openEditor(definition)"
                        >
                            <td class="px-5 py-4">
                                <p class="font-semibold text-ink-950">{{ definition.subject }}</p>
                                <p class="mt-0.5 text-xs text-slate-400">{{ definition.slug }}</p>
                            </td>
                            <td class="hidden px-5 py-4 sm:table-cell">
                                <div class="flex flex-wrap gap-1.5">
                                    <span
                                        v-for="channel in definition.channels"
                                        :key="channel"
                                        class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600"
                                    >
                                        {{ channelLabels[channel] ?? channel }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <span
                                    class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="definition.is_overridden ? 'bg-brand-50 text-brand-700' : 'bg-slate-100 text-slate-500'"
                                >
                                    {{ definition.is_overridden ? 'Edited' : 'Default' }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0" leave-active-class="transition duration-150" leave-to-class="opacity-0">
            <div v-if="editing" class="fixed inset-0 z-[70] grid place-items-start justify-center overflow-y-auto bg-ink-950/45 p-5 backdrop-blur-sm" @click.self="closeEditor">
                <div class="my-6 w-full max-w-2xl rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl sm:p-8">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex gap-4">
                            <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-slate-100 text-ink-950">
                                <Bell :size="21" />
                            </span>
                            <div>
                                <h2 class="text-xl font-semibold text-ink-950">Edit notification</h2>
                                <p class="mt-1 text-sm leading-6 text-slate-500">{{ editing.slug }}</p>
                            </div>
                        </div>
                        <button type="button" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-ink-950" aria-label="Close" @click="closeEditor">
                            <X :size="19" />
                        </button>
                    </div>

                    <form class="mt-7 space-y-5" @submit.prevent="save">
                        <div v-if="placeholders.length">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Available placeholders</p>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                <span v-for="placeholder in placeholders" :key="placeholder" class="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-700">
                                    {{ '{' + placeholder + '}' }}
                                </span>
                            </div>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-ink-950">Channels</label>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="option in channelOptions"
                                    :key="option.value"
                                    type="button"
                                    class="rounded-xl border px-3.5 py-2 text-sm font-medium transition"
                                    :class="form.channels.includes(option.value)
                                        ? 'border-brand-500 bg-brand-50 text-brand-700'
                                        : 'border-slate-200 text-slate-500 hover:border-slate-300'"
                                    @click="toggleChannel(option.value)"
                                >
                                    {{ option.label }}
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-ink-950">Subject</label>
                            <input v-model="form.subject" type="text" required class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm outline-none transition focus:border-brand-500" />
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-ink-950">Email message</label>
                            <textarea v-model="form.mail" rows="5" required class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm outline-none transition focus:border-brand-500"></textarea>
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-ink-950">SMS message</label>
                                <textarea v-model="form.sms" rows="3" class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm outline-none transition focus:border-brand-500"></textarea>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-ink-950">WhatsApp message</label>
                                <textarea v-model="form.whatsapp" rows="3" class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm outline-none transition focus:border-brand-500"></textarea>
                            </div>
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-ink-950">Action label</label>
                                <input v-model="form.action_label" type="text" class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm outline-none transition focus:border-brand-500" />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-ink-950">Action URL</label>
                                <input v-model="form.action_url" type="text" placeholder="/profile" class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm outline-none transition focus:border-brand-500" />
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-3 pt-2">
                            <button
                                v-if="isOverridden"
                                type="button"
                                :disabled="resetting"
                                class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 disabled:opacity-60"
                                @click="resetToDefault"
                            >
                                <RotateCcw :size="16" :class="resetting ? 'animate-spin' : ''" />
                                Reset to default
                            </button>
                            <span v-else></span>

                            <button
                                type="submit"
                                :disabled="saving"
                                class="inline-flex items-center justify-center gap-2 rounded-xl bg-ink-950 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 disabled:opacity-60"
                            >
                                <LoaderCircle v-if="saving" :size="16" class="animate-spin" />
                                Save changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </DashboardLayout>
</template>

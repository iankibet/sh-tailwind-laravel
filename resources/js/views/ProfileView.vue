<script setup>
import { getAuthStrategy, shRepo, useStreamline, useUserStore } from '@iankibetsh/sh-core';
import { Passkeys, UserCancelledError } from '@laravel/passkeys';
import { ShDialogForm } from '@iankibetsh/sh-tailwind';
import { Fingerprint, KeyRound, Pencil, Phone, Plus, Trash2, UserRound } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import DashboardLayout from '../components/DashboardLayout.vue';
import { syncPasskeyAvailability } from '../lib/rememberedAccount';

const userStore = useUserStore();
const { getActionUrl } = useStreamline('auth/user');
const user = computed(() => userStore.user);
const passkeys = ref([]);
const passkeyName = ref('');
const passkeyLoading = ref(false);
const passkeysSupported = ref(false);
const initials = computed(() => user.value?.name
    ?.split(/\s+/)
    .slice(0, 2)
    .map((part) => part[0])
    .join('')
    .toUpperCase() || 'U');

const profileFields = [
    { name: 'name', label: 'Full name', required: true },
    { name: 'phone', type: 'phone', label: 'Phone number', countryCode: 'KE', required: true },
];

const passwordFields = [
    { name: 'current_password', type: 'password', label: 'Current password', required: true },
    { name: 'password', type: 'password', label: 'New password', helper: 'Use at least 8 characters.', required: true },
    { name: 'password_confirmation', type: 'password', label: 'Confirm new password', required: true },
];

const refreshUser = () => userStore.fetchUser('auth/user:current');
const passkeyRequest = async (url, options = {}) => {
    const response = await fetch(url, {
        ...options,
        headers: {
            Accept: 'application/json',
            Authorization: `Bearer ${getAuthStrategy().getToken()}`,
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            ...options.headers,
        },
    });

    if (!response.ok) {
        const body = await response.json().catch(() => ({}));
        throw new Error(body.message || 'Passkey request failed.');
    }

    return response.json();
};

const loadPasskeys = async () => {
    const response = await passkeyRequest('/user/passkeys');
    passkeys.value = response.passkeys;
    syncPasskeyAvailability(user.value?.email, passkeys.value.length > 0);
};

const addPasskey = async () => {
    const name = passkeyName.value.trim();
    if (!name) return;

    passkeyLoading.value = true;
    try {
        Passkeys.configure({
            fetch: { headers: { Authorization: `Bearer ${getAuthStrategy().getToken()}` } },
        });
        await Passkeys.register({
            name,
            routes: { options: '/user/passkeys/options', submit: '/user/passkeys' },
        });
        passkeyName.value = '';
        await loadPasskeys();
        shRepo.showToast('Passkey added');
    } catch (reason) {
        if (! (reason instanceof UserCancelledError)) {
            shRepo.showToast(reason.message || 'Could not add passkey.', 'error');
        }
    } finally {
        passkeyLoading.value = false;
    }
};

const removePasskey = async (passkey) => {
    if (!window.confirm(`Remove “${passkey.name}”?`)) return;

    await passkeyRequest(`/user/passkeys/${passkey.id}`, { method: 'DELETE' });
    await loadPasskeys();
    shRepo.showToast('Passkey removed');
};

const formatDate = (value) => value
    ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
    : 'Never';

onMounted(() => {
    if (!userStore.user) refreshUser();
    passkeysSupported.value = Passkeys.isSupported();
    loadPasskeys();
});
</script>

<template>
    <DashboardLayout>
        <section>
            <div class="mb-8">
                <p class="max-w-2xl leading-7 text-slate-500">Manage your personal details, password, and passkeys.</p>
            </div>

            <div v-if="user" class="grid gap-6 lg:grid-cols-[minmax(0,1.1fr)_minmax(20rem,0.9fr)]">
                <article class="rounded-[1.5rem] border border-slate-200 bg-white p-6 shadow-[0_24px_70px_-45px_rgba(15,35,30,0.45)] sm:p-8">
                    <div class="flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
                        <div class="flex items-center gap-4">
                            <span class="grid size-16 place-items-center rounded-2xl bg-brand-100 text-lg font-bold text-brand-700">{{ initials }}</span>
                            <div>
                                <h2 class="text-xl font-semibold text-ink-950">{{ user.name }}</h2>
                                <p class="mt-1 text-sm text-slate-500">{{ user.email }}</p>
                                <span class="mt-2 inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold capitalize text-slate-600">{{ user.role.replaceAll('_', ' ') }}</span>
                            </div>
                        </div>

                        <ShDialogForm
                            title="Edit profile"
                            :action="getActionUrl('updateProfile')"
                            :fields="profileFields"
                            :current-data="user"
                            :hidden-id="false"
                            submit-label="Save profile"
                            success-message="Profile updated"
                            btn-class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-ink-950"
                            @success="refreshUser"
                        >
                            <template #trigger><Pencil :size="17" /> Edit profile</template>
                        </ShDialogForm>
                    </div>

                    <dl class="mt-8 divide-y divide-slate-100 border-t border-slate-100">
                        <div class="flex items-center gap-4 py-5">
                            <UserRound :size="19" class="text-slate-400" />
                            <div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Email address</dt><dd class="mt-1 text-sm font-medium text-ink-950">{{ user.email }}</dd></div>
                        </div>
                        <div class="flex items-center gap-4 py-5">
                            <Phone :size="19" class="text-slate-400" />
                            <div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Phone number</dt><dd class="mt-1 text-sm font-medium text-ink-950">{{ user.phone }}</dd></div>
                        </div>
                    </dl>
                </article>

                <article class="rounded-[1.5rem] border border-slate-200 bg-white p-6 sm:p-8">
                    <span class="grid size-11 place-items-center rounded-xl bg-slate-100 text-ink-950"><KeyRound :size="21" /></span>
                    <h2 class="mt-5 text-xl font-semibold text-ink-950">Password</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500">Change your password using your current password for verification.</p>
                    <ShDialogForm
                        title="Change password"
                        :action="getActionUrl('updatePassword')"
                        :fields="passwordFields"
                        :hidden-id="false"
                        submit-label="Change password"
                        success-message="Password updated"
                        btn-class="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-ink-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800"
                    >
                        <template #trigger><KeyRound :size="17" /> Change password</template>
                    </ShDialogForm>
                </article>

                <article class="rounded-[1.5rem] border border-slate-200 bg-white p-6 sm:p-8 lg:col-span-2">
                    <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                        <div class="flex gap-4">
                            <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-700"><Fingerprint :size="22" /></span>
                            <div>
                                <h2 class="text-xl font-semibold text-ink-950">Passkeys</h2>
                                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Use Face ID, fingerprint, Windows Hello, or a security key to sign in without entering your password.</p>
                            </div>
                        </div>

                        <form v-if="passkeysSupported" class="flex w-full gap-2 sm:max-w-md" @submit.prevent="addPasskey">
                            <input v-model="passkeyName" class="min-w-0 flex-1 rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/10" placeholder="e.g. My MacBook" maxlength="255">
                            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-ink-950 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60" :disabled="passkeyLoading || !passkeyName.trim()">
                                <Plus :size="17" /> {{ passkeyLoading ? 'Adding...' : 'Add' }}
                            </button>
                        </form>
                    </div>

                    <p v-if="!passkeysSupported" class="mt-5 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800">This browser does not support passkeys.</p>

                    <div v-else-if="passkeys.length" class="mt-6 divide-y divide-slate-100 rounded-2xl border border-slate-200">
                        <div v-for="passkey in passkeys" :key="passkey.id" class="flex items-center gap-4 px-4 py-4 sm:px-5">
                            <Fingerprint :size="20" class="shrink-0 text-brand-600" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-ink-950">{{ passkey.name }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ passkey.authenticator || 'Passkey' }} · Last used {{ formatDate(passkey.last_used_at) }}</p>
                            </div>
                            <button type="button" class="rounded-lg p-2 text-slate-400 transition hover:bg-red-50 hover:text-red-600" :aria-label="`Remove ${passkey.name}`" @click="removePasskey(passkey)">
                                <Trash2 :size="18" />
                            </button>
                        </div>
                    </div>
                    <p v-else class="mt-6 rounded-2xl border border-dashed border-slate-300 px-5 py-8 text-center text-sm text-slate-500">No passkeys added yet.</p>
                </article>
            </div>
        </section>
    </DashboardLayout>
</template>

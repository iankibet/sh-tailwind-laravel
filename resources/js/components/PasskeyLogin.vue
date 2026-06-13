<script setup>
import { Passkeys, UserCancelledError } from '@laravel/passkeys';
import { Fingerprint } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { getRememberedEmail, hasRememberedPasskey } from '../lib/rememberedAccount';

const emit = defineEmits(['success']);
const supported = ref(false);
const loading = ref(false);
const error = ref('');
const rememberedEmail = getRememberedEmail();
const shouldOfferPasskey = rememberedEmail && hasRememberedPasskey(rememberedEmail);

const routes = {
    options: '/passkeys/login/options',
    submit: '/passkeys/login',
};

const complete = (response) => {
    if (response?.token) {
        emit('success', response);
    }
};

const verify = async () => {
    loading.value = true;
    error.value = '';

    try {
        Passkeys.configure({ fetch: { headers: { Authorization: '' } } });
        complete(await Passkeys.verify({ routes }));
    } catch (reason) {
        if (! (reason instanceof UserCancelledError)) {
            error.value = reason.message || 'Passkey sign-in failed.';
        }
    } finally {
        loading.value = false;
    }
};

onMounted(async () => {
    supported.value = Passkeys.isSupported();
    if (!supported.value || !shouldOfferPasskey) return;

    Passkeys.configure({ fetch: { headers: { Authorization: '' } } });
    try {
        complete(await Passkeys.autofill({ routes }));
    } catch {
        // The explicit button remains available when autofill is dismissed.
    }
});

onBeforeUnmount(() => Passkeys.cancel());
</script>

<template>
    <div v-if="supported && shouldOfferPasskey" class="mb-6">
        <button
            type="button"
            class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-ink-950 transition hover:border-brand-500 hover:bg-brand-50 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="loading"
            @click="verify"
        >
            <Fingerprint :size="19" />
            {{ loading ? 'Checking passkey...' : `Continue as ${rememberedEmail}` }}
        </button>
        <p v-if="error" class="mt-2 text-sm text-red-600">{{ error }}</p>
        <div class="my-6 flex items-center gap-4 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
            <span class="h-px flex-1 bg-slate-200" />
            Or use another method
            <span class="h-px flex-1 bg-slate-200" />
        </div>
    </div>
</template>

<script setup>
import { shApis, shRepo } from '@iankibetsh/sh-core';
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { loadScript } from '../lib/loadScript';

const props = defineProps({
    context: { type: String, default: 'signin' },
});

const emit = defineEmits(['success']);
const button = ref(null);
const submitting = ref(false);
const clientId = import.meta.env.VITE_GOOGLE_CLIENT_ID;

let activeCredentialHandler = null;
let initialized = false;

const receiveCredential = async ({ credential }) => {
    if (!credential || submitting.value) {
        return;
    }

    submitting.value = true;
    try {
        const response = await shApis.doPost('auth/guest:google', { credential });
        emit('success', response.data);
    } catch (error) {
        const message = error.response?.data?.errors?.credential?.[0]
            ?? error.response?.data?.message
            ?? 'Google sign-in failed. Please try again.';
        shRepo.showToast(message, 'error');
    } finally {
        submitting.value = false;
    }
};

onMounted(async () => {
    if (!clientId) {
        return;
    }

    try {
        await loadScript('https://accounts.google.com/gsi/client');
        await nextTick();
        activeCredentialHandler = receiveCredential;

        if (!initialized) {
            window.google.accounts.id.initialize({
                client_id: clientId,
                callback: (response) => activeCredentialHandler?.(response),
                auto_select: false,
                cancel_on_tap_outside: true,
            });
            initialized = true;
        }

        window.google.accounts.id.renderButton(button.value, {
            type: 'standard',
            theme: 'outline',
            size: 'large',
            shape: 'rectangular',
            text: props.context === 'signup' ? 'signup_with' : 'signin_with',
            width: Math.min(button.value.clientWidth || 360, 400),
        });
        window.google.accounts.id.prompt();
    } catch {
        shRepo.showToast('Google sign-in could not load.', 'error');
    }
});

onBeforeUnmount(() => {
    if (activeCredentialHandler === receiveCredential) {
        activeCredentialHandler = null;
    }
    window.google?.accounts?.id?.cancel();
});
</script>

<template>
    <div v-if="clientId" class="mb-6">
        <div ref="button" class="min-h-10 w-full" :class="{ 'pointer-events-none opacity-60': submitting }" />
        <div class="my-6 flex items-center gap-4 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
            <span class="h-px flex-1 bg-slate-200" />
            Or continue with email
            <span class="h-px flex-1 bg-slate-200" />
        </div>
    </div>
</template>

<script setup>
import { getAuthStrategy, useUserStore } from '@iankibetsh/sh-core';
import { ShForm } from '@iankibetsh/sh-tailwind';
import { useRouter } from 'vue-router';
import AuthLayout from '../components/AuthLayout.vue';
import GoogleAuth from '../components/GoogleAuth.vue';
import { withRecaptcha } from '../lib/recaptcha';
import { rememberEmail, syncPasskeyAvailability } from '../lib/rememberedAccount';

const router = useRouter();
const userStore = useUserStore();

const fields = [
    { name: 'name', label: 'Full name', placeholder: 'Your full name', required: true },
    { name: 'email', type: 'email', label: 'Email address', placeholder: 'you@example.com', required: true },
    { name: 'phone', type: 'phone', label: 'Phone number', countryCode: 'KE', required: true },
    { name: 'password', type: 'password', label: 'Password', helper: 'Use at least 8 characters.', required: true },
    { name: 'password_confirmation', type: 'password', label: 'Confirm password', required: true },
];

const registered = async (response) => {
    rememberEmail(response.user?.email);
    syncPasskeyAvailability(response.user?.email, response.has_passkeys);
    getAuthStrategy().setToken(response.token);
    await userStore.fetchUser('auth/user:current');
    await router.replace('/users');
};

const addRecaptcha = (data) => withRecaptcha(data, 'register');
</script>

<template>
    <AuthLayout
        eyebrow="Create account"
        title="Join the workspace"
        description="Register once, then continue directly to the community directory."
    >
        <GoogleAuth context="signup" @success="registered" />

        <ShForm
            action="auth/guest:register"
            :fields="fields"
            :pre-submit="addRecaptcha"
            submit-label="Create account"
            retain-data
            @success="registered"
        />

        <template #footer>
            Already registered?
            <RouterLink to="/login" class="font-semibold text-brand-700 hover:text-brand-600">Sign in</RouterLink>
        </template>
    </AuthLayout>
</template>

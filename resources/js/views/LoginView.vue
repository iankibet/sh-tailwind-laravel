<script setup>
import { getAuthStrategy, useUserStore } from '@iankibetsh/sh-core';
import { ShForm } from '@iankibetsh/sh-tailwind';
import { useRoute, useRouter } from 'vue-router';
import AuthLayout from '../components/AuthLayout.vue';
import GoogleAuth from '../components/GoogleAuth.vue';
import PasskeyLogin from '../components/PasskeyLogin.vue';
import { withRecaptcha } from '../lib/recaptcha';
import { getRememberedEmail, rememberEmail, syncPasskeyAvailability } from '../lib/rememberedAccount';

const route = useRoute();
const router = useRouter();
const userStore = useUserStore();

const fields = [
    { name: 'email', type: 'email', label: 'Email address', placeholder: 'you@example.com', required: true, value: getRememberedEmail(), props: { autocomplete: 'email webauthn' } },
    { name: 'password', type: 'password', label: 'Password', placeholder: 'Enter your password', required: true },
];

const loggedIn = async (response) => {
    rememberEmail(response.user?.email);
    syncPasskeyAvailability(response.user?.email, response.has_passkeys);
    getAuthStrategy().setToken(response.token);
    await userStore.fetchUser('auth/user:current');
    await router.replace(route.query.redirect || '/users');
};

const addRecaptcha = (data) => withRecaptcha(data, 'login');
</script>

<template>
    <AuthLayout
        eyebrow="Welcome back"
        title="Sign in to continue"
        description="Use your account details to open the registered users workspace."
    >
        <PasskeyLogin @success="loggedIn" />
        <GoogleAuth context="signin" @success="loggedIn" />

        <ShForm
            action="auth/guest:login"
            :fields="fields"
            :pre-submit="addRecaptcha"
            submit-label="Sign in"
            retain-data
            @success="loggedIn"
        />

        <template #footer>
            New to Pius Videos?
            <RouterLink to="/register" class="font-semibold text-brand-700 hover:text-brand-600">Create an account</RouterLink>
        </template>
    </AuthLayout>
</template>

<script setup>
import { useStreamline } from '@iankibetsh/sh-core';
import { ShDialogForm } from '@iankibetsh/sh-tailwind';
import { ArrowLeft, Building2, KeyRound, LoaderCircle, Mail, Pencil, Phone, ShieldCheck } from '@lucide/vue';
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import DashboardLayout from '../components/DashboardLayout.vue';

const route = useRoute();
const { getActionUrl, loading, props } = useStreamline('admin/admins', Number(route.params.id));
const admin = computed(() => props.user);
const departments = computed(() => props.departments ?? []);

const initials = computed(() => admin.value?.name.split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase());
const detailFields = computed(() => [
    { name: 'name', label: 'Full name', required: true },
    { name: 'email', type: 'email', label: 'Email address', required: true },
    { name: 'phone', type: 'phone', label: 'Phone number', countryCode: 'KE', required: true },
    { name: 'department_id', type: 'select', label: 'Department', placeholder: 'Select department', options: departments.value },
]);
const passwordFields = [
    { name: 'password', type: 'password', label: 'New password', helper: 'Use at least 8 characters.', required: true },
    { name: 'password_confirmation', type: 'password', label: 'Confirm password', required: true },
];

const adminUpdated = (response) => { props.user = response.user; };
</script>

<template>
    <DashboardLayout :breadcrumb-label="admin?.name">
        <div v-if="loading || !admin" class="grid min-h-[30rem] place-items-center text-slate-500"><LoaderCircle :size="28" class="animate-spin" /></div>
        <section v-else>
            <RouterLink to="/admins" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 hover:text-ink-950"><ArrowLeft :size="17" /> Back to administrators</RouterLink>

            <div class="mt-7 grid gap-6 lg:grid-cols-[minmax(0,1.1fr)_minmax(20rem,0.9fr)]">
                <article class="rounded-[1.5rem] border border-slate-200 bg-white p-6 shadow-[0_24px_70px_-45px_rgba(15,35,30,0.45)] sm:p-8">
                    <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                        <div class="flex items-center gap-4">
                            <span class="grid size-16 place-items-center rounded-2xl bg-brand-100 text-lg font-bold text-brand-700">{{ initials }}</span>
                            <h1 class="text-3xl font-semibold text-ink-950">{{ admin.name }}</h1>
                        </div>
                        <ShDialogForm title="Edit administrator" :action="getActionUrl('saveDetails')" :fields="detailFields" :current-data="admin" submit-label="Save changes" success-message="Administrator updated" btn-class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:border-slate-400" @success="adminUpdated">
                            <template #trigger><Pencil :size="17" /> Edit details</template>
                        </ShDialogForm>
                    </div>

                    <dl class="mt-8 divide-y divide-slate-100 border-t border-slate-100">
                        <div class="flex items-center gap-4 py-5"><Mail :size="19" class="text-slate-400" /><div><dt class="text-xs uppercase tracking-wider text-slate-400">Email</dt><dd class="mt-1 text-sm font-medium text-ink-950">{{ admin.email }}</dd></div></div>
                        <div class="flex items-center gap-4 py-5"><Phone :size="19" class="text-slate-400" /><div><dt class="text-xs uppercase tracking-wider text-slate-400">Phone</dt><dd class="mt-1 text-sm font-medium text-ink-950">{{ admin.phone }}</dd></div></div>
                        <div class="flex items-center gap-4 py-5"><Building2 :size="19" class="text-slate-400" /><div><dt class="text-xs uppercase tracking-wider text-slate-400">Department</dt><dd class="mt-1 text-sm font-medium text-ink-950">{{ admin.department?.name || 'Not assigned' }}</dd></div></div>
                        <div class="flex items-center gap-4 py-5"><ShieldCheck :size="19" class="text-slate-400" /><div><dt class="text-xs uppercase tracking-wider text-slate-400">Role</dt><dd class="mt-1 text-sm font-medium capitalize text-ink-950">{{ admin.role.replaceAll('_', ' ') }}</dd></div></div>
                    </dl>
                </article>

                <article class="rounded-[1.5rem] border border-slate-200 bg-white p-6 sm:p-8">
                    <span class="grid size-11 place-items-center rounded-xl bg-slate-100 text-ink-950"><KeyRound :size="21" /></span>
                    <h2 class="mt-5 text-xl font-semibold text-ink-950">Reset password</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500">Set a new password for this administrator. Their current password is not required.</p>
                    <ShDialogForm title="Reset administrator password" :action="getActionUrl('resetPassword')" :fields="passwordFields" :current-data="{ id: admin.id }" submit-label="Update password" success-message="Administrator password updated" btn-class="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-ink-950 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800">
                        <template #trigger><KeyRound :size="17" /> Reset password</template>
                    </ShDialogForm>
                </article>
            </div>
        </section>
    </DashboardLayout>
</template>

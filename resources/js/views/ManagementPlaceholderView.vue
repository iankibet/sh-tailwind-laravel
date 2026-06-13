<script setup>
import { ArrowRight, Construction } from '@lucide/vue';
import DashboardLayout from '../components/DashboardLayout.vue';

defineProps({
    description: { type: String, required: true },
});
</script>

<template>
    <DashboardLayout>
        <div class="mb-8">
            <p class="max-w-2xl leading-7 text-slate-500">{{ description }}</p>
        </div>

        <section class="grid min-h-[26rem] place-items-center rounded-[1.5rem] border border-slate-200 bg-white p-8 text-center shadow-[0_24px_70px_-45px_rgba(15,35,30,0.45)]">
            <div class="max-w-md">
                <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-brand-50 text-brand-700">
                    <Construction :size="26" />
                </span>
                <h2 class="mt-5 text-2xl font-semibold tracking-tight text-ink-950">Coming soon</h2>
                <p class="mt-3 leading-7 text-slate-500">
                    This workspace is ready for the next phase of the Pius Videos administration tools.
                </p>
                <RouterLink to="/users" class="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-brand-700 hover:text-brand-600">
                    Return to users
                    <ArrowRight :size="17" />
                </RouterLink>
            </div>
        </section>
    </DashboardLayout>
</template>

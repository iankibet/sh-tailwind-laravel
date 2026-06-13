<script setup>
import { onBeforeUnmount, onMounted } from 'vue';

defineProps({
    eyebrow: { type: String, required: true },
    title: { type: String, required: true },
    description: { type: String, required: true },
});

onMounted(() => document.body.classList.add('guest-auth'));
onBeforeUnmount(() => document.body.classList.remove('guest-auth'));
</script>

<template>
    <main class="relative min-h-screen overflow-hidden bg-ink-950 px-5 py-8 sm:px-8 lg:grid lg:grid-cols-[1.05fr_0.95fr] lg:p-0">
        <div class="pointer-events-none absolute -left-36 top-20 size-96 rounded-full bg-brand-500/20 blur-3xl" />
        <div class="pointer-events-none absolute bottom-0 right-0 size-[30rem] rounded-full bg-amber-300/10 blur-3xl" />

        <section class="relative hidden min-h-screen flex-col justify-between p-14 text-white lg:flex">
            <RouterLink to="/" class="flex items-center gap-3 text-lg font-semibold tracking-tight">
                <span class="grid size-10 place-items-center rounded-xl bg-brand-500 text-sm font-black text-white">PV</span>
                Pius Videos
            </RouterLink>

            <div class="max-w-xl pb-10">
                <p class="mb-5 text-xs font-bold uppercase tracking-[0.28em] text-brand-100">Creator operations</p>
                <h2 class="text-5xl font-semibold leading-[1.08] tracking-[-0.04em]">
                    A calmer place to manage your video community.
                </h2>
                <p class="mt-6 max-w-lg text-lg leading-8 text-slate-300">
                    Keep your registered audience visible, organized, and ready for whatever you publish next.
                </p>
            </div>

            <p class="text-sm text-slate-500">Built with Streamline and sh-tailwind.</p>
        </section>

        <section class="relative flex min-h-[calc(100vh-4rem)] items-center justify-center rounded-[2rem] bg-[#f7faf8] px-5 py-10 shadow-2xl lg:min-h-screen lg:rounded-none lg:px-12">
            <div class="w-full max-w-md">
                <RouterLink to="/" class="mb-10 flex items-center gap-3 text-lg font-semibold tracking-tight text-ink-950 lg:hidden">
                    <span class="grid size-10 place-items-center rounded-xl bg-brand-600 text-sm font-black text-white">PV</span>
                    Pius Videos
                </RouterLink>

                <p class="text-xs font-bold uppercase tracking-[0.22em] text-brand-600">{{ eyebrow }}</p>
                <h1 class="mt-3 text-4xl font-semibold tracking-[-0.04em] text-ink-950">{{ title }}</h1>
                <p class="mt-3 leading-7 text-slate-500">{{ description }}</p>

                <div class="mt-9 rounded-2xl border border-slate-200 bg-white p-6 shadow-[0_20px_60px_-35px_rgba(15,35,30,0.45)] sm:p-8">
                    <slot />
                </div>

                <div class="mt-7 text-center text-sm text-slate-500">
                    <slot name="footer" />
                </div>
            </div>
        </section>
    </main>
</template>

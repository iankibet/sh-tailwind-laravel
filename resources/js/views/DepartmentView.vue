<script setup>
import { shRepo, useStreamline } from '@iankibetsh/sh-core';
import { ShDialogForm } from '@iankibetsh/sh-tailwind';
import { ArrowLeft, Check, LoaderCircle, Pencil, Save, Search } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import DashboardLayout from '../components/DashboardLayout.vue';
import PermissionTree from '../components/PermissionTree.vue';

const route = useRoute();
const { getActionUrl, loading, props, service } = useStreamline('admin/departments', Number(route.params.id));
const department = computed(() => props.department);
const modules = computed(() => props.modules ?? []);
const selected = ref({});
const savedSelected = ref({});
const activeModuleSlug = ref('');
const moduleSearch = ref('');
const savingModule = ref('');

const departmentFields = [
    { name: 'name', label: 'Department name', required: true },
    { name: 'description', type: 'textarea', label: 'Description', rows: 4 },
];

const activeModule = computed(() => modules.value.find((module) => module.module === activeModuleSlug.value));
const visibleModules = computed(() => {
    const query = moduleSearch.value.trim().toLowerCase();
    return query ? modules.value.filter((module) => module.label.toLowerCase().includes(query)) : modules.value;
});
const activePermissions = computed(() => selected.value[activeModuleSlug.value] ?? []);
const activeSavedPermissions = computed(() => savedSelected.value[activeModuleSlug.value] ?? []);
const activeDirty = computed(() => JSON.stringify([...activePermissions.value].sort()) !== JSON.stringify([...activeSavedPermissions.value].sort()));

watch(
    () => [props.department, props.modules],
    ([loadedDepartment, loadedModules]) => {
        if (!loadedDepartment || !loadedModules?.length) return;
        const initialSelection = Object.fromEntries(modules.value.map((module) => [module.module, []]));

        loadedDepartment.permissions.forEach((permission) => {
            initialSelection[permission.module] = [...permission.permissions];
        });

        selected.value = structuredClone(initialSelection);
        savedSelected.value = structuredClone(initialSelection);
        activeModuleSlug.value = modules.value[0]?.module ?? '';
    },
    { immediate: true },
);

const moduleSelected = (module) => selected.value[module.module]?.length === module.permissions.length && module.permissions.length > 0;

const toggleModule = (module) => {
    selected.value[module.module] = moduleSelected(module) ? [] : [...module.permissions];
};

const setActivePermissions = (permissions) => {
    selected.value[activeModuleSlug.value] = permissions;
};

const departmentUpdated = (response) => {
    props.department = {
        ...props.department,
        ...response.department,
    };
};

const moduleDirty = (module) => JSON.stringify([...(selected.value[module] ?? [])].sort()) !== JSON.stringify([...(savedSelected.value[module] ?? [])].sort());

const saveActiveModule = async () => {
    if (!activeModule.value) return;

    savingModule.value = activeModuleSlug.value;

    try {
        await service.updateModulePermissions(activeModuleSlug.value, {
            permissions: activePermissions.value,
        });

        savedSelected.value[activeModuleSlug.value] = [...activePermissions.value];
        shRepo.showToast(`${activeModule.value.label} permissions updated`);
    } catch (reason) {
        shRepo.showToast(reason.response?.data?.message ?? 'Unable to update permissions', 'error');
    } finally {
        savingModule.value = '';
    }
};

</script>

<template>
    <DashboardLayout :breadcrumb-label="department?.name">
        <div v-if="loading || !department" class="grid min-h-[30rem] place-items-center">
            <div class="text-center text-slate-500">
                <LoaderCircle :size="28" class="mx-auto animate-spin" />
                <p class="mt-3 text-sm">Loading department...</p>
            </div>
        </div>

        <section v-else>
            <RouterLink to="/departments" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 transition hover:text-ink-950">
                <ArrowLeft :size="17" />
                Back to departments
            </RouterLink>

            <div class="mt-6 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-3xl font-semibold tracking-[-0.03em] text-ink-950">{{ department.name }}</h1>
                    <p class="mt-3 max-w-2xl leading-7 text-slate-500">{{ department.description || 'No department description has been added.' }}</p>
                </div>
                <ShDialogForm
                    title="Edit department"
                    :action="getActionUrl('updateDetails')"
                    :fields="departmentFields"
                    :current-data="department"
                    submit-label="Save changes"
                    success-message="Department details updated"
                    btn-class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-ink-950"
                    @success="departmentUpdated"
                >
                    <template #trigger>
                        <Pencil :size="17" />
                        Edit department
                    </template>
                </ShDialogForm>
            </div>

            <div class="mt-7 overflow-hidden rounded-[1.5rem] border border-slate-200 bg-white shadow-[0_24px_70px_-45px_rgba(15,35,30,0.45)] lg:grid lg:grid-cols-[17rem_minmax(0,1fr)]">
                <aside class="border-b border-slate-200 bg-slate-50/70 p-4 lg:min-h-[34rem] lg:border-b-0 lg:border-r">
                    <div class="px-2 pb-4">
                        <h2 class="font-semibold text-ink-950">Permission modules</h2>
                        <p class="mt-1 text-xs leading-5 text-slate-500">Choose one module to configure.</p>
                    </div>

                    <label class="relative mb-3 block">
                        <Search :size="16" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                        <input v-model="moduleSearch" type="search" placeholder="Find a module" class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-9 pr-3 text-sm outline-none transition focus:border-slate-400">
                    </label>

                    <nav class="flex gap-2 overflow-x-auto pb-1 lg:block lg:space-y-1 lg:overflow-visible" aria-label="Permission modules">
                        <button
                            v-for="module in visibleModules"
                            :key="module.module"
                            type="button"
                            class="min-w-48 rounded-xl px-3 py-3 text-left transition lg:min-w-0 lg:w-full"
                            :class="activeModuleSlug === module.module ? 'bg-white text-ink-950 shadow-sm ring-1 ring-slate-200' : 'text-slate-600 hover:bg-white/80 hover:text-ink-950'"
                            @click="activeModuleSlug = module.module"
                        >
                            <span class="flex items-center justify-between gap-3">
                                <span class="truncate text-sm font-semibold">{{ module.label }}</span>
                                <span v-if="moduleDirty(module.module)" class="size-2 shrink-0 rounded-full bg-amber-500" title="Unsaved changes" />
                                <Check v-else-if="selected[module.module]?.length" :size="15" class="shrink-0 text-brand-600" />
                            </span>
                            <span class="mt-1 block text-xs text-slate-400">{{ selected[module.module]?.length || 0 }} / {{ module.permissions.length }} allowed</span>
                        </button>
                    </nav>
                </aside>

                <section v-if="activeModule" class="p-5 sm:p-7">
                    <div class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Module access</p>
                            <h2 class="mt-1 text-xl font-semibold text-ink-950">{{ activeModule.label }}</h2>
                            <p class="mt-1 text-sm text-slate-500">Choose the actions this department can perform in this module.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-xs font-semibold text-slate-600 transition hover:border-slate-400"
                                @click="toggleModule(activeModule)"
                            >
                                {{ moduleSelected(activeModule) ? 'Clear all' : 'Select all' }}
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-xl bg-ink-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="!activeDirty || savingModule === activeModule.module"
                                @click="saveActiveModule"
                            >
                                <LoaderCircle v-if="savingModule === activeModule.module" :size="17" class="animate-spin" />
                                <Save v-else :size="17" />
                                {{ savingModule === activeModule.module ? 'Saving...' : 'Save module' }}
                            </button>
                        </div>
                    </div>

                    <div class="mt-6 rounded-2xl bg-slate-50 p-3 sm:p-4">
                        <PermissionTree
                            :nodes="activeModule.tree"
                            :selected="activePermissions"
                            @change="setActivePermissions"
                        />
                    </div>
                </section>

                <div v-else class="grid min-h-80 place-items-center p-8 text-center text-sm text-slate-500">
                    Select a permission module to begin.
                </div>
            </div>
        </section>
    </DashboardLayout>
</template>

<script setup>
import { ShForm, ShTable } from '@iankibetsh/sh-tailwind';
import { Building2, Plus, X } from '@lucide/vue';
import { ref } from 'vue';
import DashboardLayout from '../components/DashboardLayout.vue';

const createOpen = ref(false);
const tableReload = ref(0);

const columns = [
    { name: 'name', label: 'Department' },
    { name: 'description', label: 'Description' },
    { name: 'users_count', label: 'Users', format: 'number' },
    { name: 'permissions_count', label: 'Modules', format: 'number' },
    { name: 'created_at', label: 'Created', format: 'date' },
];

const fields = [
    { name: 'name', label: 'Department name', placeholder: 'e.g. Editorial', required: true },
    {
        name: 'description',
        type: 'textarea',
        label: 'Description',
        placeholder: 'What does this department manage?',
        rows: 4,
    },
];

const departmentCreated = () => {
    createOpen.value = false;
    tableReload.value++;
};
</script>

<template>
    <DashboardLayout>
        <section>
            <div class="mb-8 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="max-w-2xl leading-7 text-slate-500">
                        Group administrators by responsibility and control the permissions available to each team.
                    </p>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-ink-950 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800"
                    @click="createOpen = true"
                >
                    <Plus :size="18" />
                    Add department
                </button>
            </div>

            <div class="rounded-[1.5rem] border border-slate-200 bg-white p-4 shadow-[0_24px_70px_-45px_rgba(15,35,30,0.45)] sm:p-6">
                <ShTable
                    endpoint="admin/departments:list"
                    :columns="columns"
                    :reload="tableReload"
                    row-link="/departments/{id}"
                    search-placeholder="Search departments"
                    sort-by="created_at"
                    sort-method="desc"
                    empty-message="No departments have been added yet."
                    :cache="false"
                />
            </div>
        </section>

        <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0" leave-active-class="transition duration-150" leave-to-class="opacity-0">
            <div v-if="createOpen" class="fixed inset-0 z-[70] grid place-items-center bg-ink-950/45 p-5 backdrop-blur-sm" @click.self="createOpen = false">
                <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl sm:p-8">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex gap-4">
                            <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-slate-100 text-ink-950">
                                <Building2 :size="21" />
                            </span>
                            <div>
                                <h2 class="text-xl font-semibold text-ink-950">New department</h2>
                                <p class="mt-1 text-sm leading-6 text-slate-500">Create the department first, then select its permissions.</p>
                            </div>
                        </div>
                        <button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-ink-950" aria-label="Close" @click="createOpen = false">
                            <X :size="19" />
                        </button>
                    </div>

                    <div class="mt-7">
                        <ShForm
                            action="admin/departments:create"
                            :fields="fields"
                            submit-label="Create department"
                            success-message="Department created"
                            @success="departmentCreated"
                        />
                    </div>
                </div>
            </div>
        </Transition>
    </DashboardLayout>
</template>

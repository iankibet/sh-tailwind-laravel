<script setup>
import { ShTable } from '@iankibetsh/sh-tailwind';
import DashboardLayout from '../components/DashboardLayout.vue';

const columns = [
    { name: 'name', label: 'Name' },
    { name: 'email', label: 'Email address' },
    { name: 'phone', label: 'Phone' },
    { name: 'role', label: 'Role' },
    { name: 'created_at', label: 'Joined', format: 'date' },
];

</script>

<template>
    <DashboardLayout>
        <section>
            <div class="mb-8 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="max-w-2xl leading-7 text-slate-500">
                        Search, sort, and review everyone who has joined Pius Videos.
                    </p>
                </div>
                <div class="rounded-2xl border border-brand-100 bg-brand-50 px-4 py-3 text-sm text-brand-700">
                    Live directory
                </div>
            </div>

            <div class="rounded-[1.5rem] border border-slate-200 bg-white p-4 shadow-[0_24px_70px_-45px_rgba(15,35,30,0.45)] sm:p-6">
                <ShTable
                    endpoint="admin/users:list"
                    :columns="columns"
                    search-placeholder="Search by name, email, phone, or role"
                    sort-by="created_at"
                    sort-method="desc"
                    empty-message="No registered users yet."
                    cache
                />
            </div>
        </section>
    </DashboardLayout>
</template>

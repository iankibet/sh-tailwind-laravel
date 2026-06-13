<script setup>
import { ShTable } from '@iankibetsh/sh-tailwind';
import DashboardLayout from '../components/DashboardLayout.vue';

const columns = [
    { name: 'created_at', label: 'Time', format: 'date', sortable: false },
    { name: 'user', label: 'User', sortable: false },
    { name: 'slug', label: 'Event', sortable: false },
    { name: 'log', label: 'Activity', sortable: false },
    { name: 'ip_address', label: 'IP address', sortable: false },
    { name: 'device', label: 'Device', sortable: false },
];
</script>

<template>
    <DashboardLayout>
        <section>
            <div class="mb-8">
                <p class="max-w-2xl leading-7 text-slate-500">
                    Review sign-ins and administrative changes across the workspace.
                </p>
            </div>

            <div class="rounded-[1.5rem] border border-slate-200 bg-white p-4 shadow-[0_24px_70px_-45px_rgba(15,35,30,0.45)] sm:p-6">
                <ShTable
                    endpoint="admin/access-logs:list"
                    :columns="columns"
                    search-placeholder="Search activity, users, devices, or IP addresses"
                    sort-by="logs.created_at"
                    sort-method="desc"
                    empty-message="No access activity has been recorded yet."
                    :cache="false"
                />
            </div>
        </section>
    </DashboardLayout>
</template>

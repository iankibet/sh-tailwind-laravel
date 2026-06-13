<script setup>
import { ShTable } from '@iankibetsh/sh-tailwind';
import DashboardLayout from '../components/DashboardLayout.vue';

const columns = [
    { name: 'name', label: 'Name', sortable: false },
    { name: 'email', label: 'Email address', sortable: false },
    { name: 'phone', label: 'Phone', sortable: false },
    { name: 'department', label: 'Department', sortable: false },
    { name: 'role', label: 'Role', sortable: false },
    { name: 'created_at', label: 'Joined', format: 'date', sortable: false },
];
</script>

<template>
    <DashboardLayout>
        <section>
            <div class="mb-8">
                <p class="max-w-2xl leading-7 text-slate-500">Review administrator accounts, departments, and contact details.</p>
            </div>

            <div class="rounded-[1.5rem] border border-slate-200 bg-white p-4 shadow-[0_24px_70px_-45px_rgba(15,35,30,0.45)] sm:p-6">
                <ShTable
                    endpoint="admin/admins:list"
                    :columns="columns"
                    row-link="/admins/{id}"
                    search-placeholder="Search administrators"
                    sort-by="users.created_at"
                    sort-method="desc"
                    empty-message="No administrators found."
                    :cache="false"
                />
            </div>
        </section>
    </DashboardLayout>
</template>

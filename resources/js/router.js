import { getAuthStrategy } from '@iankibetsh/sh-core';
import { createRouter, createWebHistory } from 'vue-router';
import AccessLogsView from './views/AccessLogsView.vue';
import LoginView from './views/LoginView.vue';
import AdminView from './views/AdminView.vue';
import AdminsView from './views/AdminsView.vue';
import AllNotificationsView from './views/AllNotificationsView.vue';
import DepartmentView from './views/DepartmentView.vue';
import DepartmentsView from './views/DepartmentsView.vue';
import NotificationsView from './views/NotificationsView.vue';
import RegisterView from './views/RegisterView.vue';
import ProfileView from './views/ProfileView.vue';
import UsersView from './views/UsersView.vue';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', redirect: '/users' },
        { path: '/login', name: 'login', component: LoginView, meta: { guest: true } },
        { path: '/register', name: 'register', component: RegisterView, meta: { guest: true } },
        { path: '/users', name: 'users', component: UsersView, meta: { auth: true, title: 'Users' } },
        { path: '/profile', name: 'profile', component: ProfileView, meta: { auth: true, title: 'Your profile' } },
        {
            path: '/admins',
            name: 'admins',
            component: AdminsView,
            meta: { auth: true, title: 'Admins' },
        },
        {
            path: '/admins/:id',
            name: 'admin',
            component: AdminView,
            meta: { auth: true, title: 'Administrator details', breadcrumbs: [{ label: 'Admins', to: '/admins' }] },
        },
        {
            path: '/access-logs',
            name: 'access-logs',
            component: AccessLogsView,
            meta: { auth: true, title: 'Access logs' },
        },
        {
            path: '/departments',
            name: 'departments',
            component: DepartmentsView,
            meta: { auth: true, title: 'Departments' },
        },
        {
            path: '/departments/:id',
            name: 'department',
            component: DepartmentView,
            meta: { auth: true, title: 'Department permissions', breadcrumbs: [{ label: 'Departments', to: '/departments' }] },
        },
        {
            path: '/notifications',
            name: 'notifications',
            component: NotificationsView,
            meta: { auth: true, title: 'Notifications' },
        },
        {
            path: '/notifications/all',
            name: 'notifications-all',
            component: AllNotificationsView,
            meta: { auth: true, title: 'Your notifications' },
        },
        { path: '/:pathMatch(.*)*', redirect: '/users' },
    ],
});

router.beforeEach((to) => {
    const authenticated = getAuthStrategy().isAuthenticated();

    if (to.meta.auth && !authenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    if (to.meta.guest && authenticated) {
        return { name: 'users' };
    }
});

export default router;

import {
    Bell,
    Building2,
    ScrollText,
    Settings,
    ShieldCheck,
    Users,
} from '@lucide/vue';

export const menuItems = [
    {
        label: 'Users',
        path: '/users',
        icon: Users,
        permission: 'users.list',
    },
    {
        label: 'Management',
        icon: Settings,
        type: 'dropdown',
        position: 'bottom',
        children: [
            {
                label: 'Admins',
                path: '/admins',
                icon: ShieldCheck,
                permission: 'admins.list',
            },
            {
                label: 'Access logs',
                path: '/access-logs',
                icon: ScrollText,
                permission: 'access_logs.list',
            },
            {
                label: 'Departments',
                path: '/departments',
                icon: Building2,
                permission: 'departments.list',
            },
            {
                label: 'Notifications',
                path: '/notifications',
                icon: Bell,
                permission: 'notifications.list',
            },
        ],
    },
];

export const menuItemsForPosition = (position) => menuItems.filter(
    (item) => (item.position ?? 'main') === position,
);

export default menuItems;

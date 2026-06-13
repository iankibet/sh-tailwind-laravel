import '../css/app.css';
import { createPinia } from 'pinia';
import { createApp } from 'vue';
import { ShTailwind } from '@iankibetsh/sh-tailwind';
import App from './App.vue';
import router from './router';

const app = createApp(App);

app.use(createPinia());
app.use(ShTailwind, {
    baseApiUrl: '/api/',
    authMode: 'bearer',
    tokenStorage: 'local',
    loginUrl: '/login',
    streamlineUrl: 'streamline',
    enableTableCache: true,
    tablePerPage: 10,
    theme: {
        form: {
            submitBtn: 'inline-flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-brand-700 focus:outline-none focus:ring-4 focus:ring-brand-500/20 disabled:cursor-not-allowed disabled:opacity-60',
        },
        table: {
            container: 'hidden overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm md:block',
            cards: 'space-y-3 md:hidden',
        },
    },
});
app.use(router);

app.mount('#app');
